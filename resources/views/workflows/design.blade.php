<x-app-layout :full="true">
    <div x-data="designerApp()" x-init="boot()" class="flex h-[calc(100vh-4rem)] flex-col">

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
                <input type="text" x-model="changeSummary" placeholder="Beschreibung der Aenderung (optional)"
                    class="w-72 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <a href="{{ route('workflows.versions', $workflow) }}" class="text-sm text-slate-600 hover:text-slate-900">Versionen</a>
                <button type="button" @click="save()" :disabled="saving"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                    <span x-show="!saving">Speichern</span>
                    <span x-show="saving">Speichere…</span>
                </button>
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
            <aside class="w-60 shrink-0 overflow-y-auto border-r border-slate-200 bg-white">
                <div class="p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Knoten</h3>
                    <p class="mt-1 text-xs text-slate-500">In die Flaeche ziehen.</p>
                    <div class="mt-3 space-y-2">
                        <template x-for="palette in nodePalette" :key="palette.type">
                            <div draggable="true"
                                @dragstart="onDragStart($event, palette.type)"
                                class="cursor-grab rounded-lg border border-slate-200 bg-white p-3 hover:border-indigo-300 hover:bg-indigo-50 transition">
                                <div class="flex items-center gap-2">
                                    <div class="grid h-6 w-6 place-items-center rounded text-xs font-semibold text-white"
                                        :style="`background-color: ${palette.color}`" x-text="palette.shortLabel"></div>
                                    <div class="text-sm font-medium text-slate-900" x-text="palette.label"></div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500" x-text="palette.help"></p>
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
                                                    <select x-model="selectedNode.data.lookup_source"
                                                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">— Feld waehlen —</option>
                                                        <template x-for="f in formSchema" :key="f.key">
                                                            <option :value="f.key" x-text="f.label || f.key"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <p class="text-xs text-slate-500">Der Workflow nimmt die Verantwortlich-E-Mail aus der Liste und sucht den entsprechenden Benutzer.</p>
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
                                        <p class="rounded-md bg-slate-50 p-2 text-xs text-slate-500">Ausgaenge: <strong>Genehmigt</strong> / <strong>Abgelehnt</strong> <span x-show="selectedNode.data.allow_forward">/ <strong>Weitergeleitet</strong></span></p>
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
                                                        <select x-model="branch.field" class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="">Feld waehlen</option>
                                                            <template x-for="f in formSchema" :key="f.key">
                                                                <option :value="f.key" x-text="f.label || f.key"></option>
                                                            </template>
                                                        </select>
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
                                                <option value="supervisor_of_initiator">Vorgesetzter des Antragstellers</option>
                                                <option value="role">Mitglieder einer Rolle</option>
                                                <option value="user">Konkrete Person</option>
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

                                {{-- Start settings --}}
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
