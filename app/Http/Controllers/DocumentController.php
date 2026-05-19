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
        $fieldFilters = (array) $request->get('fields', []);

        $visibleTypes = DocumentTypes::visibleForUser($user);
        $allowAll = $user->hasRole('admin');
        $includeUnclassified = (bool) \App\Support\Settings::get('attachments.unclassified_visible_for_all', false);

        // Sichtbarkeits-Filter als Closure, damit wir ihn fuer Trefferliste UND Archiv-Counts wiederverwenden.
        $applyVisibility = function ($q) use ($allowAll, $visibleTypes, $includeUnclassified) {
            if ($allowAll) return $q;
            return $q->where(function ($w) use ($visibleTypes, $includeUnclassified) {
                if ($includeUnclassified) $w->whereNull('document_type');
                if (! empty($visibleTypes)) $w->orWhereIn('document_type', $visibleTypes);
                if (! $includeUnclassified && empty($visibleTypes)) $w->whereRaw('1=0');
            });
        };

        // Anzahl pro Archiv (Dokumenttyp) — eine GROUP-BY-Query.
        $rawCounts = $applyVisibility(
            Attachment::query()->where('is_current_version', true)
        )
            ->selectRaw('document_type, COUNT(*) as c')
            ->groupBy('document_type')
            ->pluck('c', 'document_type')
            ->all();
        $archiveCounts = [];
        $unclassifiedCount = 0;
        foreach ($rawCounts as $key => $count) {
            if ($key === null || $key === '') {
                $unclassifiedCount += (int) $count;
            } else {
                $archiveCounts[(string) $key] = (int) $count;
            }
        }
        $totalDocs = array_sum($archiveCounts) + $unclassifiedCount;

        // Standard: nur die aktuelle Version pro Chain anzeigen.
        $query = Attachment::with('attachable', 'uploader')
            ->where('is_current_version', true)
            ->orderByDesc('id');
        $applyVisibility($query);

        if ($type === '__unclassified__') {
            $query->whereNull('document_type');
        } elseif ($type) {
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

        // Filter auf indexed_fields (nur wenn ein Typ gewaehlt ist, denn nur
        // dann gibt es ein Schema mit erlaubten Schluesseln).
        $schema = ($type && $type !== '__unclassified__') ? \App\Support\DocumentFieldSchema::forType((string) $type) : [];
        $activeFieldFilters = [];
        if ($schema && $fieldFilters) {
            $allowedKeys = array_column($schema, 'key');
            $byKey = collect($schema)->keyBy('key');
            foreach ($fieldFilters as $key => $rawValue) {
                if (! in_array($key, $allowedKeys, true)) continue;
                $field = $byKey[$key];

                if (is_array($rawValue)) {
                    // Range fuer date / currency / number: ['from' => ..., 'to' => ...]
                    $from = trim((string) ($rawValue['from'] ?? ''));
                    $to = trim((string) ($rawValue['to'] ?? ''));
                    if ($from === '' && $to === '') continue;
                    if ($from !== '') {
                        $query->where('indexed_fields->'.$key, '>=', $from);
                    }
                    if ($to !== '') {
                        $query->where('indexed_fields->'.$key, '<=', $to);
                    }
                    $activeFieldFilters[$key] = ['from' => $from, 'to' => $to];
                } else {
                    $value = trim((string) $rawValue);
                    if ($value === '') continue;
                    // String, IBAN, E-Mail: contains. Sonst exakt.
                    if (in_array($field['type'], ['string', 'iban', 'email'], true)) {
                        $query->where('indexed_fields->'.$key, 'like', '%'.$value.'%');
                    } else {
                        $query->where('indexed_fields->'.$key, $value);
                    }
                    $activeFieldFilters[$key] = $value;
                }
            }
        }

        return view('documents.index', [
            'documents' => $query->paginate(25)->withQueryString(),
            'types' => $visibleTypes,
            'archiveCounts' => $archiveCounts,
            'unclassifiedCount' => $unclassifiedCount,
            'totalDocs' => $totalDocs,
            'unclassifiedVisible' => $allowAll || $includeUnclassified,
            'q' => $q,
            'type' => $type,
            'status' => $status,
            'schema' => $schema,
            'fieldFilters' => $activeFieldFilters,
            'ocrAvailability' => $ocr->availability(),
        ]);
    }

    /**
     * Exportiert die aktuelle Suche (gleiche Filter wie /dokumente) als
     * CSV. Inkl. erkannter Felder, falls ein Dokumenttyp gewaehlt ist.
     */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $q = trim((string) $request->get('q', ''));
        $type = $request->get('type');
        $status = $request->get('status');
        $fieldFilters = (array) $request->get('fields', []);

        $visibleTypes = DocumentTypes::visibleForUser($user);
        $allowAll = $user->hasRole('admin');

        $query = Attachment::with('uploader')->where('is_current_version', true)->orderByDesc('id');

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

        $schema = $type ? \App\Support\DocumentFieldSchema::forType((string) $type) : [];
        if ($schema && $fieldFilters) {
            $allowedKeys = array_column($schema, 'key');
            $byKey = collect($schema)->keyBy('key');
            foreach ($fieldFilters as $key => $rawValue) {
                if (! in_array($key, $allowedKeys, true)) continue;
                $field = $byKey[$key];
                if (is_array($rawValue)) {
                    $from = trim((string) ($rawValue['from'] ?? ''));
                    $to = trim((string) ($rawValue['to'] ?? ''));
                    if ($from !== '') $query->where('indexed_fields->'.$key, '>=', $from);
                    if ($to !== '') $query->where('indexed_fields->'.$key, '<=', $to);
                } else {
                    $value = trim((string) $rawValue);
                    if ($value === '') continue;
                    if (in_array($field['type'], ['string', 'iban', 'email'], true)) {
                        $query->where('indexed_fields->'.$key, 'like', '%'.$value.'%');
                    } else {
                        $query->where('indexed_fields->'.$key, $value);
                    }
                }
            }
        }

        $fieldKeys = array_column($schema, 'key');
        $filename = 'dokumente-export-'.now()->format('Y-m-d-Hi').'.csv';

        $this->audit->log('documents.exported', null, null, [
            'filters' => array_filter(['q' => $q, 'type' => $type, 'status' => $status, 'fields' => $fieldFilters]),
        ], "CSV-Export Dokumente ({$user->email})", $user->id);

        return response()->streamDownload(function () use ($query, $fieldKeys) {
            $out = fopen('php://output', 'w');
            // BOM fuer Excel
            fwrite($out, "\xEF\xBB\xBF");
            $header = ['id', 'dateiname', 'dokumenttyp', 'beschriftung',
                'mime', 'groesse_bytes', 'hochgeladen_am', 'hochgeladen_von',
                'ocr_status'];
            foreach ($fieldKeys as $k) {
                $header[] = 'feld_'.$k;
            }
            fputcsv($out, $header, ';');

            $query->chunk(500, function ($chunk) use ($out, $fieldKeys) {
                foreach ($chunk as $att) {
                    $row = [
                        $att->id,
                        $att->original_name,
                        $att->document_type,
                        $att->label,
                        $att->mime_type,
                        $att->size,
                        $att->created_at?->format('Y-m-d H:i'),
                        $att->uploader?->email,
                        $att->ocr_status,
                    ];
                    $fields = (array) ($att->indexed_fields ?? []);
                    foreach ($fieldKeys as $k) {
                        $row[] = $fields[$k] ?? '';
                    }
                    fputcsv($out, $row, ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Postkorb: alle Anhaenge, die noch keinem Objekt angehaengt sind
     * (z. B. via IMAP eingegangen, ohne Workflow-Trigger). Pro Zeile kann
     * ein Workflow manuell gestartet werden — die erkannten Felder
     * wandern in den Kontext.
     */
    public function inbox(Request $request): View
    {
        $user = $request->user();
        $visibleTypes = DocumentTypes::visibleForUser($user);
        $allowAll = $user->hasRole('admin');

        $query = Attachment::with('uploader')
            ->whereNull('attachable_type')
            ->where('is_current_version', true)
            ->orderByDesc('id');

        if (! $allowAll) {
            $query->whereIn('document_type', $visibleTypes ?: ['__none__']);
        }

        $workflows = \App\Models\Workflow::where('status', \App\Models\Workflow::STATUS_ACTIVE)
            ->orderBy('name')->get(['id', 'name', 'trigger_type']);

        return view('documents.inbox', [
            'documents' => $query->paginate(25),
            'workflows' => $workflows,
        ]);
    }

    public function bulkStartWorkflow(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workflow_id' => ['required', 'integer', 'exists:workflows,id'],
            'attachment_ids' => ['required', 'array', 'min:1', 'max:50'],
            'attachment_ids.*' => ['integer', 'exists:attachments,id'],
        ]);

        $workflow = \App\Models\Workflow::findOrFail($data['workflow_id']);
        if ($workflow->status !== \App\Models\Workflow::STATUS_ACTIVE) {
            return back()->withErrors(['workflow_id' => 'Workflow ist nicht aktiv.']);
        }

        $attachments = Attachment::whereIn('id', $data['attachment_ids'])
            ->whereNull('attachable_type')
            ->where('is_current_version', true)
            ->get();

        $started = 0; $skipped = 0;
        foreach ($attachments as $att) {
            if (! DocumentTypes::canViewType($request->user(), $att->document_type)) {
                $skipped++;
                continue;
            }
            $form = array_merge((array) ($att->indexed_fields ?? []), [
                'doc_attachment_id' => $att->id,
                'doc_original_name' => $att->original_name,
                'doc_document_type' => $att->document_type,
            ]);
            $instance = app(\App\Services\WorkflowEngine::class)->start($workflow, $form, $request->user());
            $att->update([
                'attachable_type' => $instance->getMorphClass(),
                'attachable_id' => $instance->id,
            ]);
            $this->audit->log('document.workflow.started', $att, null, [
                'workflow_id' => $workflow->id, 'instance_id' => $instance->id, 'via' => 'bulk',
            ], "Workflow {$workflow->name} aus Postkorb gestartet (#{$att->id}, bulk)");
            $started++;
        }

        return redirect()->route('documents.inbox')
            ->with('status', "Workflow {$workflow->name}: {$started} gestartet"
                .($skipped ? ", {$skipped} ohne Berechtigung uebersprungen" : '').'.');
    }

    public function startWorkflow(Request $request, Attachment $attachment): RedirectResponse
    {
        if (! DocumentTypes::canViewType($request->user(), $attachment->document_type)) abort(403);

        $data = $request->validate([
            'workflow_id' => ['required', 'integer', 'exists:workflows,id'],
        ]);

        $workflow = \App\Models\Workflow::findOrFail($data['workflow_id']);
        if ($workflow->status !== \App\Models\Workflow::STATUS_ACTIVE) {
            return back()->withErrors(['workflow_id' => 'Workflow ist nicht aktiv.']);
        }

        // Form-Kontext: erkannte Felder + Referenz auf das Dokument.
        $form = array_merge(
            (array) ($attachment->indexed_fields ?? []),
            [
                'doc_attachment_id' => $attachment->id,
                'doc_original_name' => $attachment->original_name,
                'doc_document_type' => $attachment->document_type,
            ],
        );

        $instance = app(\App\Services\WorkflowEngine::class)->start($workflow, $form, $request->user());

        // Anhang an die Instanz haengen, damit er im Workflow-Viewer sichtbar ist.
        $attachment->update([
            'attachable_type' => $instance->getMorphClass(),
            'attachable_id' => $instance->id,
        ]);

        $this->audit->log('document.workflow.started', $attachment, null, [
            'workflow_id' => $workflow->id, 'instance_id' => $instance->id,
        ], "Workflow {$workflow->name} aus Postkorb gestartet (#{$attachment->id})");

        return redirect()->route('workflow-instances.show', $instance)
            ->with('status', 'Workflow gestartet.');
    }

    public function show(Attachment $attachment, Request $request): View
    {
        // Type-Mapping ODER Kontext (Workflow-Assignee / Asset-Owner).
        if (! $attachment->visibleTo($request->user())) abort(403);
        $versions = $attachment->versions()->with('uploader')->get();

        // ZUGFeRD-Daten ermitteln: erst aus indexed_fields._zugferd (z. B.
        // wenn separate XRechnung-XML aus einer Mail dazu kam), sonst aus
        // dem PDF selbst parsen.
        $zugferdData = null;
        if (! empty($attachment->indexed_fields['_zugferd'] ?? null)) {
            $zugferdData = $attachment->indexed_fields['_zugferd'];
        } elseif ($attachment->mime_type === 'application/pdf') {
            try {
                $zugferdData = app(\App\Services\ZugferdParser::class)->parse($attachment);
            } catch (\Throwable) {
                $zugferdData = null;
            }
        }

        // Aktive Workflows fuer den "Workflow starten"-Button (nur die,
        // bei denen der User auch starten darf).
        $availableWorkflows = \App\Models\Workflow::where('status', \App\Models\Workflow::STATUS_ACTIVE)
            ->orderBy('name')->get(['id', 'name'])
            ->filter(fn ($w) => $request->user()->hasAnyPermission(['workflows.run', 'workflows.design']))
            ->values();

        return view('documents.show', [
            'attachment' => $attachment->load('attachable', 'uploader'),
            'versions' => $versions,
            'zugferdData' => $zugferdData,
            'availableWorkflows' => $availableWorkflows,
        ]);
    }

    /**
     * Bulk-Aktion auf mehrere ausgewaehlte Dokumente: Typ aendern, Tag
     * setzen/entfernen, Akte hinzufuegen, archivieren.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'attachment_ids' => ['required', 'array', 'min:1', 'max:500'],
            'attachment_ids.*' => ['integer', 'exists:attachments,id'],
            'action' => ['required', 'in:set_type,add_tag,remove_tag,add_case,archive'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'case_id' => ['nullable', 'integer', 'exists:document_cases,id'],
        ]);

        $user = $request->user();
        $attachments = Attachment::whereIn('id', $data['attachment_ids'])->get();
        $allowed = $attachments->filter(fn ($a) => DocumentTypes::canViewType($user, $a->document_type));
        $touched = 0;

        foreach ($allowed as $att) {
            switch ($data['action']) {
                case 'set_type':
                    $att->forceFill(['document_type' => $data['document_type'] ?: null])->save();
                    $touched++;
                    break;
                case 'add_tag':
                    if ($data['tag_id']) {
                        $att->tags()->syncWithoutDetaching([$data['tag_id']]);
                        $touched++;
                    }
                    break;
                case 'remove_tag':
                    if ($data['tag_id']) {
                        $att->tags()->detach($data['tag_id']);
                        $touched++;
                    }
                    break;
                case 'add_case':
                    if ($data['case_id']) {
                        $att->cases()->syncWithoutDetaching([$data['case_id']]);
                        $touched++;
                    }
                    break;
                case 'archive':
                    $att->delete();
                    $touched++;
                    break;
            }
        }

        $this->audit->log('documents.bulk_action', null, null, [
            'action' => $data['action'], 'count' => $touched,
            'ids' => $allowed->pluck('id')->all(),
        ], "Bulk-Aktion {$data['action']}: {$touched} Dokument(e)", $user->id);

        return back()->with('status', "{$touched} Dokument(e) verarbeitet.");
    }

    public function updateIndexedFields(Request $request, Attachment $attachment): RedirectResponse
    {
        if (! DocumentTypes::canViewType($request->user(), $attachment->document_type)) abort(403);

        $fields = (array) $request->input('fields', []);
        // Nur erlaubte Schluessel uebernehmen — aus dem Schema.
        $schema = \App\Support\DocumentFieldSchema::forType((string) $attachment->document_type);
        $allowed = array_column($schema, 'key');
        $clean = [];
        foreach ($fields as $k => $v) {
            $k = trim((string) $k);
            if ($k === '' || ! in_array($k, $allowed, true)) continue;
            $v = trim((string) $v);
            if ($v === '') continue;
            $clean[$k] = $v;
        }

        $original = $attachment->indexed_fields ?? [];
        $attachment->forceFill([
            'indexed_fields' => $clean,
            'indexed_at' => now(),
        ])->save();

        $this->audit->log('document.fields.corrected', $attachment, $original, $clean,
            "Erkannte Felder manuell korrigiert (#{$attachment->id})");

        return back()->with('status', 'Felder gespeichert.');
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
        if (! $attachment->visibleTo($request->user())) abort(403);
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
