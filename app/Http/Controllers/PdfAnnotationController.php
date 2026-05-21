<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\PdfAnnotation;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Verwaltet Notizen + Stempel auf Dokumenten. Bewusst leichtgewichtig:
 * kein visuelles Overlay auf dem PDF (das braeuchte pdf.js + Custom-
 * Renderer), sondern eine Liste von Notizen / Stempeln mit Seitenzahl
 * + Author + Datum unter dem Preview.
 *
 * Wer es später visuell will: Koordinaten (x/y/width/height) lassen
 * sich in den 'text'-Spalten-payload kodieren oder als zusaetzliche
 * Spalten ergänzen — das Datenmodell trägt das mit.
 */
class PdfAnnotationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

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

        return back()->with('status', 'Notiz hinzugefügt.');
    }

    public function destroy(Request $request, PdfAnnotation $annotation): RedirectResponse
    {
        if (! $annotation->attachment->visibleTo($request->user())) abort(403);
        // Eigene immer löschbar; fremde nur als Admin oder als Doc-Owner.
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
