<x-app-layout>
    <x-slot name="header">{{ $type->exists ? 'Vertragsart bearbeiten' : 'Neue Vertragsart' }}</x-slot>
    <x-slot name="subheader">{{ $type->name ?? 'Definition + Default-Frist + Rollen-Berechtigungen' }}</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Vertraege', 'url' => route('contracts.index')],
        ['title' => 'Vertragsarten', 'url' => route('contract-types.index')],
        ['title' => $type->exists ? $type->name : 'Neu'],
    ]" />

    <x-card>
        <form method="POST"
              action="{{ $type->exists ? route('contract-types.update', $type) : route('contract-types.store') }}"
              class="space-y-5">
            @csrf
            @if($type->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" value="{{ old('name', $type->name) }}" required maxlength="128" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="default_notice_period_days" value="Default-Kuendigungsfrist (Tage)" />
                    <x-text-input id="default_notice_period_days" name="default_notice_period_days" type="number" min="0" max="3650"
                                  value="{{ old('default_notice_period_days', $type->default_notice_period_days ?? 90) }}" />
                </div>
                <div>
                    <x-input-label for="color" value="Farbe" />
                    <x-text-input id="color" name="color" type="color"
                                  value="{{ old('color', $type->color ?? '#64748b') }}" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="description" value="Beschreibung" />
                    <textarea id="description" name="description" rows="2"
                              class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $type->description) }}</textarea>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-3">
                <h4 class="text-sm font-semibold text-slate-900 mb-2">Rollen-Berechtigungen</h4>
                <p class="text-xs text-slate-500 mb-3">
                    Wer Vertraege dieser Art sehen darf, und wer sie auch bearbeiten/loeschen darf.
                    Admins haben immer Zugriff (unabhaengig von dieser Liste).
                </p>
                @php($assigned = $type->exists ? $type->roles->keyBy('id') : collect())
                <div class="space-y-1.5">
                    @foreach($roles as $idx => $r)
                        @php
                            $row = $assigned->get($r->id);
                            $canView = $row !== null;
                            $canManage = $canView && $row->pivot->can_manage;
                        @endphp
                        <div class="grid grid-cols-12 items-center gap-2 text-sm">
                            <span class="col-span-6 text-slate-700">{{ $r->name }}</span>
                            <label class="col-span-3 inline-flex items-center gap-1.5">
                                <input type="hidden" name="roles[{{ $idx }}][id]" value="{{ $r->id }}">
                                <input type="checkbox" name="roles[{{ $idx }}][can_view]" value="1"
                                       @checked($canView)
                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                Sehen
                            </label>
                            <label class="col-span-3 inline-flex items-center gap-1.5">
                                <input type="checkbox" name="roles[{{ $idx }}][can_manage]" value="1"
                                       @checked($canManage)
                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                Bearbeiten
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('contract-types.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</a>
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
