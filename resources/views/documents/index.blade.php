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

    @php
        $docsForJs = $documents->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->original_name,
            'documentType' => $d->document_type,
            'previewable' => $d->isPdf() || $d->isImage(),
            'previewUrl' => route('documents.preview', $d),
            'detailUrl' => route('documents.show', $d),
            'startWorkflowUrl' => route('documents.start_workflow', $d),
            'sizeFormatted' => $d->sizeFormatted(),
            'indexedFields' => (object) ($d->indexed_fields ?? []),
            'tags' => $d->tags->map(fn ($t) => ['name' => $t->name, 'color' => $t->color])->values(),
        ])->values();
    @endphp

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden"
         x-data="documentsSplit(@js(request('doc')), @js($docsForJs), @js($availableWorkflows ?? []))"
         x-init="bootstrap()"
         @keydown.window="onKeydown($event)">
        @if($documents->isEmpty())
            <div class="p-6 lg:p-6">
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
            </div>
        @else
            @php
                $allTags = \App\Models\Tag::orderBy('name')->get();
                $allCases = \App\Models\DocumentCase::whereNull('closed_at')->orderBy('name')->get();
            @endphp

            {{-- 2-Spalten auf Desktop: links Liste (mit Bulk-Toolbar oben),
                 rechts Iframe-Preview. Mobile: nur die linke Spalte. --}}
            <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] lg:h-[78vh]">

                {{-- Liste --}}
                <div class="flex flex-col lg:border-r lg:border-slate-200 lg:overflow-hidden">
                    <form method="POST" action="{{ route('documents.bulk_action') }}" class="flex flex-col h-full"
                          x-data="{ selected: [], action: 'set_type' }">
                        @csrf
                        <div class="px-4 lg:px-6 pt-4 lg:pt-5">
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
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
                        </div>

                        <ul class="divide-y divide-slate-100 flex-1 lg:overflow-y-auto px-4 lg:px-6" x-ref="docList">
                            @foreach($documents as $i => $d)
                                <li class="py-2 flex items-start gap-3 -mx-2 px-2 rounded transition cursor-pointer hover:bg-slate-50"
                                    :class="selectedIdx === {{ $i }} ? 'bg-indigo-50' : ''"
                                    :data-doc-idx="{{ $i }}"
                                    @click="if (window.innerWidth >= 1024
                                                && !$event.metaKey && !$event.ctrlKey && !$event.shiftKey
                                                && $event.button === 0
                                                && !$event.target.closest('input, button, select, label, code, [target=_blank]')) {
                                        $event.preventDefault();
                                        select({{ $i }});
                                    }">
                                    <input type="checkbox" name="attachment_ids[]" value="{{ $d->id }}"
                                        @change="selected = Array.from(document.querySelectorAll('input[name=&quot;attachment_ids[]&quot;]:checked')).map(c => Number(c.value))"
                                        class="mt-2 rounded border-slate-300 text-indigo-600">
                                    <div class="flex-1 min-w-0">
                                        @include('documents._row', ['d' => $d, 'q' => $q])
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <div class="border-t border-slate-100 px-4 lg:px-6 py-3">{{ $documents->links() }}</div>
                    </form>
                </div>

                {{-- Preview (nur Desktop) --}}
                <div class="hidden lg:flex flex-col bg-slate-50 overflow-hidden">
                    <template x-if="!selected">
                        <div class="flex flex-col items-center justify-center h-full text-center px-8 text-sm text-slate-500">
                            <div class="grid h-14 w-14 place-items-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-200 mb-3">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg>
                            </div>
                            <strong class="text-slate-700">Klick ein Dokument links an</strong>
                            <p class="mt-1">PDF und Bilder werden hier direkt angezeigt — kein „Oeffnen" mehr noetig.</p>
                            <p class="mt-3 text-xs text-slate-400">Tastatur: <kbd class="rounded border border-slate-300 bg-white px-1 font-mono text-[10px]">↑</kbd> <kbd class="rounded border border-slate-300 bg-white px-1 font-mono text-[10px]">↓</kbd> blaettern.</p>
                        </div>
                    </template>

                    <template x-if="selected">
                        <div class="flex flex-col h-full">
                            {{-- Header-Zeile 1: Name + Position + Pfeile + Aktionen --}}
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="prev()" :disabled="selectedIdx === 0"
                                            class="grid h-7 w-7 place-items-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed"
                                            title="Vorheriges Dokument (Pfeil hoch)">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                                        </button>
                                        <button type="button" @click="next()" :disabled="selectedIdx === docs.length - 1"
                                            class="grid h-7 w-7 place-items-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed"
                                            title="Naechstes Dokument (Pfeil runter)">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                                        </button>
                                        <span class="text-[11px] text-slate-400 ms-1" x-text="(selectedIdx + 1) + ' / ' + docs.length"></span>
                                    </div>
                                    <span class="text-slate-300">·</span>
                                    <div class="text-sm font-medium text-slate-900 truncate" x-text="selected.name"></div>
                                </div>
                                <div class="flex items-center gap-3 text-xs whitespace-nowrap"
                                     x-data="{ wfOpen: false }" @click.outside="wfOpen = false">
                                    @if(($availableWorkflows ?? collect())->isNotEmpty() && auth()->user()->hasAnyPermission(['workflows.run', 'workflows.design']))
                                        <div class="relative">
                                            <button type="button" @click="wfOpen = !wfOpen"
                                                class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                Workflow starten
                                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 12 12"><path d="M3 4.5 6 8l3-3.5"/></svg>
                                            </button>
                                            <div x-show="wfOpen" x-transition class="absolute right-0 z-20 mt-1 w-64 rounded-lg bg-white shadow-lg ring-1 ring-slate-200" style="display:none;">
                                                <div class="border-b border-slate-100 px-3 py-1.5 text-[11px] font-semibold uppercase text-slate-500">Aktive Workflows</div>
                                                <ul class="max-h-64 overflow-y-auto py-1">
                                                    <template x-for="wf in workflows" :key="wf.id">
                                                        <li>
                                                            <form method="POST" :action="selected.startWorkflowUrl">
                                                                @csrf
                                                                <input type="hidden" name="workflow_id" :value="wf.id">
                                                                <button type="submit" class="block w-full text-left px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50" x-text="wf.name"></button>
                                                            </form>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </div>
                                    @endif
                                    <a :href="selected.detailUrl" class="text-indigo-600 hover:text-indigo-500">Details</a>
                                    <span class="text-slate-300">·</span>
                                    <a :href="selected.previewUrl" target="_blank" class="text-slate-600 hover:text-slate-900">Im Tab</a>
                                </div>
                            </div>

                            {{-- Header-Zeile 2: Indexfelder + Tags. Nur wenn welche da sind. --}}
                            <template x-if="hasMeta">
                                <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 bg-slate-50 px-4 py-2">
                                    <template x-for="(value, key) in indexedFieldsForDisplay" :key="key">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-200 px-1.5 py-0.5 text-[11px]">
                                            <span class="font-mono text-slate-500" x-text="key + ':'"></span>
                                            <span class="font-medium text-slate-800" x-text="value"></span>
                                        </span>
                                    </template>
                                    <template x-for="tag in selected.tags" :key="tag.name">
                                        <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                                              :style="`background:${tag.color}22; color:${tag.color};`">
                                            <span class="inline-block h-1.5 w-1.5 rounded-full" :style="`background:${tag.color}`"></span>
                                            <span x-text="tag.name"></span>
                                        </span>
                                    </template>
                                </div>
                            </template>

                            {{-- Preview-Body --}}
                            <template x-if="selected.previewable">
                                <iframe :src="selected.previewUrl + '#toolbar=1'" :title="selected.name" class="flex-1 w-full bg-white"></iframe>
                            </template>
                            <template x-if="!selected.previewable">
                                <div class="flex flex-col items-center justify-center flex-1 px-8 text-center text-sm text-slate-600 bg-white">
                                    <strong>Dieser Dateityp wird nicht direkt angezeigt.</strong>
                                    <p class="mt-1 text-slate-500">Oeffne <a :href="selected.detailUrl" class="text-indigo-600 hover:text-indigo-500">die Detail-Seite</a> oder lade die Datei herunter.</p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        @endif
    </div>

    <script>
        function documentsSplit(initialDocId, docs, workflows) {
            return {
                docs: docs || [],
                workflows: workflows || [],
                selectedIdx: null,
                get selected() {
                    return this.selectedIdx !== null ? this.docs[this.selectedIdx] : null;
                },
                // Indexfelder fuer den schmalen Header oben — max 4 sichtbar,
                // damit nichts in 2 Zeilen umbricht.
                get indexedFieldsForDisplay() {
                    if (!this.selected) return {};
                    const all = this.selected.indexedFields || {};
                    const out = {};
                    let count = 0;
                    for (const k of Object.keys(all)) {
                        if (k.startsWith('_')) continue; // intern: _zugferd etc.
                        out[k] = String(all[k]).slice(0, 30);
                        if (++count >= 4) break;
                    }
                    return out;
                },
                get hasMeta() {
                    if (!this.selected) return false;
                    const hasFields = Object.keys(this.indexedFieldsForDisplay).length > 0;
                    const hasTags = Array.isArray(this.selected.tags) && this.selected.tags.length > 0;
                    return hasFields || hasTags;
                },
                bootstrap() {
                    if (window.innerWidth < 1024) return;
                    if (!initialDocId) return;
                    const idx = this.docs.findIndex(d => String(d.id) === String(initialDocId));
                    if (idx >= 0) this.select(idx);
                },
                select(idx) {
                    if (idx < 0 || idx >= this.docs.length) return;
                    this.selectedIdx = idx;
                    const doc = this.docs[idx];
                    const u = new URL(window.location.href);
                    u.searchParams.set('doc', doc.id);
                    window.history.replaceState(null, '', u);
                    // Selektierte Zeile in den Viewport scrollen falls sie raus ist
                    this.$nextTick(() => {
                        const row = this.$refs.docList?.querySelector(`[data-doc-idx="${idx}"]`);
                        if (row && row.scrollIntoView) {
                            row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        }
                    });
                },
                prev() { if (this.selectedIdx !== null && this.selectedIdx > 0) this.select(this.selectedIdx - 1); },
                next() { if (this.selectedIdx !== null && this.selectedIdx < this.docs.length - 1) this.select(this.selectedIdx + 1); },
                onKeydown(e) {
                    if (window.innerWidth < 1024) return;
                    if (this.selectedIdx === null) return;
                    const t = document.activeElement?.tagName;
                    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(t)) return;
                    if (e.metaKey || e.ctrlKey || e.altKey) return;
                    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                        e.preventDefault(); this.next();
                    } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                        e.preventDefault(); this.prev();
                    }
                },
            };
        }
    </script>
</x-app-layout>
