<x-app-layout :full="true">
    {{-- Mobile-Warnung: Designer braucht Drag & Drop und ist auf Touch-Geraeten kaum
         bedienbar. Auf Geraeten kleiner als lg blenden wir die App komplett aus und
         zeigen einen Hinweis statt einem kaputten Canvas. --}}
    <div class="lg:hidden flex flex-col items-center justify-center min-h-[60vh] px-6 text-center">
        <div class="grid h-14 w-14 place-items-center rounded-full bg-amber-100 text-amber-700">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
        </div>
        <h2 class="mt-4 text-base font-semibold text-slate-900">Designer braucht einen Desktop</h2>
        <p class="mt-2 max-w-sm text-sm text-slate-500">
            Drag-and-Drop von Knoten und das Ziehen von Verbindungen funktioniert auf
            Touch-Geraeten nicht zuverlaessig. Oeffne diesen Workflow am Rechner.
        </p>
        <a href="{{ route('workflows.index') }}" class="mt-4 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">&larr; Zurueck zu Workflows</a>
    </div>

    <div x-data="designerApp()" x-init="boot()" class="hidden lg:flex h-[calc(100vh-4rem)] flex-col">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-6 py-3">
            <a href="{{ route('workflows.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Workflows</a>
            <div class="h-6 w-px bg-slate-200"></div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-base font-semibold text-slate-900">{{ $workflow->name }}</h1>
                    @switch($workflow->status)
                        @case('draft')<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Entwurf</span>@break
                        @case('active')<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktiv</span>@break
                        @case('archived')<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Archiviert</span>@break
                    @endswitch
                </div>
                <div class="text-xs text-slate-500">
                    <span>Trigger: <strong x-text="triggerLabel"></strong></span>
                    <span x-show="currentVersion"> · Aktuelle Version: v<span x-text="currentVersion"></span></span>
                    <span x-show="!currentVersion">· noch nicht gespeichert</span>
                </div>
            </div>
            <div class="ms-auto flex items-center gap-2">
                <button type="button" @click="aiOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                    KI-Entwurf
                </button>
                <input type="text" x-model="changeSummary" placeholder="Beschreibung der Aenderung (optional)"
                    class="w-64 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <a href="{{ route('workflows.versions', $workflow) }}" class="text-sm text-slate-600 hover:text-slate-900">Versionen</a>
                <a href="{{ route('workflows.process_doc', $workflow) }}" target="_blank" class="text-sm text-slate-600 hover:text-slate-900" title="Prozessbeschreibung als PDF herunterladen">PDF-Doku</a>
                <button type="button" @click="save()" :disabled="saving"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                    <span x-show="!saving">Speichern</span>
                    <span x-show="saving">Speichere…</span>
                </button>
            </div>
        </div>

        {{-- KI-Modal --}}
        <div x-show="aiOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" style="display:none;" @keydown.escape.window="aiOpen=false">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl ring-1 ring-slate-200" @click.outside="aiOpen=false">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-900">✨ Workflow per KI entwerfen</h2>
                    <p class="mt-1 text-xs text-slate-500">Beschreibe in eigenen Worten, was der Workflow tun soll. Die KI baut Formularfelder, Knoten und Verbindungen als Entwurf — du kannst danach beliebig nachbearbeiten und musst manuell speichern.</p>
                </div>
                <div class="p-6 space-y-3">
                    <textarea x-model="aiDesc" rows="8"
                        placeholder="z. B.: Bestellantrag. Mitarbeiter fuellt Formular mit Kostenstelle, Beschreibung und Betrag aus. Bei IT-Kostenstelle (1000) geht es zur IT-Leitung, bei Office (2000) an den Office-Manager. Karenzzeit 3 Tage, eskaliert an Admin-Rolle. Nach Genehmigung wird via HTTP-POST ein Ticket im Jira (URL beispiel.atlassian.net/rest/api/3/issue, Bearer-Token) erstellt und der Antragsteller per Mail informiert. Bei Ablehnung Mail an den Antragsteller mit dem Grund."
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"></textarea>
                    <p x-show="aiError" x-text="aiError" class="text-sm text-rose-700" style="display:none;"></p>
                </div>
                <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-3 rounded-b-2xl">
                    <p class="text-xs text-slate-500">Achtung: ersetzt den aktuellen Canvas. Gespeicherte Versionen bleiben unberuehrt.</p>
                    <div class="flex gap-2">
                        <button type="button" @click="aiOpen=false" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Abbrechen</button>
                        <button type="button" @click="generateFromAI('{{ route('admin.ai.suggest_workflow') }}')" :disabled="aiBusy || !aiDesc.trim()"
                            class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-500 disabled:opacity-50">
                            <span x-show="!aiBusy">Entwurf generieren</span>
                            <span x-show="aiBusy">Generiere…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex items-center gap-1 border-b border-slate-200 bg-slate-50 px-6">
            <button type="button" @click="tab='canvas'"
                :class="tab==='canvas' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-600 hover:text-slate-900'"
                class="border-b-2 px-3 py-2 text-sm font-medium">Workflow-Canvas</button>
            <button type="button" @click="tab='form'" x-show="triggerType==='form'"
                :class="tab==='form' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-600 hover:text-slate-900'"
                class="border-b-2 px-3 py-2 text-sm font-medium">Formularfelder</button>
            <div x-show="saveMessage" x-transition class="ms-auto text-xs"
                :class="saveError ? 'text-rose-600' : 'text-emerald-700'" x-text="saveMessage"></div>
        </div>

        {{-- Canvas tab --}}
        <div x-show="tab==='canvas'" class="flex flex-1 overflow-hidden">
            {{-- Palette --}}
            <aside class="w-64 shrink-0 overflow-y-auto border-r border-slate-200 bg-white">
                <div class="p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Knoten</h3>
                    <p class="mt-1 text-xs text-slate-500">In die Flaeche ziehen.</p>
                    <div class="mt-3 space-y-4">
                        <template x-for="group in nodePaletteGrouped" :key="group.category">
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5" x-text="group.category"></div>
                                <div class="space-y-1.5">
                                    <template x-for="palette in group.items" :key="palette.type">
                                        <div draggable="true"
                                            @dragstart="onDragStart($event, palette.type)"
                                            :title="palette.help"
                                            class="group cursor-grab rounded-lg border border-slate-200 bg-white px-2.5 py-2 hover:border-indigo-300 hover:bg-indigo-50 hover:shadow-sm transition">
                                            <div class="flex items-center gap-2">
                                                <div class="grid h-7 w-7 shrink-0 place-items-center rounded text-xs font-semibold text-white"
                                                    :style="`background-color: ${palette.color}`" x-text="palette.shortLabel"></div>
                                                <div class="text-sm font-medium text-slate-900 leading-tight" x-text="palette.label"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </aside>

            {{-- Canvas --}}
            <div class="relative flex-1 bg-slate-50">
                <div id="drawflow" class="absolute inset-0"
                    @drop="onDrop($event)" @dragover.prevent></div>
                <div class="pointer-events-none absolute right-3 bottom-3 flex gap-2">
                    <button type="button" @click="zoom('in')" class="pointer-events-auto rounded-md bg-white p-2 shadow ring-1 ring-slate-200 hover:bg-slate-50" title="Zoom +">+</button>
                    <button type="button" @click="zoom('out')" class="pointer-events-auto rounded-md bg-white p-2 shadow ring-1 ring-slate-200 hover:bg-slate-50" title="Zoom -">−</button>
                    <button type="button" @click="zoom('reset')" class="pointer-events-auto rounded-md bg-white p-2 shadow ring-1 ring-slate-200 hover:bg-slate-50" title="Reset">⊙</button>
                </div>
            </div>

            {{-- Settings panel --}}
            <aside class="w-96 shrink-0 overflow-y-auto border-l border-slate-200 bg-white">
                <div class="p-4">
                    <template x-if="!selectedNode">
                        <div class="text-sm text-slate-500">
                            <p>Waehle einen Knoten aus, um seine Einstellungen zu bearbeiten.</p>
                            <p class="mt-2 text-xs">Verbinde Knoten, indem du vom kleinen Punkt rechts (Ausgang) zum Punkt links (Eingang) des naechsten Knotens ziehst.</p>
                        </div>
                    </template>
                    <template x-if="selectedNode">
                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900" x-text="paletteFor(selectedNode.type).label"></h3>
                                <button type="button" @click="deleteSelected()" class="text-xs text-rose-600 hover:text-rose-500">Loeschen</button>
                            </div>
                            <p class="mt-1 text-xs text-slate-500" x-text="paletteFor(selectedNode.type).help"></p>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">Anzeige-Name</label>
                                    <input type="text" x-model="selectedNode.data.label" @input="updateNodeLabel()"
                                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                {{-- Approval settings --}}
                                <template x-if="selectedNode.type==='approval'">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Empfaenger-Typ</label>
                                            <select x-model="selectedNode.data.recipient_type"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="supervisor_of_initiator">Vorgesetzter des Antragstellers</option>
                                                <option value="supervisor_of_previous">Vorgesetzter des vorherigen Bearbeiters</option>
                                                <option value="subject_user">Asset-Inhaber / Subject-User</option>
                                                <option value="supervisor_of_subject">Vorgesetzter des Subject-Users</option>
                                                <option value="role">Mitglieder einer Rolle</option>
                                                <option value="user">Konkrete Person</option>
                                                <option value="list_lookup">Aus Liste nachschlagen</option>
                                            </select>
                                        </div>
                                        <template x-if="selectedNode.data.recipient_type==='list_lookup'">
                                            <div class="space-y-2 rounded-md bg-slate-50 p-2">
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-600">Liste</label>
                                                    <select x-model.number="selectedNode.data.list_id"
                                                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">— Liste waehlen —</option>
                                                        <template x-for="l in directory.lists" :key="l.id">
                                                            <option :value="l.id" x-text="l.name + (l.has_responsible ? '' : ' (keine Verantwortlich-Spalte!)')"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-600">Schluessel aus Feld</label>
                                                    <input type="text" x-model="selectedNode.data.lookup_source"
                                                        placeholder="z. B. kostenstelle oder doc.indexed_fields.kostenstelle"
                                                        list="lookup-source-options"
                                                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <datalist id="lookup-source-options">
                                                        <template x-for="f in formSchema" :key="f.key">
                                                            <option :value="f.key" x-text="f.label || f.key"></option>
                                                        </template>
                                                        <option value="doc.indexed_fields.kostenstelle">doc.indexed_fields.kostenstelle</option>
                                                        <option value="doc.indexed_fields.rechnungsnummer">doc.indexed_fields.rechnungsnummer</option>
                                                        <option value="doc.document_type">doc.document_type</option>
                                                    </datalist>
                                                    <p class="mt-1 text-xs text-slate-500">Direkter Formularfeld-Name (z. B. <code>kostenstelle</code>) oder Punktnotation fuer Dokument-Felder (z. B. <code>doc.indexed_fields.kostenstelle</code>).</p>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-xs font-medium text-slate-600">Fallback-Rolle</label>
                                                        <select x-model.number="selectedNode.data.fallback_role_id"
                                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="">— keine —</option>
                                                            <template x-for="r in directory.roles" :key="r.id">
                                                                <option :value="r.id" x-text="r.name"></option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-slate-600">Fallback-Benutzer</label>
                                                        <select x-model.number="selectedNode.data.fallback_user_id"
                                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="">— keiner —</option>
                                                            <template x-for="u in directory.users" :key="u.id">
                                                                <option :value="u.id" x-text="u.name"></option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-slate-500">Wenn der Lookup nichts findet (z. B. keine Kostenstelle im Dokument), geht die Aufgabe an Fallback-Benutzer, sonst Fallback-Rolle.</p>
                                            </div>
                                        </template>
                                        <template x-if="selectedNode.data.recipient_type==='role'">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Rolle</label>
                                                <select x-model.number="selectedNode.data.recipient_role_id"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">— waehlen —</option>
                                                    <template x-for="r in directory.roles" :key="r.id">
                                                        <option :value="r.id" x-text="r.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </template>
                                        <template x-if="selectedNode.data.recipient_type==='user'">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Benutzer</label>
                                                <select x-model.number="selectedNode.data.recipient_user_id"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">— waehlen —</option>
                                                    <template x-for="u in directory.users" :key="u.id">
                                                        <option :value="u.id" x-text="`${u.name} (${u.email})`"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </template>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Karenzzeit</label>
                                                <input type="number" min="0" x-model.number="selectedNode.data.grace_value"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Einheit</label>
                                                <select x-model="selectedNode.data.grace_unit"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="hours">Stunden</option>
                                                    <option value="days">Tage</option>
                                                    <option value="months">Monate</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Bei Ueberschreitung eskalieren an</label>
                                            <select x-model="selectedNode.data.escalation_type"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="none">Nicht eskalieren</option>
                                                <option value="supervisor_of_current">Vorgesetzten des Empfaengers</option>
                                                <option value="role">Mitglieder einer Rolle</option>
                                                <option value="list_lookup">Aus Liste (Eskalations-Spalte)</option>
                                            </select>
                                        </div>
                                        <template x-if="selectedNode.data.escalation_type==='role'">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Eskalations-Rolle</label>
                                                <select x-model.number="selectedNode.data.escalation_role_id"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">— waehlen —</option>
                                                    <template x-for="r in directory.roles" :key="r.id">
                                                        <option :value="r.id" x-text="r.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </template>
                                        <label class="flex items-center gap-2 text-xs text-slate-700">
                                            <input type="checkbox" x-model="selectedNode.data.allow_forward" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            Empfaenger darf an dritte Person weiterleiten
                                        </label>

                                        <div class="rounded-md bg-slate-50 p-2 space-y-2">
                                            <label class="block text-xs font-medium text-slate-600">Quorum (nur bei Rolle als Empfaenger)</label>
                                            <select x-model="selectedNode.data.quorum_mode"
                                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="single">Einer reicht (Standard)</option>
                                                <option value="all">Alle muessen zustimmen (Vier-Augen / Sechs-Augen)</option>
                                                <option value="n_of_m">N von M (Mehrheits-Quorum)</option>
                                            </select>
                                            <template x-if="selectedNode.data.quorum_mode === 'n_of_m'">
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-600">Mindest-Zustimmungen</label>
                                                    <input type="number" min="1" x-model.number="selectedNode.data.quorum_min" class="mt-1 block w-32 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                            </template>
                                            <p class="text-xs text-slate-500" x-show="selectedNode.data.quorum_mode !== 'single' && selectedNode.data.recipient_type !== 'role'">
                                                Quorum hat nur Wirkung, wenn der Empfaenger eine <strong>Rolle</strong> ist (mehrere Personen).
                                            </p>
                                        </div>

                                        <div class="rounded-md bg-slate-50 p-2 space-y-1">
                                            <label class="flex items-center gap-2 text-xs text-slate-700">
                                                <input type="checkbox" x-model="selectedNode.data.require_comment_on_approval" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                Kommentar bei Genehmigung verpflichtend
                                            </label>
                                            <label class="flex items-center gap-2 text-xs text-slate-700">
                                                <input type="checkbox" x-model="selectedNode.data.require_comment_on_rejection" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                Begruendung bei Ablehnung verpflichtend
                                            </label>
                                        </div>
                                        <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Ausgaenge: <strong>Genehmigt</strong> / <strong>Abgelehnt</strong> <span x-show="selectedNode.data.allow_forward">/ <strong>Weitergeleitet</strong></span></p>

                                        {{-- Parallele Genehmigung: nur sinnvoll wenn Empfaenger eine Rolle
                                             oder eine Liste ist (mehrere Personen) — bei Einzeluser wird die
                                             Einstellung im Engine ignoriert. --}}
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                                            <div>
                                                <h4 class="text-xs font-semibold text-slate-700">Parallele Genehmigung</h4>
                                                <p class="text-[11px] text-slate-500">
                                                    Nur sinnvoll wenn der Empfaenger eine Rolle oder eine Liste ist
                                                    (mehrere Personen werden parallel beteiligt). Bei Einzeluser hat das
                                                    keinen Effekt.
                                                </p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600 mb-1">Modus</label>
                                                <select x-model="selectedNode.data.quorum_mode"
                                                    x-init="if (!selectedNode.data.quorum_mode) selectedNode.data.quorum_mode = 'single'"
                                                    class="block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="single">Einzeln (Default) — eine Person genehmigt</option>
                                                    <option value="all">Alle muessen genehmigen — eine Ablehnung bricht ab</option>
                                                    <option value="n_of_m">N von M genehmigen — ab Schwelle wird durchgewunken</option>
                                                </select>
                                            </div>
                                            <template x-if="selectedNode.data.quorum_mode === 'n_of_m'">
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-600 mb-1">Schwelle (mindestens N Approvals)</label>
                                                    <input type="number" min="1" max="50" x-model.number="selectedNode.data.quorum_min"
                                                        class="block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <p class="text-[11px] text-slate-500 mt-1">
                                                        Sobald N Mitglieder genehmigen, geht der Workflow weiter. Wenn nicht
                                                        mehr genug Stimmen uebrig sind, wird automatisch abgelehnt.
                                                    </p>
                                                </div>
                                            </template>
                                        </div>

                                        {{-- Zusatzfelder beim Genehmigen --}}
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                            <div class="flex items-baseline justify-between mb-2">
                                                <div>
                                                    <h4 class="text-xs font-semibold text-slate-700">Zusatzfelder beim Entscheiden</h4>
                                                    <p class="text-[11px] text-slate-500">
                                                        Werden im Aufgaben-Formular angezeigt. Werte mit Ziel <em>Dokument-Indexfeld</em>
                                                        werden automatisch ins Schema des jeweiligen Doku-Typs uebernommen
                                                        (falls noch nicht vorhanden) — danach sind sie in der Dokumenten-Suche
                                                        als Filter und im Detail-Editor verfuegbar.
                                                    </p>
                                                </div>
                                            </div>
                                            <template x-if="!selectedNode.data.extra_fields">
                                                <span x-init="selectedNode.data.extra_fields = []"></span>
                                            </template>
                                            <div class="space-y-2">
                                                <template x-for="(f, fi) in selectedNode.data.extra_fields" :key="fi">
                                                    <div class="rounded-md border border-slate-200 bg-white p-2 space-y-1.5">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-[11px] uppercase tracking-wider text-slate-400">Feld <span x-text="fi+1"></span></span>
                                                            <button type="button" @click="selectedNode.data.extra_fields.splice(fi,1)" class="text-xs text-rose-600 hover:text-rose-500">entfernen</button>
                                                        </div>
                                                        <div class="grid grid-cols-3 gap-1.5">
                                                            <input type="text" x-model="f.label" placeholder="Bezeichnung"
                                                                class="rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            <input type="text" x-model="f.key"
                                                                @input="f.key = f.key.toString().toLowerCase().replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'')"
                                                                placeholder="key (a-z, _)"
                                                                class="rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                            <select x-model="f.type" class="rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                <option value="text">Text</option>
                                                                <option value="textarea">Textarea</option>
                                                                <option value="number">Zahl</option>
                                                                <option value="date">Datum</option>
                                                                <option value="select">Auswahl</option>
                                                                <option value="checkbox">Ja/Nein</option>
                                                            </select>
                                                        </div>
                                                        <template x-if="f.type === 'select'">
                                                            <textarea x-model="f.options_raw"
                                                                @input="f.options = (f.options_raw||'').split('\n').map(s => s.trim()).filter(Boolean)"
                                                                rows="2" placeholder="Optionen je Zeile"
                                                                class="block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                                        </template>
                                                        <div class="flex items-center justify-between gap-2 text-[11px]">
                                                            <label class="inline-flex items-center gap-1.5 text-slate-700">
                                                                <input type="checkbox" x-model="f.required" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                                Pflichtfeld
                                                            </label>
                                                            <label class="inline-flex items-center gap-1.5 text-slate-700">
                                                                Speichern in
                                                                <select x-model="f.target" class="rounded border-slate-300 text-[11px] shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                                    <option value="doc">Dokument-Indexfeld</option>
                                                                    <option value="instance">Workflow-Daten</option>
                                                                </select>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </template>
                                                <button type="button"
                                                    @click="selectedNode.data.extra_fields.push({
                                                        key: 'feld_' + ((selectedNode.data.extra_fields?.length || 0) + 1),
                                                        label: '',
                                                        type: 'text',
                                                        required: false,
                                                        target: 'doc',
                                                        options: [],
                                                        options_raw: '',
                                                    })"
                                                    class="w-full rounded-md border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Zusatzfeld</button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Condition settings --}}
                                <template x-if="selectedNode.type==='condition'">
                                    <div class="space-y-3">
                                        <p class="text-xs text-slate-500">Per Drag-and-Drop am Griff sortieren. Trifft keine Bedingung zu, wird der <strong>Else</strong>-Ausgang genutzt.</p>
                                        <div class="space-y-3"
                                             x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                                             x-sort="selectedNode.data.branches.splice($event.newIndex, 0, selectedNode.data.branches.splice($event.oldIndex, 1)[0])">
                                        <template x-for="(branch, idx) in selectedNode.data.branches" :key="idx">
                                            <div class="rounded-lg border border-slate-200 p-3 bg-white" x-sort:item="idx">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <span class="drag-handle cursor-grab select-none text-slate-400 hover:text-slate-600">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><path d="M6 2.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm7-11a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
                                                        </span>
                                                        <span class="text-xs font-semibold text-slate-700">Zweig <span x-text="idx+1"></span> — Ausgang <span x-text="idx+1"></span></span>
                                                    </div>
                                                    <button type="button" @click="removeBranch(idx)" class="text-xs text-rose-600 hover:text-rose-500">entfernen</button>
                                                </div>
                                                <div class="mt-2 grid grid-cols-1 gap-2">
                                                    <input type="text" x-model="branch.label" placeholder="Bezeichnung (z. B. IT-Bestellung)"
                                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <div class="grid grid-cols-3 gap-2">
                                                        <input type="text" x-model="branch.field" list="condition-field-options"
                                                            placeholder="Feld (z. B. kostenstelle oder doc.indexed_fields.kostenstelle)"
                                                            class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                        <datalist id="condition-field-options">
                                                            <template x-for="f in formSchema" :key="f.key">
                                                                <option :value="f.key" x-text="f.label || f.key"></option>
                                                            </template>
                                                            <option value="doc.document_type">doc.document_type</option>
                                                            <option value="doc.indexed_fields.kostenstelle">doc.indexed_fields.kostenstelle</option>
                                                            <option value="doc.indexed_fields.rechnungsnummer">doc.indexed_fields.rechnungsnummer</option>
                                                            <option value="doc.indexed_fields.betrag_brutto">doc.indexed_fields.betrag_brutto</option>
                                                        </datalist>
                                                        <select x-model="branch.operator" class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="eq">ist gleich</option>
                                                            <option value="neq">ist ungleich</option>
                                                            <option value="contains">enthaelt</option>
                                                            <option value="gt">groesser</option>
                                                            <option value="gte">groesser/gleich</option>
                                                            <option value="lt">kleiner</option>
                                                            <option value="lte">kleiner/gleich</option>
                                                            <option value="checked">ist angekreuzt</option>
                                                            <option value="unchecked">ist nicht angekreuzt</option>
                                                            <option value="empty">ist leer</option>
                                                            <option value="not_empty">ist nicht leer</option>
                                                        </select>
                                                        <input type="text" x-model="branch.value" placeholder="Wert"
                                                            x-show="!['checked','unchecked','empty','not_empty'].includes(branch.operator)"
                                                            class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        </div>
                                        <button type="button" @click="addBranch()" class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Verzweigung hinzufuegen</button>
                                        <div class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">
                                            Letzter Ausgang: <strong>Sonst (else)</strong>
                                        </div>
                                    </div>
                                </template>

                                {{-- Notify settings --}}
                                <template x-if="selectedNode.type==='notify'">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Empfaenger</label>
                                            <select x-model="selectedNode.data.recipient_type"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="initiator">Antragsteller</option>
                                                <option value="subject_user">Asset-Inhaber / Subject-User</option>
                                                <option value="supervisor_of_initiator">Vorgesetzter des Antragstellers</option>
                                                <option value="supervisor_of_subject">Vorgesetzter des Subject-Users</option>
                                                <option value="role">Mitglieder einer Rolle</option>
                                                <option value="user">Konkrete Person</option>
                                                <option value="list_lookup">Aus Liste nachschlagen</option>
                                            </select>
                                        </div>
                                        <template x-if="selectedNode.data.recipient_type==='role'">
                                            <select x-model.number="selectedNode.data.recipient_role_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">— Rolle waehlen —</option>
                                                <template x-for="r in directory.roles" :key="r.id"><option :value="r.id" x-text="r.name"></option></template>
                                            </select>
                                        </template>
                                        <template x-if="selectedNode.data.recipient_type==='user'">
                                            <select x-model.number="selectedNode.data.recipient_user_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">— Benutzer waehlen —</option>
                                                <template x-for="u in directory.users" :key="u.id"><option :value="u.id" x-text="`${u.name} (${u.email})`"></option></template>
                                            </select>
                                        </template>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Betreff</label>
                                            <input type="text" x-model="selectedNode.data.subject" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Nachricht</label>
                                            <textarea x-model="selectedNode.data.body" rows="5" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                            <p class="mt-1 text-xs text-slate-500">Platzhalter: <code>@{{ feld_key }}</code>, <code>@{{ initiator }}</code></p>
                                        </div>
                                    </div>
                                </template>

                                {{-- HTTP-Request settings --}}
                                <template x-if="selectedNode.type==='http'">
                                    <div class="space-y-3" x-data="{ aiDesc:'', aiBusy:false, aiError:'' }">
                                        <p class="text-xs text-slate-500">Body und Header sind frei anpassbar. Antwort-Felder koennen zurueck in den Workflow uebernommen werden.</p>

                                        <div class="rounded-lg border border-violet-200 bg-violet-50 p-3 space-y-2">
                                            <div class="text-xs font-semibold text-violet-800">+ KI-Import: curl, OpenAPI oder API-Doku</div>
                                            <p class="text-[11px] text-violet-700">Komplettes curl reinpasten, OpenAPI-Snippet, Markdown-Endpoint oder Freitext — KI fuellt URL, Methode, Header, Auth und Body-Template aus. Beispielwerte werden durch passende Form-Platzhalter ersetzt.</p>
                                            <textarea x-model="aiDesc" rows="6"
                                                placeholder='curl -X POST "https://itdoku.example.com/api/tickets" -H "Authorization: Bearer xxx" -H "Content-Type: application/json" -d {"subject":"Test","from_email":"max@example.com","description":"..."}'
                                                class="block w-full rounded-lg border-violet-300 bg-white text-xs shadow-sm focus:border-violet-500 focus:ring-violet-500 font-mono"></textarea>
                                            <div class="flex items-center gap-2">
                                                <button type="button" :disabled="aiBusy || !aiDesc.trim()"
                                                    @click="aiBusy = true; aiError = '';
                                                        fetch('{{ route('admin.ai.suggest_http') }}', {
                                                            method: 'POST',
                                                            headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json' },
                                                            body: JSON.stringify({
                                                                input: aiDesc,
                                                                purpose: 'Aufruf aus einem Workflow-HTTP-Knoten — Werte kommen aus dem Antragsteller-Kontext und Formular-Daten',
                                                                available_fields: (formSchema||[]).map(f=>f.key).concat(['initiator_email','initiator_name','workflow_name','instance_id','timestamp']),
                                                            }),
                                                        }).then(async r => {
                                                            const j = await r.json();
                                                            if (! r.ok) { aiError = j.error || 'Fehler'; return; }
                                                            const s = j.suggestion || {};
                                                            ['method','url','auth_type','auth_token','auth_username','auth_password','auth_header_name','body_type','body_template'].forEach(k => { if (s[k] !== undefined && s[k] !== '') selectedNode.data[k] = s[k]; });
                                                            if (Array.isArray(s.headers)) selectedNode.data.headers = s.headers.filter(h => h && h.key);
                                                            if (Array.isArray(s.body_form)) selectedNode.data.body_form = s.body_form;
                                                            if (Array.isArray(s.response_mapping) && s.response_mapping.length) selectedNode.data.response_mapping = s.response_mapping;
                                                            if (s.notes) aiError = 'Hinweis: ' + s.notes;
                                                        }).catch(e => aiError = e.message).finally(() => aiBusy = false)"
                                                    class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-violet-500 disabled:opacity-50">
                                                    <span x-show="!aiBusy">API uebernehmen</span><span x-show="aiBusy">analysiere &hellip;</span>
                                                </button>
                                                <span x-text="aiError" class="text-xs text-violet-700"></span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="col-span-1">
                                                <label class="block text-xs font-medium text-slate-600">Methode</label>
                                                <select x-model="selectedNode.data.method" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
                                                </select>
                                            </div>
                                            <div class="col-span-2">
                                                <label class="block text-xs font-medium text-slate-600">URL</label>
                                                <input type="text" x-model="selectedNode.data.url" placeholder="https://..."
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Authentifizierung</label>
                                            <select x-model="selectedNode.data.auth_type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="none">Keine</option>
                                                <option value="bearer">Bearer-Token</option>
                                                <option value="basic">Basic-Auth</option>
                                                <option value="api_key_header">API-Key Header</option>
                                            </select>
                                        </div>
                                        <template x-if="selectedNode.data.auth_type==='bearer'">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Token</label>
                                                <input type="password" x-model="selectedNode.data.auth_token" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            </div>
                                        </template>
                                        <template x-if="selectedNode.data.auth_type==='basic'">
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="text" x-model="selectedNode.data.auth_username" placeholder="Benutzer" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <input type="password" x-model="selectedNode.data.auth_password" placeholder="Passwort" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                        </template>
                                        <template x-if="selectedNode.data.auth_type==='api_key_header'">
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="text" x-model="selectedNode.data.auth_header_name" placeholder="Header" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                <input type="password" x-model="selectedNode.data.auth_token" placeholder="Key" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            </div>
                                        </template>

                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Header</label>
                                            <template x-for="(h, hi) in selectedNode.data.headers" :key="hi">
                                                <div class="mb-1 grid grid-cols-5 gap-1">
                                                    <input type="text" x-model="h.key" placeholder="Header" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <input type="text" x-model="h.value" placeholder="Wert" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <button type="button" @click="selectedNode.data.headers.splice(hi,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                                                </div>
                                            </template>
                                            <button type="button" @click="selectedNode.data.headers.push({key:'', value:''})" class="mt-1 w-full rounded-lg border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Header</button>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Body-Typ</label>
                                            <select x-model="selectedNode.data.body_type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="none">Kein Body</option>
                                                <option value="json">JSON (frei editierbar)</option>
                                                <option value="form">x-www-form-urlencoded</option>
                                                <option value="raw">Raw (Content-Type via Header)</option>
                                            </select>
                                        </div>

                                        <template x-if="['json','raw'].includes(selectedNode.data.body_type)">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Body</label>
                                                <textarea x-model="selectedNode.data.body_template" rows="8" spellcheck="false"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"></textarea>
                                                <p class="mt-1 text-xs text-slate-500">Platzhalter: <code>@{{ initiator_email }}</code>, <code>@{{ instance_id }}</code>, Formularfelder, <code>@{{ subject_user_email }}</code>, <code>@{{ doc.indexed_fields.kostenstelle }}</code> u. a. (Hilfe: <a href="{{ route('help.show', 'placeholders') }}" target="_blank" class="text-indigo-600 hover:text-indigo-500 underline">Platzhalter-Referenz</a>).</p>
                                            </div>
                                        </template>

                                        <template x-if="selectedNode.data.body_type==='form'">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Formular-Felder</label>
                                                <template x-for="(f, fi) in selectedNode.data.body_form" :key="fi">
                                                    <div class="mb-1 grid grid-cols-5 gap-1">
                                                        <input type="text" x-model="f.key" placeholder="key" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                        <input type="text" x-model="f.value" placeholder="Wert (mit @{{...}})" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                        <button type="button" @click="selectedNode.data.body_form.splice(fi,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                                                    </div>
                                                </template>
                                                <button type="button" @click="selectedNode.data.body_form.push({key:'', value:''})" class="mt-1 w-full rounded-lg border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Feld</button>
                                            </div>
                                        </template>

                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Response-Mapping</label>
                                            <p class="text-xs text-slate-500 mb-2">Schreibt Felder aus der JSON-Antwort in den Workflow-Kontext (Pfad in Punktnotation, leer = ganze Antwort).</p>
                                            <template x-for="(r, ri) in selectedNode.data.response_mapping" :key="ri">
                                                <div class="mb-1 grid grid-cols-5 gap-1">
                                                    <input type="text" x-model="r.path" placeholder="data.id" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <input type="text" x-model="r.save_as" placeholder="ticket_id" class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <button type="button" @click="selectedNode.data.response_mapping.splice(ri,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                                                </div>
                                            </template>
                                            <button type="button" @click="selectedNode.data.response_mapping.push({path:'', save_as:''})" class="mt-1 w-full rounded-lg border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Feld</button>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Timeout (Sek.)</label>
                                                <input type="number" min="1" max="120" x-model.number="selectedNode.data.timeout_seconds" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div class="flex items-end">
                                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                    <input type="checkbox" x-model="selectedNode.data.continue_on_error" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                    Bei Fehler weiterleiten (Ausgang Fehler)
                                                </label>
                                            </div>
                                        </div>
                                        <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Ausgaenge: <strong>OK</strong> / <strong>Fehler</strong></p>
                                    </div>
                                </template>

                                {{-- PDF-Render settings --}}
                                <template x-if="selectedNode.type==='pdf_render'">
                                    <div class="space-y-3">
                                        <p class="text-xs text-slate-500">Erzeugt aus einem HTML-Template ein PDF und haengt es als Attachment an die Workflow-Instanz. Hash wird gespeichert (revisionssicher).</p>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">HTML-Template</label>
                                            <textarea x-model="selectedNode.data.html_template" rows="10" spellcheck="false"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"></textarea>
                                            <p class="mt-1 text-xs text-slate-500">Platzhalter: <code>@{{ instance_id }}</code>, <code>@{{ workflow_name }}</code>, <code>@{{ initiator_name }}</code>, beliebige Formularfelder. CSS via <code>&lt;style&gt;</code>-Tag.</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Dateiname</label>
                                                <input type="text" x-model="selectedNode.data.filename" placeholder="beleg-INSTANZ.pdf"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Dokumenttyp (optional)</label>
                                                <input type="text" x-model="selectedNode.data.document_type" placeholder="z. B. Workflow-Beweis"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Bezeichnung (optional)</label>
                                            <input type="text" x-model="selectedNode.data.label" placeholder="z. B. Antrag bestaetigt"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Nach dem Erzeugen verfuegbar als <code>@{{ pdf.last_attachment_id }}</code>, <code>@{{ pdf.last_filename }}</code>, <code>@{{ pdf.last_hash }}</code>.</p>
                                    </div>
                                </template>

                                {{-- Start settings --}}
                                {{-- Wait-Knoten --}}
                                <template x-if="selectedNode.type==='wait'">
                                    <div class="space-y-3">
                                        <p class="text-xs text-slate-500">Pausiert den Workflow fuer den eingestellten Zeitraum. Der Scheduler (cron) weckt automatisch wieder auf.</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Dauer</label>
                                                <input type="number" min="0" x-model.number="selectedNode.data.wait_value"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Einheit</label>
                                                <select x-model="selectedNode.data.wait_unit"
                                                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="minutes">Minuten</option>
                                                    <option value="hours">Stunden</option>
                                                    <option value="days">Tage</option>
                                                    <option value="weeks">Wochen</option>
                                                    <option value="months">Monate</option>
                                                </select>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-500">Hinweis: aufwecken passiert beim naechsten <code>workflow:check-due</code>-Lauf (alle 5 Minuten).</p>
                                    </div>
                                </template>

                                {{-- Set-Field-Knoten --}}
                                <template x-if="selectedNode.type==='set_field'">
                                    <div class="space-y-3">
                                        <p class="text-xs text-slate-500">Setzt oder berechnet Felder in den Instanz-Daten. Werte koennen Platzhalter enthalten.</p>
                                        <template x-for="(a, ai) in selectedNode.data.assignments" :key="ai">
                                            <div class="mb-1 grid grid-cols-5 gap-1 items-end">
                                                <input type="text" x-model="a.field" placeholder="field_key"
                                                    class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                <input type="text" x-model="a.value" placeholder="Wert oder Platzhalter"
                                                    class="col-span-2 rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                <div class="flex items-center justify-between gap-1">
                                                    <label class="text-xs inline-flex items-center gap-1">
                                                        <input type="checkbox" x-model="a.as_number" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                        n
                                                    </label>
                                                    <button type="button" @click="selectedNode.data.assignments.splice(ai,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                                                </div>
                                            </div>
                                        </template>
                                        <button type="button" @click="selectedNode.data.assignments.push({field:'',value:'',as_number:false})" class="mt-1 w-full rounded-lg border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Zuweisung</button>
                                        <p class="text-xs text-slate-500">„n" = als Zahl interpretieren, sofern das Ergebnis numerisch ist.</p>
                                    </div>
                                </template>

                                {{-- Sub-Workflow --}}
                                <template x-if="selectedNode.type==='subworkflow'">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Ziel-Workflow</label>
                                            <select x-model.number="selectedNode.data.target_workflow_id"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">— Workflow waehlen —</option>
                                                <template x-for="w in directory.workflows" :key="w.id">
                                                    <option :value="w.id" x-text="w.name + ' (' + w.trigger_type + ')'"></option>
                                                </template>
                                            </select>
                                            <p class="text-[11px] text-slate-500 mt-1">Der Sub-Workflow muss <em>aktiv</em> sein.</p>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Eingabe-Mapping (Parent → Child)</label>
                                            <p class="text-[11px] text-slate-500 mb-1">Schluessel = Feld in der Child-Instance. Wert = Platzhalter wie <code>$.kostenstelle</code> oder Literal.</p>
                                            <template x-for="(m, mi) in selectedNode.data.input_mapping" :key="mi">
                                                <div class="mb-1 flex items-center gap-1">
                                                    <input type="text" x-model="m.target" placeholder="child_field" class="w-1/3 rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <input type="text" x-model="m.source" placeholder="$.parent_feld oder Literal" class="flex-1 rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <button type="button" @click="selectedNode.data.input_mapping.splice(mi,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                                                </div>
                                            </template>
                                            <button type="button" @click="selectedNode.data.input_mapping.push({target:'', source:''})" class="w-full rounded border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Mapping</button>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Ausgabe-Mapping (Child → Parent)</label>
                                            <p class="text-[11px] text-slate-500 mb-1">Wird nach Child-Abschluss in die Parent-Daten geschrieben.</p>
                                            <template x-for="(m, mi) in selectedNode.data.output_mapping" :key="mi">
                                                <div class="mb-1 flex items-center gap-1">
                                                    <input type="text" x-model="m.target" placeholder="parent_field" class="w-1/3 rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <input type="text" x-model="m.source" placeholder="child_feld" class="flex-1 rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                    <button type="button" @click="selectedNode.data.output_mapping.splice(mi,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                                                </div>
                                            </template>
                                            <button type="button" @click="selectedNode.data.output_mapping.push({target:'', source:''})" class="w-full rounded border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Mapping</button>
                                        </div>

                                        <label class="flex items-center gap-2 text-xs text-slate-700">
                                            <input type="checkbox" x-model="selectedNode.data.continue_on_failure" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            Bei Fehler trotzdem auf Ausgang OK weitermachen (sonst geht's auf Fehler).
                                        </label>
                                        <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Ausgaenge: <strong>OK</strong> / <strong>Fehler</strong></p>
                                    </div>
                                </template>

                                {{-- For-each-Loop --}}
                                <template x-if="selectedNode.type==='loop'">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Quell-Feld (Liste)</label>
                                            <input type="text" x-model="selectedNode.data.source_field" placeholder="z. B. items oder doc.indexed_fields.positions"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            <p class="text-[11px] text-slate-500 mt-1">Muss in der Instance auf einen Array zeigen. Pro Element wird ein Sub-Workflow gestartet.</p>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Sub-Workflow pro Iteration</label>
                                            <select x-model.number="selectedNode.data.target_workflow_id"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">— Workflow waehlen —</option>
                                                <template x-for="w in directory.workflows" :key="w.id">
                                                    <option :value="w.id" x-text="w.name"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Element-Feldname</label>
                                                <input type="text" x-model="selectedNode.data.item_field_name" placeholder="_item"
                                                    class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Max. Iterationen</label>
                                                <input type="number" x-model.number="selectedNode.data.max_iterations" min="1" max="1000"
                                                    class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                        </div>
                                        <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Alle Iterationen laufen <strong>parallel</strong>. Der Knoten geht erst weiter wenn alle fertig sind.</p>

                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                                            <div>
                                                <h4 class="text-xs font-semibold text-slate-700">Ergebnisse einsammeln (optional)</h4>
                                                <p class="text-[11px] text-slate-500">
                                                    Wenn ein Aggregator-Knoten nach diesem Loop kommt: hier den Pfad zu einem Wert in der Child-Instance angeben, der nach jeder Iteration in eine Liste in der Parent-Instance fliesst.
                                                </p>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-600">Quellfeld in Child</label>
                                                    <input type="text" x-model="selectedNode.data.collect_field" placeholder="z. B. betrag oder ergebnis"
                                                        class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-600">Zielliste in Parent</label>
                                                    <input type="text" x-model="selectedNode.data.collect_into" placeholder="_loop_results"
                                                        class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Switch-Knoten --}}
                                <template x-if="selectedNode.type==='switch_node'">
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Ausdruck / Feld-Pfad</label>
                                            <input type="text" x-model="selectedNode.data.expression" placeholder="z. B. kostenstelle oder doc.indexed_fields.lieferant"
                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            <p class="text-[11px] text-slate-500 mt-1">Wird gegen die Cases verglichen (numerisch wenn beide Zahlen, sonst String).</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600 mb-1">Cases</label>
                                            <p class="text-[11px] text-slate-500 mb-2">Erster Match gewinnt. Wenn keiner matched → Default-Ausgang.</p>
                                            <div class="space-y-1.5"
                                                 x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                                                 x-sort="selectedNode.data.cases.splice($event.newIndex, 0, selectedNode.data.cases.splice($event.oldIndex, 1)[0])">
                                                <template x-for="(c, ci) in selectedNode.data.cases" :key="ci">
                                                    <div class="grid grid-cols-12 gap-1 items-center rounded-md border border-slate-200 bg-white p-1.5" x-sort:item="ci">
                                                        <span class="drag-handle col-span-1 cursor-grab select-none text-xs text-slate-400 text-center">⋮⋮</span>
                                                        <input type="text" x-model="c.label" placeholder="Label" class="col-span-4 rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <span class="col-span-1 text-xs text-slate-400 text-center">=</span>
                                                        <input type="text" x-model="c.value" placeholder="Wert" class="col-span-5 rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                                        <button type="button" @click="selectedNode.data.cases.splice(ci,1)" class="col-span-1 text-xs text-rose-600 hover:text-rose-500">×</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <button type="button" @click="selectedNode.data.cases.push({label:'Fall '+(selectedNode.data.cases.length+1), value:''})" class="mt-1 w-full rounded border border-dashed border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">+ Case</button>
                                        </div>
                                        <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Ausgaenge: pro Case einer + <strong>Default</strong> am Ende.</p>
                                    </div>
                                </template>

                                {{-- Aggregator-Knoten --}}
                                <template x-if="selectedNode.type==='aggregator'">
                                    <div class="space-y-3">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Quellfeld (Liste)</label>
                                                <input type="text" x-model="selectedNode.data.source_field" placeholder="_loop_results"
                                                    class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Operation</label>
                                                <select x-model="selectedNode.data.operation" class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="sum">Summe</option>
                                                    <option value="avg">Durchschnitt</option>
                                                    <option value="min">Minimum</option>
                                                    <option value="max">Maximum</option>
                                                    <option value="count">Anzahl Elemente</option>
                                                    <option value="concat">Komma-Liste</option>
                                                    <option value="distinct">Eindeutige Werte (Array)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-600">Zielfeld</label>
                                            <input type="text" x-model="selectedNode.data.target_field" placeholder="aggregated"
                                                class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            <p class="text-[11px] text-slate-500 mt-1">Resultat wird in <code>instance.data.{target_field}</code> geschrieben.</p>
                                        </div>
                                        <template x-if="selectedNode.data.operation === 'concat'">
                                            <div>
                                                <label class="block text-xs font-medium text-slate-600">Trennzeichen</label>
                                                <input type="text" x-model="selectedNode.data.separator" placeholder=", "
                                                    class="mt-1 block w-full rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="selectedNode.type==='start'">
                                    <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Start-Knoten. Wird vom Workflow-Trigger automatisch ausgeloest.</p>
                                </template>

                                {{-- End settings --}}
                                <template x-if="selectedNode.type==='end'">
                                    <div class="space-y-2">
                                        <label class="block text-xs font-medium text-slate-600">Ergebnis</label>
                                        <select x-model="selectedNode.data.result"
                                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="completed">Erfolgreich abgeschlossen</option>
                                            <option value="rejected">Abgelehnt</option>
                                            <option value="cancelled">Abgebrochen</option>
                                        </select>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </aside>
        </div>

        {{-- Form-Schema tab --}}
        <div x-show="tab==='form'" class="flex-1 overflow-y-auto bg-slate-50 p-6">
            <div class="mx-auto max-w-3xl">
                <x-card title="Formularfelder" description="Per Drag-and-Drop am Griff anordnen. Diese Felder stehen in Bedingungen, Mails und Genehmigungen zur Verfuegung.">
                    <div class="space-y-3"
                         x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                         x-sort="formSchema.splice($event.newIndex, 0, formSchema.splice($event.oldIndex, 1)[0])">
                        <template x-for="(field, idx) in formSchema" :key="idx">
                            <div class="rounded-lg border border-slate-200 p-3 bg-white" x-sort:item="idx">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="drag-handle cursor-grab select-none text-slate-400 hover:text-slate-600" title="Zum Verschieben halten">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M6 2.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm7-11a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
                                        </span>
                                        <span class="text-xs font-semibold text-slate-700">Feld <span x-text="idx+1"></span></span>
                                    </div>
                                    <button type="button" @click="removeField(idx)" class="text-xs text-rose-600 hover:text-rose-500">entfernen</button>
                                </div>
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Bezeichnung</label>
                                        <input type="text" x-model="field.label" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Key (intern)</label>
                                        <input type="text" x-model="field.key" @input="field.key = slugify(field.key)" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Typ</label>
                                        <select x-model="field.type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="text">Text</option>
                                            <option value="textarea">Mehrzeiliger Text</option>
                                            <option value="number">Zahl</option>
                                            <option value="date">Datum</option>
                                            <option value="select">Dropdown</option>
                                            <option value="radio">Radio (Einzelauswahl)</option>
                                            <option value="checkbox">Checkbox (ja/nein)</option>
                                            <option value="file">Datei-Upload</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center gap-4">
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                        <input type="checkbox" x-model="field.required" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        Pflichtfeld
                                    </label>
                                </div>
                                <template x-if="['select','radio'].includes(field.type)">
                                    <div class="mt-2">
                                        <label class="block text-xs font-medium text-slate-600">Optionen (eine pro Zeile)</label>
                                        <textarea x-model="field._optionsText" @input="field.options = field._optionsText.split('\n').map(s => s.trim()).filter(Boolean)" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    </div>
                                </template>
                                <div class="mt-2 rounded-md bg-slate-50 p-2">
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Nur anzeigen wenn (optional)</label>
                                    <div class="grid grid-cols-3 gap-1">
                                        <select x-model="field.show_if_field" class="rounded border-slate-300 text-xs">
                                            <option value="">— immer zeigen —</option>
                                            <template x-for="f in formSchema.filter(f => f.key !== field.key)" :key="f.key">
                                                <option :value="f.key" x-text="f.label || f.key"></option>
                                            </template>
                                        </select>
                                        <select x-model="field.show_if_op" class="rounded border-slate-300 text-xs">
                                            <option value="eq">ist gleich</option>
                                            <option value="neq">ist ungleich</option>
                                            <option value="contains">enthaelt</option>
                                            <option value="checked">ist angekreuzt</option>
                                            <option value="unchecked">nicht angekreuzt</option>
                                            <option value="not_empty">nicht leer</option>
                                            <option value="empty">leer</option>
                                        </select>
                                        <input type="text" x-model="field.show_if_value" placeholder="Wert" class="rounded border-slate-300 text-xs">
                                    </div>
                                </div>
                            </div>
                        </template>
                        <button type="button" @click="addField()" class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Feld hinzufuegen</button>
                    </div>
                </x-card>
            </div>
        </div>

    </div>

    {{-- Payload as JSON --}}
    <script type="application/json" id="designer-payload">
        {!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @vite('resources/js/designer/index.js')
</x-app-layout>
