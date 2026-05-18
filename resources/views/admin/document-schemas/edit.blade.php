<x-app-layout>
    <x-slot name="header">Schema: {{ $type }}</x-slot>
    <x-slot name="subheader">Welche Felder sollen pro Anhang dieses Typs automatisch erkannt und gespeichert werden?</x-slot>

    <form method="POST" action="{{ route('admin.document_schemas.update', $type) }}"
          x-data='@json(["fields" => array_map(fn($f) => $f + ["ki_fallback" => (bool) $f["ki_fallback"]], $fields)])'>
        @csrf @method('PUT')
        <x-card>
            <div class="space-y-3"
                 x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                 x-sort="fields.splice($event.newIndex, 0, fields.splice($event.oldIndex, 1)[0])">
                <template x-for="(f, idx) in fields" :key="idx">
                    <div class="rounded-lg border border-slate-200 bg-white p-3" x-sort:item="idx">
                        <div class="flex items-start gap-2">
                            <span class="drag-handle cursor-grab select-none text-xs text-slate-400 mt-1 px-1">⋮⋮</span>
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-2">
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-medium text-slate-600">Schluessel</label>
                                    <input type="text" :name="`fields[${idx}][key]`" x-model="f.key" placeholder="rechnungsnummer"
                                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-medium text-slate-600">Bezeichnung</label>
                                    <input type="text" :name="`fields[${idx}][label]`" x-model="f.label" placeholder="Rechnungsnummer"
                                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-slate-600">Typ</label>
                                    <select :name="`fields[${idx}][type]`" x-model="f.type"
                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach($fieldTypes as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-medium text-slate-600">Erkennung</label>
                                    <select :name="`fields[${idx}][extractor]`" x-model="f.extractor"
                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach($extractors as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-1 flex items-end justify-end">
                                    <button type="button" @click="fields.splice(idx, 1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                                </div>
                                <template x-if="f.extractor === 'regex'">
                                    <div class="md:col-span-12">
                                        <label class="block text-xs font-medium text-slate-600">Regex (Wert kommt aus Capture-Group 1)</label>
                                        <input type="text" :name="`fields[${idx}][pattern]`" x-model="f.pattern" placeholder="Auftrag\s*Nr\.?\s*([A-Z0-9\-]+)"
                                               class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                        <p class="mt-1 text-xs text-slate-500">Ohne Delimiter wird automatisch <code>#…#i</code> drumherum gesetzt. Slash muss nicht escaped werden.</p>
                                    </div>
                                </template>
                                <template x-if="f.extractor === 'lookup'">
                                    <div class="md:col-span-12">
                                        <label class="block text-xs font-medium text-slate-600">Lookup-Liste (Slug)</label>
                                        <select :name="`fields[${idx}][pattern]`" x-model="f.pattern"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">— Liste waehlen —</option>
                                            @foreach(($lookupLists ?? []) as $ll)
                                                <option value="{{ $ll->slug }}">{{ $ll->name }} ({{ $ll->slug }})</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-xs text-slate-500">Findet alle Schluessel der Liste im OCR-Text. Bei mehreren Treffern gewinnt der laengste (spezifischste).</p>
                                    </div>
                                </template>
                                <div class="md:col-span-12">
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                        <input type="hidden" :name="`fields[${idx}][ki_fallback]`" value="0">
                                        <input type="checkbox" :name="`fields[${idx}][ki_fallback]`" value="1" x-model="f.ki_fallback" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <span x-show="f.extractor === 'ki'">— immer per KI extrahieren</span>
                                        <span x-show="f.extractor !== 'ki'">KI als Fallback, wenn Heuristik/Regex nichts findet</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <button type="button" @click="fields.push({key:'',label:'',type:'string',extractor:'heuristic:invoice_number',pattern:'',ki_fallback:false})"
                        class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Feld</button>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <a href="{{ route('admin.document_schemas.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Zurueck</a>
                <x-primary-button>Schema speichern</x-primary-button>
            </div>
        </x-card>
    </form>

    <x-card title="Bestehende Anhaenge neu indexieren" description="Liest die Felder fuer alle bereits hochgeladenen {{ $type }}-Dokumente neu aus dem OCR-Text. Bisher mit Feldern: {{ $sampleCount }}.">
        <form method="POST" action="{{ route('admin.document_schemas.reindex', $type) }}" onsubmit="return confirm('Alle {{ $type }}-Anhaenge neu indexieren?')">
            @csrf
            <x-secondary-button>Jetzt neu indexieren</x-secondary-button>
        </form>
        <p class="mt-2 text-xs text-slate-500">Laeuft synchron — bei vielen Dokumenten kann das dauern. Alternativ per Konsole: <code>php artisan documents:reindex --type="{{ $type }}"</code></p>
    </x-card>
</x-app-layout>
