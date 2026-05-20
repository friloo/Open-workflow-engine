<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\AttachmentStorage;
use App\Services\AuditLogger;
use App\Support\DocumentTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Externer Zugriff auf Dokumente per JSON-API. Token-basiert mit Ability
 * 'documents.read' fuer Lesen und 'documents.write' fuer Upload/Update.
 *
 * Bewusst minimal: keine versteckte Magie. Was im Web-UI machbar ist,
 * ist auch hier machbar — mit denselben Permission-Checks
 * (DocumentTypes::canViewType / visibleTo).
 */
class DocumentsApiController extends Controller
{
    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /api/v1/documents — Suche analog zum Web-UI. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->get('q', ''));
        $type = $request->get('type');
        $status = $request->get('status');
        $perPage = min(100, max(1, (int) $request->get('per_page', 25)));

        $visibleTypes = DocumentTypes::visibleForUser($user);
        $allowAll = $user->hasRole('admin');

        $query = Attachment::query()
            ->where('is_current_version', true)
            ->orderByDesc('id');

        if (! $allowAll) {
            $query->where(function ($w) use ($visibleTypes) {
                $w->whereNull('document_type');
                if ($visibleTypes) $w->orWhereIn('document_type', $visibleTypes);
                else $w->whereRaw('1=1');
            });
        }
        if ($type) $query->where('document_type', $type);
        if ($status) $query->where('ocr_status', $status);
        if ($q !== '') {
            app(\App\Services\Search\DocumentSearch::class)->applyFulltext($query, $q);
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn (Attachment $a) => $this->toArray($a))->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /** GET /api/v1/documents/{attachment} — Metadata + indexed_fields. */
    public function show(Attachment $attachment, Request $request): JsonResponse
    {
        if (! $attachment->visibleTo($request->user())) abort(403);
        return response()->json(['data' => $this->toArray($attachment, true)]);
    }

    /** GET /api/v1/documents/{attachment}/download — Stream der Datei. */
    public function download(Attachment $attachment, Request $request): StreamedResponse
    {
        if (! $attachment->visibleTo($request->user())) abort(403);
        return $this->storage->streamDownload($attachment);
    }

    /** POST /api/v1/documents — multipart Upload. */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            $att = $this->storage->store(
                $request->file('file'),
                null,
                $data['label'] ?? null,
                $request->user()->id,
                $data['document_type'] ?? null,
            );
        } catch (\App\Exceptions\DuplicateAttachmentException $e) {
            return response()->json([
                'error' => 'duplicate',
                'message' => $e->getMessage(),
                'original_id' => $e->original->id,
            ], 409);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $this->audit->log('api.document.uploaded', $att, null, [
            'name' => $att->original_name, 'sha256' => $att->content_hash,
            'token' => $request->attributes->get('api_token')?->name,
        ], 'Upload via API', $request->user()->id);

        return response()->json(['data' => $this->toArray($att, true)], 201);
    }

    /** PATCH /api/v1/documents/{attachment} — Indexfelder + Label updaten. */
    public function update(Request $request, Attachment $attachment): JsonResponse
    {
        if (! $attachment->visibleTo($request->user())) abort(403);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:128'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'indexed_fields' => ['nullable', 'array'],
        ]);

        $before = $attachment->only(['label', 'document_type', 'indexed_fields']);
        if (array_key_exists('label', $data)) $attachment->label = $data['label'];
        if (array_key_exists('document_type', $data)) $attachment->document_type = $data['document_type'];
        if (isset($data['indexed_fields'])) {
            $attachment->indexed_fields = array_merge((array) $attachment->indexed_fields, $data['indexed_fields']);
            $attachment->indexed_at = now();
        }
        $attachment->save();

        $this->audit->log('api.document.updated', $attachment, $before, [
            'label' => $attachment->label, 'document_type' => $attachment->document_type,
            'indexed_fields' => $attachment->indexed_fields,
        ], 'Update via API', $request->user()->id);

        return response()->json(['data' => $this->toArray($attachment, true)]);
    }

    private function toArray(Attachment $a, bool $detail = false): array
    {
        $base = [
            'id' => $a->id,
            'original_name' => $a->original_name,
            'mime_type' => $a->mime_type,
            'size' => $a->size,
            'document_type' => $a->document_type,
            'content_hash' => $a->content_hash,
            'created_at' => $a->created_at?->toIso8601String(),
            'version_number' => $a->version_number,
            'is_current_version' => (bool) $a->is_current_version,
        ];
        if ($detail) {
            $base['indexed_fields'] = (object) ($a->indexed_fields ?? []);
            $base['ocr_status'] = $a->ocr_status;
            $base['ocr_tool'] = $a->ocr_tool;
            $base['label'] = $a->label;
        }
        return $base;
    }
}
