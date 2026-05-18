<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentStorage;
use App\Services\AuditLogger;
use App\Services\OcrExtractor;
use App\Support\DocumentTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, OcrExtractor $ocr): View
    {
        $user = $request->user();
        $q = trim((string) $request->get('q', ''));
        $type = $request->get('type');
        $status = $request->get('status');

        $visibleTypes = DocumentTypes::visibleForUser($user);
        $allowAll = $user->hasRole('admin');

        // Standard: nur die aktuelle Version pro Chain anzeigen.
        $query = Attachment::with('attachable', 'uploader')
            ->where('is_current_version', true)
            ->orderByDesc('id');

        if (! $allowAll) {
            $includeUnclassified = (bool) \App\Support\Settings::get('attachments.unclassified_visible_for_all', false);
            $query->where(function ($w) use ($visibleTypes, $includeUnclassified) {
                if ($includeUnclassified) $w->whereNull('document_type');
                if (! empty($visibleTypes)) $w->orWhereIn('document_type', $visibleTypes);
                if (! $includeUnclassified && empty($visibleTypes)) $w->whereRaw('1=0');
            });
        }

        if ($type) {
            if (! $allowAll && ! in_array($type, $visibleTypes, true)) abort(403);
            $query->where('document_type', $type);
        }
        if ($status) $query->where('ocr_status', $status);
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('original_name', 'like', "%{$q}%")
                   ->orWhere('label', 'like', "%{$q}%")
                   ->orWhere('ocr_text', 'like', "%{$q}%");
            });
        }

        return view('documents.index', [
            'documents' => $query->paginate(25)->withQueryString(),
            'types' => $visibleTypes,
            'q' => $q,
            'type' => $type,
            'status' => $status,
            'ocrAvailability' => $ocr->availability(),
        ]);
    }

    public function show(Attachment $attachment, Request $request): View
    {
        if (! DocumentTypes::canViewType($request->user(), $attachment->document_type)) abort(403);
        $versions = $attachment->versions()->with('uploader')->get();
        return view('documents.show', [
            'attachment' => $attachment->load('attachable', 'uploader'),
            'versions' => $versions,
        ]);
    }

    public function reindex(Attachment $attachment, OcrExtractor $ocr): RedirectResponse
    {
        $attachment->forceFill(['ocr_status' => 'pending'])->save();
        $ocr->extract($attachment);
        return back()->with('status', 'OCR neu gestartet: '.$attachment->fresh()->ocr_status);
    }

    /**
     * Inline-Preview: setzt Content-Disposition: inline statt attachment,
     * so dass der Browser PDF/Bild direkt anzeigt.
     */
    public function preview(Attachment $attachment, Request $request): StreamedResponse
    {
        if (! DocumentTypes::canViewType($request->user(), $attachment->document_type)) abort(403);
        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) abort(404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function uploadVersion(Request $request, Attachment $attachment): RedirectResponse
    {
        if (! $request->user()->hasPermission('documents.search')) abort(403);
        $request->validate(['file' => ['required', 'file', 'max:15360']]);
        try {
            $new = $this->storage->store(
                $request->file('file'),
                $attachment->attachable,
                $attachment->label,
                $request->user()->id,
                $attachment->document_type,
                $attachment,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        $this->audit->log('document.new_version', $new, null, [
            'chain' => $new->version_chain_id,
            'version' => $new->version_number,
            'sha256' => $new->content_hash,
        ], "Neue Version v{$new->version_number} hochgeladen: {$new->original_name}");
        return redirect()->route('documents.show', $new)->with('status', "Neue Version v{$new->version_number} gespeichert.");
    }

    public function bulkUploadShow(): View
    {
        return view('documents.bulk', [
            'types' => DocumentTypes::all(),
        ]);
    }

    public function bulkUploadStore(Request $request): RedirectResponse
    {
        if (! $request->user()->hasPermission('documents.search')) abort(403);
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', 'file', 'max:15360'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:128'],
        ]);
        $ok = 0; $errors = [];
        foreach ($request->file('files') as $file) {
            try {
                $this->storage->store($file, null, $data['label'] ?? null, $request->user()->id, $data['document_type'] ?? null);
                $ok++;
            } catch (\Throwable $e) {
                $errors[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }
        $this->audit->log('documents.bulk_uploaded', null, null, [
            'imported' => $ok, 'errors' => count($errors), 'type' => $data['document_type'] ?? null,
        ], "Bulk-Upload: {$ok} Dateien", $request->user()->id);
        return redirect()->route('documents.index')->with('status', "Hochgeladen: {$ok} Dateien.".(count($errors) ? ' '.count($errors).' Fehler.' : ''))
            ->with('uploadErrors', $errors);
    }
}
