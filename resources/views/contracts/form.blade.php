<x-app-layout>
    <x-slot name="header">{{ $contract->exists ? 'Vertrag bearbeiten' : 'Neuer Vertrag' }}</x-slot>
    <x-slot name="subheader">{{ $contract->name ?? 'Stammdaten + Laufzeit + Kündigungsfrist' }}</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Verträge', 'url' => route('contracts.index')],
        ['title' => $contract->exists ? $contract->name : 'Neu'],
    ]" />

    <x-card>
        <form method="POST"
              action="{{ $contract->exists ? route('contracts.update', $contract) : route('contracts.store') }}"
              class="space-y-4">
            @csrf
            @if($contract->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Vertragsname" />
                    <x-text-input id="name" name="name" value="{{ old('name', $contract->name) }}" required maxlength="255" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="party" value="Vertragspartner" />
                    <x-text-input id="party" name="party" value="{{ old('party', $contract->party) }}" maxlength="255" />
                </div>
                <div>
                    <x-input-label for="contract_type_id" value="Vertragsart" />
                    <select id="contract_type_id" name="contract_type_id"
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-data
                            @change="
                                const opt = $el.options[$el.selectedIndex];
                                const dn = opt.dataset.defaultDays;
                                if (dn) document.getElementById('notice_period_days').value = dn;
                            ">
                        <option value="">— Keine Zuordnung —</option>
                        @foreach($types as $t)
                            <option value="{{ $t->id }}"
                                    data-default-days="{{ $t->default_notice_period_days }}"
                                    @selected(old('contract_type_id', $contract->contract_type_id) == $t->id)>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        Bestimmt Default-Kündigungsfrist + welche Rollen den Vertrag sehen dürfen.
                        <a href="{{ route('contract-types.index') }}" class="text-indigo-600 hover:text-indigo-500">Vertragsarten verwalten</a>.
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="description" value="Beschreibung" />
                    <textarea id="description" name="description" rows="3"
                              class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $contract->description) }}</textarea>
                </div>

                <div>
                    <x-input-label for="start_date" value="Beginn" />
                    <x-text-input id="start_date" name="start_date" type="date"
                                  value="{{ old('start_date', optional($contract->start_date)->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label for="end_date" value="Ende" />
                    <x-text-input id="end_date" name="end_date" type="date"
                                  value="{{ old('end_date', optional($contract->end_date)->format('Y-m-d')) }}" />
                    <x-input-error :messages="$errors->get('end_date')" />
                </div>
                <div>
                    <x-input-label for="notice_period_days" value="Kündigungsfrist (Tage vor Ende)" />
                    <x-text-input id="notice_period_days" name="notice_period_days" type="number" min="0" max="3650"
                                  value="{{ old('notice_period_days', $contract->notice_period_days ?? 90) }}" required />
                    <p class="mt-1 text-xs text-slate-500">OWE erinnert dich pro Vertrag X Tage vorher.</p>
                </div>
                <div>
                    <x-input-label for="owner_user_id" value="Verantwortlich" />
                    <select id="owner_user_id" name="owner_user_id"
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Niemand —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(old('owner_user_id', $contract->owner_user_id) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2 rounded-lg border border-slate-200 p-3 bg-slate-50/50">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="auto_renew" value="0">
                        <input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew', $contract->auto_renew))
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Vertrag verlängert sich automatisch, wenn nicht gekündigt
                    </label>
                    <div class="mt-2 max-w-xs">
                        <x-input-label for="auto_renew_months" value="Verlängerung um Monate" />
                        <x-text-input id="auto_renew_months" name="auto_renew_months" type="number" min="1" max="120"
                                      value="{{ old('auto_renew_months', $contract->auto_renew_months ?? 12) }}" />
                    </div>
                </div>

                {{-- Pro-Vertrag Berechtigungen: zusaetzliche Rollen freischalten --}}
                <div class="sm:col-span-2 rounded-lg border border-slate-200 p-3">
                    <h4 class="text-sm font-semibold text-slate-900 mb-2">Zusaetzliche Berechtigungen pro Rolle</h4>
                    <p class="text-xs text-slate-500 mb-3">
                        Der <strong>Verantwortliche</strong> und alle über den <strong>Vertragstyp</strong>
                        berechtigten Rollen sehen den Vertrag automatisch. Hier kannst du weitere Rollen
                        nur für DIESEN Vertrag freischalten.
                    </p>
                    @php
                        $assigned = $contract->exists ? $contract->roles->keyBy('id') : collect();
                    @endphp
                    <div class="space-y-1.5">
                        @foreach($roles as $idx => $r)
                            @php
                                $row = $assigned->get($r->id);
                                $canView = $row !== null;
                                $canManage = $canView && $row->pivot->can_manage;
                            @endphp
                            <div class="grid grid-cols-12 items-center gap-2 text-xs">
                                <span class="col-span-6 text-slate-700">{{ $r->name }}</span>
                                <label class="col-span-3 inline-flex items-center gap-1.5 text-slate-700">
                                    <input type="hidden" name="extra_roles[{{ $idx }}][id]" value="{{ $r->id }}">
                                    <input type="checkbox" name="extra_roles[{{ $idx }}][can_view]" value="1"
                                           @checked($canView)
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    Sehen
                                </label>
                                <label class="col-span-3 inline-flex items-center gap-1.5 text-slate-700">
                                    <input type="checkbox" name="extra_roles[{{ $idx }}][can_manage]" value="1"
                                           @checked($canManage)
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    Bearbeiten
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                @if($contract->exists)
                    <a href="{{ route('contracts.show', $contract) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</a>
                @endif
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
