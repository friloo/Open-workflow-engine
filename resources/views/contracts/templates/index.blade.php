<x-app-layout>
    <x-slot name="header">
        Vertrags-Vorlagen
        <x-help-hint topic="contracts" label="Anleitung" />
    </x-slot>
    <x-slot name="subheader">Vorlagen mit Platzhaltern wie {{ '{{ party }}' }}, {{ '{{ end_date }}' }} — erzeugen auf Knopfdruck eine ausgefuellte Vertrags-PDF.</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Vertraege', 'url' => route('contracts.index')],
        ['title' => 'Vorlagen'],
    ]" />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('contract-templates.create') }}"><x-primary-button type="button">Neue Vorlage</x-primary-button></a>
    </div>

    <x-card>
        @if($templates->isEmpty())
            <x-empty-state icon="document" title="Noch keine Vorlagen"
                description="Lege Vorlagen fuer wiederkehrende Vertragsarten an — z. B. Wartungsvertrag, Mietvertrag. Platzhalter wie {{ '{{ party }}' }} werden beim Erzeugen aus den Vertragsdaten gefuellt.">
                <a href="{{ route('contract-templates.create') }}"><x-primary-button type="button">Erste Vorlage</x-primary-button></a>
            </x-empty-state>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
            <table class="min-w-full text-sm divide-y divide-slate-200">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Vertragsart</th>
                        <th class="py-2 pr-4">Angelegt</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($templates as $t)
                        <tr>
                            <td class="py-2 pr-4">
                                <div class="font-medium text-slate-900">{{ $t->name }}</div>
                                @if($t->description)
                                    <div class="text-xs text-slate-500">{{ $t->description }}</div>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-slate-700">{{ $t->type?->name ?: '— alle —' }}</td>
                            <td class="py-2 pr-4 text-xs text-slate-500">{{ $t->created_at?->format('d.m.Y') }}</td>
                            <td class="py-2 pr-4 text-right whitespace-nowrap">
                                <a href="{{ route('contract-templates.edit', $t) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                <form method="POST" action="{{ route('contract-templates.destroy', $t) }}" class="inline ms-2"
                                      onsubmit="return confirm('Vorlage loeschen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
