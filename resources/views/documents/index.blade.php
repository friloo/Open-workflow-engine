<x-app-layout>
    <x-slot name="header">
        @if($type === '__unclassified__')
            Dokumente · <span class="font-normal italic text-slate-500">Unklassifiziert</span>
        @elseif($type)
            Dokumente · <span class="font-normal text-slate-500">{{ $type }}</span>
        @else
            Dokumente
        @endif
    </x-slot>
    <x-slot name="subheader">
        @if($type)
            Treffer im aktuellen Archiv. Volltext und Indexfelder kombinierbar.
        @else
            Volltext-Suche ueber alle Archive. Fuer Indexfeld-Filter links ein Archiv waehlen.
        @endif
    </x-slot>

    @php
        $missing = collect($ocrAvailability)->reject(fn ($v) => $v)->keys()->all();
    @endphp
    @if(! empty($missing))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            OCR-Tools nicht installiert: <strong>{{ implode(', ', $missing) }}</strong>.
            Volltextsuche funktioniert nur fuer eingebettete PDF-Texte. Fuer Bild-PDFs
            poppler-utils (pdftotext, pdftoppm) und tesseract-ocr auf dem Server installieren.
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-slate-500">
            @if($type === '__unclassified__')
                {{ $unclassifiedCount }} Dokument(e) unklassifiziert
            @elseif($type)
                {{ $archiveCounts[$type] ?? 0 }} Dokument(e) im Archiv „{{ $type }}"
            @else
                {{ $totalDocs }} Dokument(e) insgesamt · {{ count($types) }} Archiv(e)
            @endif
        </div>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('documents.inbox') }}" class="text-indigo-600 hover:text-indigo-500">Postkorb</a>
            <a href="{{ route('documents.export_csv', request()->query()) }}" class="text-indigo-600 hover:text-indigo-500">Als CSV</a>
            <a href="{{ route('documents.bulk') }}"><x-primary-button type="button">Bulk-Upload</x-primary-button></a>
        </div>
    </div>

    <form method="GET" class="space-y-3">
        @if($type)
            <input type="hidden" name="type" value="{{ $type }}">
        @endif

        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="Volltext: Dateiname, OCR-Text, Label …"
                class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">OCR: alle</option>
                <option value="done" @selected($status==='done')>fertig</option>
                <option value="pending" @selected($status==='pending')>pending</option>
                <option value="failed" @selected($status==='failed')>fehlgeschlagen</option>
                <option value="skipped" @selected($status==='skipped')>uebersprungen</option>
            </select>
            <x-secondary-button type="submit">Suchen</x-secondary-button>
        </div>

        @if(! empty($schema))
            <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm font-semibold text-indigo-900">Indexfelder · {{ $type }}</div>
                        <div class="text-xs text-indigo-700/80">Mehrere Felder werden UND-verknuepft.</div>
                    </div>
                    <div class="text-xs">
                        @if(! empty($fieldFilters))
                            <a href="{{ route('documents.index', ['type' => $type, 'q' => $q]) }}"
                               class="text-rose-600 hover:text-rose-500">Filter zuruecksetzen</a>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($schema as $f)
                        @php
                            $current = $fieldFilters[$f['key']] ?? null;
                            $isRange = in_array($f['type'], ['date', 'currency', 'number'], true);
                        @endphp
                        @if($isRange)
                            @php
                                $from = is_array($current) ? ($current['from'] ?? '') : '';
                                $to = is_array($current) ? ($current['to'] ?? '') : '';
                                $inputType = $f['type'] === 'date' ? 'date' : 'number';
                                $step = $f['type'] === 'currency' ? '0.01' : 'any';
                            @endphp
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ $f['label'] }}</label>
                                <div class="flex gap-1">
                                    <input type="{{ $inputType }}" step="{{ $step }}"
                                           name="fields[{{ $f['key'] }}][from]" value="{{ $from }}" placeholder="von"
                                           class="block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <input type="{{ $inputType }}" step="{{ $step }}"
                                           name="fields[{{ $f['key'] }}][to]" value="{{ $to }}" placeholder="bis"
                                           class="block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        @else
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ $f['label'] }}</label>
                                <input type="text" name="fields[{{ $f['key'] }}]"
                                       value="{{ is_array($current) ? '' : ($current ?? '') }}"
                                       placeholder="enthaelt …"
                                       class="block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-3 flex justify-end">
                    <x-primary-button type="submit">Felder anwenden</x-primary-button>
                </div>
            </div>
        @elseif($type && $type !== '__unclassified__')
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                Fuer das Archiv <strong>{{ $type }}</strong> ist noch kein Index-Schema definiert.
                <a href="{{ route('admin.document_schemas.index') }}" class="text-indigo-600 hover:text-indigo-500">Felder anlegen →</a>
            </div>
        @endif
    </form>

    <x-card>
        @if($documents->isEmpty())
            @php
                if ($type === '__unclassified__') {
                    $emptyDescription = 'Im Archiv "Unklassifiziert" passen keine Treffer zu deinen Filtern.';
                } elseif ($type) {
                    $emptyDescription = 'Im Archiv "'.$type.'" passen keine Treffer zu deinen Filtern.';
                } else {
                    $emptyDescription = 'Lade PDFs und Bilder per Bulk-Upload hoch — sie sind danach per OCR-Volltext durchsuchbar.';
                }
            @endphp
            <x-empty-state icon="document"
                title="Keine Dokumente gefunden"
                :description="$emptyDescription">
                <a href="{{ route('documents.bulk') }}"><x-primary-button type="button">Bulk-Upload starten</x-primary-button></a>
                <a href="{{ route('help.show', 'documents') }}" class="text-sm text-slate-600 hover:text-slate-900">Anleitung lesen</a>
            </x-empty-state>
        @else
            @php
                $allTags = \App\Models\Tag::orderBy('name')->get();
                $allCases = \App\Models\DocumentCase::whereNull('closed_at')->orderBy('name')->get();
            @endphp
            <form method="POST" action="{{ route('documents.bulk_action') }}" x-data="{ selected: [], action: 'set_type' }">
                @csrf
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center gap-2 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox"
                                @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('input[name=&quot;attachment_ids[]&quot;]')).map(c => { c.checked = true; return Number(c.value); }) : (document.querySelectorAll('input[name=&quot;attachment_ids[]&quot;]').forEach(c => c.checked = false), [])"
                                class="rounded border-slate-300 text-indigo-600">
                            <span class="text-slate-700">Alle</span>
                        </label>
                        <span class="text-xs text-slate-500" x-text="selected.length === 0 ? 'nichts ausgewaehlt' : selected.length + ' ausgewaehlt'"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="action" name="action" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="set_type">Typ aendern</option>
                            <option value="add_tag">Tag setzen</option>
                            <option value="remove_tag">Tag entfernen</option>
                            <option value="add_case">Zu Akte hinzufuegen</option>
                            <option value="archive">Archivieren</option>
                        </select>
                        <template x-if="action === 'set_type'">
                            <select name="document_type" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— ohne Typ —</option>
                                @foreach(\App\Support\DocumentTypes::all() as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </template>
                        <template x-if="action === 'add_tag' || action === 'remove_tag'">
                            <select name="tag_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Tag waehlen —</option>
                                @foreach($allTags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </template>
                        <template x-if="action === 'add_case'">
                            <select name="case_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Akte waehlen —</option>
                                @foreach($allCases as $case)
                                    <option value="{{ $case->id }}">{{ $case->name }}</option>
                                @endforeach
                            </select>
                        </template>
                        <x-primary-button x-bind:disabled="selected.length === 0" onclick="return confirm('Aktion auf ausgewaehlte Dokumente anwenden?')">Anwenden</x-primary-button>
                    </div>
                </div>

                <ul class="divide-y divide-slate-100">
                    @foreach($documents as $d)
                        <li class="py-2 flex items-start gap-3">
                            <input type="checkbox" name="attachment_ids[]" value="{{ $d->id }}"
                                @change="selected = Array.from(document.querySelectorAll('input[name=&quot;attachment_ids[]&quot;]:checked')).map(c => Number(c.value))"
                                class="mt-2 rounded border-slate-300 text-indigo-600">
                            <div class="flex-1">
                                @include('documents._row', ['d' => $d, 'q' => $q])
                            </div>
                        </li>
                    @endforeach
                </ul>
            </form>
        @endif
        <div class="mt-4">{{ $documents->links() }}</div>
    </x-card>
</x-app-layout>
