<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\AuditLogger;
use App\Services\FieldExtractor;
use App\Support\DocumentFieldSchema;
use App\Support\DocumentTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentSchemaController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FieldExtractor $extractor,
    ) {}

    public function index(): View
    {
        $types = DocumentTypes::all();
        $schemas = [];
        foreach ($types as $t) {
            $schemas[$t] = DocumentFieldSchema::forType($t);
        }
        return view('admin.document-schemas.index', [
            'types' => $types,
            'schemas' => $schemas,
        ]);
    }

    public function edit(string $type): View
    {
        abort_unless(in_array($type, DocumentTypes::all(), true), 404);
        return view('admin.document-schemas.edit', [
            'type' => $type,
            'fields' => DocumentFieldSchema::forType($type),
            'fieldTypes' => DocumentFieldSchema::FIELD_TYPES,
            'extractors' => DocumentFieldSchema::EXTRACTORS,
            'lookupLists' => \App\Models\LookupList::orderBy('name')->get(['id', 'name', 'slug']),
            'sampleCount' => Attachment::where('document_type', $type)->whereNotNull('indexed_fields')->count(),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, DocumentTypes::all(), true), 404);

        $data = $request->validate([
            'fields' => ['array'],
            'fields.*.key' => ['required', 'string', 'max:64'],
            'fields.*.label' => ['required', 'string', 'max:128'],
            'fields.*.type' => ['required', 'in:'.implode(',', array_keys(DocumentFieldSchema::FIELD_TYPES))],
            'fields.*.extractor' => ['required', 'in:'.implode(',', array_keys(DocumentFieldSchema::EXTRACTORS))],
            'fields.*.pattern' => ['nullable', 'string', 'max:512'],
            'fields.*.ki_fallback' => ['nullable', 'boolean'],
        ]);

        $clean = [];
        foreach ($data['fields'] ?? [] as $f) {
            if (empty(trim($f['key']))) continue;
            $clean[] = $f + ['ki_fallback' => (bool) ($f['ki_fallback'] ?? false)];
        }
        DocumentFieldSchema::save($type, $clean);

        $this->audit->log('settings.document_schema.updated', null, null,
            ['document_type' => $type, 'fields' => collect($clean)->pluck('key')->all()],
            "Schema fuer {$type} aktualisiert", $request->user()->id);

        return redirect()->route('admin.document_schemas.edit', $type)
            ->with('status', 'Schema gespeichert.');
    }

    public function reindex(Request $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, DocumentTypes::all(), true), 404);

        $n = 0;
        Attachment::where('document_type', $type)->orderBy('id')->chunk(100, function ($chunk) use (&$n) {
            foreach ($chunk as $att) {
                try {
                    $this->extractor->extractFor($att);
                    $n++;
                } catch (\Throwable) {
                }
            }
        });

        $this->audit->log('attachments.reindexed', null, null,
            ['document_type' => $type, 'count' => $n],
            "Felder fuer {$n} Anhaenge ({$type}) neu extrahiert", $request->user()->id);

        return back()->with('status', "{$n} Anhaenge neu indexiert.");
    }
}
