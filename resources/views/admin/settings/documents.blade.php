<x-app-layout>
    <x-slot name="header">
        Systemeinstellungen · Dokumente
        <x-help-hint topic="document-schemas" label="Anleitung Dokumente" />
    </x-slot>
    <x-slot name="subheader">Archive, Berechtigungen pro Rolle, Aufbewahrungsregeln.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'documents'])

    <x-card title="Dokumenttypen / Archive" description="Klassen für hochgeladene Dateien (z. B. Rechnung, Vertrag, Führerschein). Jeder Typ wird zu einem Archiv in der Dokumentenliste.">
        <form method="POST" action="{{ route('admin.settings.document_types.update') }}"
              x-data='@json(["types" => $documentTypes ?: []])'>
            @csrf
            <div class="space-y-2"
                 x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                 x-sort="types.splice($event.newIndex, 0, types.splice($event.oldIndex, 1)[0])">
                <template x-for="(t, idx) in types" :key="idx">
                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2" x-sort:item="idx">
                        <span class="drag-handle cursor-grab select-none text-xs text-slate-400 px-1">⋮⋮</span>
                        <input type="text" :name="`types[${idx}]`" x-model="types[idx]" placeholder="z. B. Rechnung" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" @click="types.splice(idx,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                    </div>
                </template>
                <button type="button" @click="types.push('')" class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Typ</button>
            </div>
            <div class="mt-3 flex justify-end">
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
        <p class="mt-3 text-xs text-slate-500">Indexfelder pro Archiv pflegst du unter <a href="{{ route('admin.document_schemas.index') }}" class="text-indigo-600 hover:text-indigo-500">Dokument-Schemas</a>.</p>
    </x-card>

    <x-card title="Berechtigungen je Rolle"
            description="Welche Rolle welche Archive sieht — wird jetzt direkt bei der Rolle gepflegt, damit du beim Anlegen einer Rolle alles in einem Fenster hast.">
        @if(empty($documentTypes))
            <p class="text-sm text-slate-500">Lege zuerst Dokumenttypen oben an.</p>
        @elseif($roles->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Rollen vorhanden.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                            <th class="py-2 pr-4">Rolle</th>
                            <th class="py-2 pr-4">Sichtbare Archive</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($roles as $role)
                            @php $allowed = $roleDocumentTypes[$role->slug] ?? []; @endphp
                            <tr>
                                <td class="py-2 pr-4 font-medium text-slate-900 whitespace-nowrap">{{ $role->name }}</td>
                                <td class="py-2 pr-4">
                                    @if($role->slug === 'admin')
                                        <em class="text-slate-500">alle (implizit)</em>
                                    @elseif(empty($allowed))
                                        <span class="text-slate-400">keines</span>
                                    @else
                                        <span class="flex flex-wrap gap-1">
                                            @foreach($allowed as $dt)
                                                <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $dt }}</span>
                                            @endforeach
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="text-xs text-indigo-600 hover:text-indigo-500">Rolle bearbeiten &rarr;</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="Signatur-Pflicht pro Dokumenttyp" description="Welches Signatur-Niveau ist für diesen Dokumenttyp erforderlich. Workflows können das Level für einzelne Approvals anheben, nicht absenken.">
        @if(empty($documentTypes))
            <p class="text-sm text-slate-500">Lege zuerst Dokumenttypen oben an.</p>
        @else
            <form method="POST" action="{{ route('admin.settings.signature_levels.update') }}" class="space-y-2">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($documentTypes as $t)
                        <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-2.5">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-slate-900 truncate">{{ $t }}</div>
                            </div>
                            <select name="levels[{{ $t }}]" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach(['none' => 'Keine', 'ses' => 'SES', 'aes' => 'AES (PKCS#7)', 'qes' => 'QES (extern)'] as $code => $label)
                                    <option value="{{ $code }}" @selected(($signatureLevels[$t] ?? 'none') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex justify-end">
                    <x-primary-button>Speichern</x-primary-button>
                </div>
            </form>
            <p class="mt-3 text-xs text-slate-500">
                AES benötigt ein Server-Zertifikat in Settings (signatures.aes_cert_pem + signatures.aes_key_pem).
                QES benötigt einen externen Provider — aktuell verfügbar: <code>mock</code> (Tests/Demos),
                produktiv via D-Trust/Bundesdruckerei/Swisscom (Lizenz pro Mandant).
            </p>
        @endif
    </x-card>

    <x-card title="Aufbewahrungsregeln pro Dokumenttyp" description="Pro Dokumenttyp eine Mindestlaufzeit und eine Aktion nach Ablauf. Wird täglich um 03:15 ausgewertet.">
        @php
            $retentionRules = [];
            foreach (($retention ?? []) as $docType => $rule) {
                $retentionRules[] = [
                    'document_type' => $docType,
                    'min_years' => $rule['min_years'] ?? 10,
                    'max_years' => $rule['max_years'] ?? null,
                    'on_expiry' => $rule['on_expiry'] ?? 'mark_for_review',
                ];
            }
        @endphp
        <form method="POST" action="{{ route('admin.settings.retention.update') }}"
              x-data='@json(["rules" => $retentionRules])'>
            @csrf
            <div class="space-y-2">
                <template x-for="(r, idx) in rules" :key="idx">
                    <div class="grid grid-cols-12 gap-2 rounded-lg border border-slate-200 bg-white p-2">
                        <div class="col-span-4">
                            <label class="block text-xs font-medium text-slate-600">Dokumenttyp</label>
                            <input type="text" :name="`rules[${idx}][document_type]`" x-model="r.document_type" placeholder="z. B. Rechnung" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600">Min. Jahre</label>
                            <input type="number" min="0" max="100" :name="`rules[${idx}][min_years]`" x-model.number="r.min_years" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600">Max. Jahre</label>
                            <input type="number" min="1" max="200" :name="`rules[${idx}][max_years]`" x-model.number="r.max_years" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-xs font-medium text-slate-600">Nach Ablauf</label>
                            <select :name="`rules[${idx}][on_expiry]`" x-model="r.on_expiry" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="mark_for_review">Prüfung markieren</option>
                                <option value="archive">Archivieren (Soft-Delete)</option>
                                <option value="delete">Endgültig löschen</option>
                            </select>
                        </div>
                        <div class="col-span-1 flex items-end justify-end">
                            <button type="button" @click="rules.splice(idx,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                        </div>
                    </div>
                </template>
                <button type="button" @click="rules.push({document_type:'',min_years:10,max_years:11,on_expiry:'mark_for_review'})" class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Regel</button>
            </div>
            <div class="mt-3 flex justify-end">
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
    </x-card>

    <div id="sharing"></div>
    <x-card title="Externe Freigabe-Links (Sharing)"
            description="Caps und Prüfungs-Intervalle für per-Link geteilte Dokumente.">
        <form method="POST" action="{{ route('admin.settings.shares.update') }}" class="space-y-3 max-w-2xl">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <x-input-label for="max_expiry_days" value="Max. Ablauf (Tage)" />
                    <x-text-input id="max_expiry_days" name="max_expiry_days" type="number" min="1" max="3650" value="{{ $shares['max_expiry_days'] }}" />
                    <p class="mt-1 text-xs text-slate-500">Kann von individuellen Links nie überschritten werden.</p>
                </div>
                <div>
                    <x-input-label for="default_expiry_days" value="Default-Ablauf (Tage)" />
                    <x-text-input id="default_expiry_days" name="default_expiry_days" type="number" min="1" max="3650" value="{{ $shares['default_expiry_days'] }}" />
                </div>
                <div>
                    <x-input-label for="review_interval_days" value="Prüfungs-Intervall (Tage)" />
                    <x-text-input id="review_interval_days" name="review_interval_days" type="number" min="1" max="365" value="{{ $shares['review_interval_days'] }}" />
                    <p class="mt-1 text-xs text-slate-500">Mail an den Ersteller alle X Tage.</p>
                </div>
                <div>
                    <x-input-label for="review_grace_days" value="Reaktions-Frist (Tage)" />
                    <x-text-input id="review_grace_days" name="review_grace_days" type="number" min="1" max="90" value="{{ $shares['review_grace_days'] }}" />
                    <p class="mt-1 text-xs text-slate-500">Wird überschritten -> automatischer Widerruf.</p>
                </div>
            </div>
            <div class="flex justify-end">
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
