<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\PdfAnnotation;
use App\Services\AuditLogger;
use App\Services\PdfStamper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfAnnotationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PdfStamper $stamper,
    ) {}

    public function store(Request $request, Attachment $attachment): RedirectResponse
    {
        if (! $attachment->visibleTo($request->user())) abort(403);

        $data = $request->validate([
            'kind' => ['required', 'in:note,stamp,highlight'],
            'text' => ['required', 'string', 'max:500'],
            'color' => ['nullable', 'in:slate,emerald,rose,amber,indigo,violet,sky'],
            'page' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $ann = PdfAnnotation::create([
            'attachment_id' => $attachment->id,
            'created_by' => $request->user()->id,
            'kind' => $data['kind'],
            'text' => $data['text'],
            'color' => $data['color'] ?? 'slate',
            'page' => $data['page'] ?? null,
        ]);

        $this->audit->log('pdf.annotation.added', $attachment, null, [
            'kind' => $ann->kind,
            'text' => $ann->text,
            'page' => $ann->page,
        ], "Notiz '{$ann->text}' an Dokument #{$attachment->id} hinzugefügt", $request->user()->id);

        // Bei Stempeln auf PDF-Dateien: visuellen Stempel tatsächlich auf
        // die letzte Seite des PDFs rendern und als neue Version speichern.
        if ($data['kind'] === 'stamp' && $attachment->isPdf()) {
            try {
                $this->applyVisualStamp($attachment, $data, $request->user());
            } catch (\Throwable $e) {
                \Log::warning('Visual stamp failed', ['id' => $attachment->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('status', $data['kind'] === 'stamp' ? 'Stempel auf dem PDF gesetzt.' : 'Notiz hinzugefügt.');
    }

    private function applyVisualStamp(Attachment $attachment, array $data, \App\Models\User $user): void
    {
        $bytes = Storage::disk($attachment->disk)->get($attachment->path);
        if (! $bytes) return;

        $colorMap = [
            'emerald' => 'emerald', 'indigo' => 'slate', 'sky' => 'slate',
            'rose' => 'rose', 'amber' => 'amber', 'slate' => 'slate', 'violet' => 'slate',
        ];
        $stampData = [
            'title' => $data['text'],
            'lines' => [
                'von '.$user->name,
                'am '.now()->format('d.m.Y H:i'),
            ],
            'color' => $colorMap[$data['color'] ?? 'slate'] ?? 'slate',
        ];

        $stamped = $this->stamper->stamp($bytes, $stampData);
        $hash = hash('sha256', $stamped);
        $dir = 'attachments/'.now()->format('Y/m');
        $path = $dir.'/'.Str::ulid().'.pdf';
        Storage::disk($attachment->disk)->put($path, $stamped);

        DB::transaction(function () use ($attachment, $path, $stamped, $hash, $user) {
            Attachment::where('version_chain_id', $attachment->version_chain_id)
                ->update(['is_current_version' => false]);

            Attachment::create([
                'attachable_type' => $attachment->attachable_type,
                'attachable_id' => $attachment->attachable_id,
                'original_name' => $attachment->original_name,
                'disk' => $attachment->disk,
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => strlen($stamped),
                'content_hash' => $hash,
                'label' => $attachment->label,
                'uploaded_by' => $user->id,
                'document_type' => $attachment->document_type,
                'ocr_status' => 'pending',
                'version_chain_id' => $attachment->version_chain_id,
                'version_number' => (int) ($attachment->version_number ?? 1) + 1,
                'is_current_version' => true,
            ]);
        });

        $this->audit->log('attachment.stamped', $attachment, null,
            ['title' => $stampData['title']],
            'Stempel visuell auf PDF gerendert: '.$stampData['title'].' ('.$attachment->original_name.')',
            $user->id);
    }

    public function destroy(Request $request, PdfAnnotation $annotation): RedirectResponse
    {
        if (! $annotation->attachment->visibleTo($request->user())) abort(403);
        $isOwn = $annotation->created_by === $request->user()->id;
        if (! $isOwn && ! $request->user()->hasAnyPermission(['workflows.design', 'documents.search'])) {
            abort(403);
        }

        $snapshot = $annotation->only(['kind', 'text', 'color', 'page', 'created_by']);
        $att = $annotation->attachment;
        $annotation->delete();

        $this->audit->log('pdf.annotation.removed', $att, $snapshot, null,
            'Notiz entfernt', $request->user()->id);

        return back()->with('status', 'Notiz entfernt.');
    }
}
