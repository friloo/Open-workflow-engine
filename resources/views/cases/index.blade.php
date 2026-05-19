<x-app-layout>
    <x-slot name="header">Akten</x-slot>
    <x-slot name="subheader">Sammlungen zusammengehoeriger Dokumente — z. B. „Akte Mueller GmbH", „Vertrag XY", „Projekt Z".</x-slot>

    <div class="mb-4 flex items-center justify-between">
        <form method="GET" class="flex gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="Suchen nach Name oder Referenz..." class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <x-secondary-button type="submit">Suchen</x-secondary-button>
        </form>
        <a href="{{ route('cases.create') }}"><x-primary-button type="button">Neue Akte</x-primary-button></a>
    </div>

    <x-card>
        @if($cases->isEmpty())
            <x-empty-state icon="document" title="Noch keine Akten"
                description="Lege eine Akte an, um Dokumente thematisch zu buendeln (z. B. pro Kunde, Vertrag oder Projekt)." />
        @else
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Name</th>
                    <th class="py-2 pr-4">Referenz</th>
                    <th class="py-2 pr-4 text-right">Dokumente</th>
                    <th class="py-2 pr-4">Status</th>
                    <th class="py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($cases as $c)
                        <tr>
                            <td class="py-3 pr-4">
                                <a href="{{ route('cases.show', $c) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $c->name }}</a>
                                @if($c->description)<div class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($c->description, 80) }}</div>@endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-700">{{ $c->reference }}</td>
                            <td class="py-3 pr-4 text-right text-xs">{{ $c->attachments_count }}</td>
                            <td class="py-3 pr-4">
                                @if($c->closed_at)
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">geschlossen</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">offen</span>
                                @endif
                            </td>
                            <td class="py-3 text-right"><a href="{{ route('cases.show', $c) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Oeffnen &rarr;</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $cases->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
