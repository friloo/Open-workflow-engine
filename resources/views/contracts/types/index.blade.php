<x-app-layout>
    <x-slot name="header">
        Vertragsarten
        <x-help-hint topic="contracts" label="Anleitung Vertragsarten" />
    </x-slot>
    <x-slot name="subheader">Pro Vertragsart: Default-Frist + welche Rollen Verträge dieses Typs sehen/bearbeiten dürfen.</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Verträge', 'url' => route('contracts.index')],
        ['title' => 'Vertragsarten'],
    ]" />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('contract-types.create') }}"><x-primary-button type="button">Neue Vertragsart</x-primary-button></a>
    </div>

    <x-card>
        @if($types->isEmpty())
            <x-empty-state icon="document" title="Noch keine Vertragsarten"
                description="Definiere Vertragsarten (Mietvertrag, Wartung, Software-Lizenz ...) und steuere, welche Rollen welche Arten sehen dürfen.">
                <a href="{{ route('contract-types.create') }}"><x-primary-button type="button">Erste Vertragsart</x-primary-button></a>
            </x-empty-state>
        @else
            <table class="min-w-full text-sm divide-y divide-slate-200">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Default-Frist</th>
                        <th class="py-2 pr-4">Berechtigte Rollen</th>
                        <th class="py-2 pr-4">Verträge</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($types as $t)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 pr-4">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $t->color }}"></span>
                                    <span class="font-medium text-slate-900">{{ $t->name }}</span>
                                </div>
                                @if($t->description)
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $t->description }}</p>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-700">{{ $t->default_notice_period_days }} Tage</td>
                            <td class="py-3 pr-4 text-xs text-slate-600">
                                @forelse($t->roles as $r)
                                    <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 me-1 mb-1">
                                        {{ $r->name }}
                                        @if($r->pivot->can_manage)
                                            <span class="ms-1 text-[10px] font-semibold text-indigo-600">+M</span>
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-slate-400">— keine Rolle freigeschaltet —</span>
                                @endforelse
                            </td>
                            <td class="py-3 pr-4 text-slate-700">{{ $t->contracts_count }}</td>
                            <td class="py-3 pr-4 text-right whitespace-nowrap">
                                <a href="{{ route('contract-types.edit', $t) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                <form method="POST" action="{{ route('contract-types.destroy', $t) }}" class="inline ms-2"
                                      onsubmit="return confirm('Vertragsart löschen? Geht nur, wenn keine Verträge dieser Art existieren.')">
                                    @csrf @method('DELETE')
                                    <button class="text-sm text-rose-600 hover:text-rose-500">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-app-layout>
