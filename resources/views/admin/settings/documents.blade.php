<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Dokumente</x-slot>
    <x-slot name="subheader">Archive, Berechtigungen pro Rolle, Aufbewahrungsregeln.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'documents'])

    <x-card title="Dokumenttypen / Archive" description="Klassen fuer hochgeladene Dateien (z. B. Rechnung, Vertrag, Fuehrerschein). Jeder Typ wird zu einem Archiv in der Dokumentenliste.">
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

    <x-card title="Berechtigungen je Rolle" description="Lege fest, welche Dokumenttypen eine Rolle in der Dokumenten-Suche sieht. Admin sieht immer alles.">
        @if(empty($documentTypes))
            <p class="text-sm text-slate-500">Lege zuerst Dokumenttypen oben an.</p>
        @else
            <form method="POST" action="{{ route('admin.settings.role_document_types.update') }}">
                @csrf
                <div class="space-y-3">
                    @foreach($roles as $role)
                        @php
                            $allowed = $roleDocumentTypes[$role->slug] ?? [];
                        @endphp
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="text-sm font-medium text-slate-900">{{ $role->name }} <code class="ms-1 text-xs text-slate-500">{{ $role->slug }}</code></div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($documentTypes as $dt)
                                    <label class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2 py-0.5 text-xs has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                        <input type="checkbox" name="roles[{{ $role->slug }}][]" value="{{ $dt }}" @checked(in_array($dt, $allowed))
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        {{ $dt }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex justify-end">
                    <x-primary-button>Berechtigungen speichern</x-primary-button>
                </div>
            </form>
        @endif
    </x-card>

    <x-card title="Aufbewahrungsregeln pro Dokumenttyp" description="Pro Dokumenttyp eine Mindestlaufzeit und eine Aktion nach Ablauf. Wird taeglich um 03:15 ausgewertet.">
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
                                <option value="mark_for_review">Pruefung markieren</option>
                                <option value="archive">Archivieren (Soft-Delete)</option>
                                <option value="delete">Endgueltig loeschen</option>
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
</x-app-layout>
