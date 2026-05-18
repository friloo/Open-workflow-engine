<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\OcrExtractor;
use App\Support\DocumentTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request, OcrExtractor $ocr): View
    {
        $user = $request->user();
        $q = trim((string) $request->get('q', ''));
        $type = $request->get('type');
        $status = $request->get('status');

        $visibleTypes = DocumentTypes::visibleForUser($user);
        $allowAll = $user->hasRole('admin');

        $query = Attachment::with('attachable', 'uploader')->orderByDesc('id');

        // Rollenbasierte Berechtigung auf Dokumenttypen
        if (! $allowAll) {
            $includeUnclassified = (bool) \App\Support\Settings::get('attachments.unclassified_visible_for_all', false);
            $query->where(function ($w) use ($visibleTypes, $includeUnclassified) {
                if ($includeUnclassified) {
                    $w->whereNull('document_type');
                }
                if (! empty($visibleTypes)) {
                    $w->orWhereIn('document_type', $visibleTypes);
                }
                if (! $includeUnclassified && empty($visibleTypes)) {
                    $w->whereRaw('1=0');
                }
            });
        }

        if ($type) {
            // Selbstgewaehlter Filter — nur erlaubt, wenn auch sichtbar
            if (! $allowAll && ! in_array($type, $visibleTypes, true)) {
                abort(403);
            }
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
        if (! DocumentTypes::canViewType($request->user(), $attachment->document_type)) {
            abort(403);
        }
        return view('documents.show', [
            'attachment' => $attachment->load('attachable', 'uploader'),
        ]);
    }

    public function reindex(Attachment $attachment, OcrExtractor $ocr): RedirectResponse
    {
        $attachment->forceFill(['ocr_status' => 'pending'])->save();
        $ocr->extract($attachment);
        return back()->with('status', 'OCR neu gestartet: '.$attachment->fresh()->ocr_status);
    }
}
