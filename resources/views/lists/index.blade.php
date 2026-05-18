<x-app-layout>
    <x-slot name="header">Listen</x-slot>
    <x-slot name="subheader">Lookup-Listen (z. B. Kostenstellen) mit Verantwortlichen und Eskalationspersonen, direkt im Workflow verwendbar.</x-slot>

    <div class="mb-4 flex justify-end">
        @if(auth()->user()->hasPermission('lists.manage'))
            <a href="{{ route('lists.create') }}"><x-primary-button type="button">Neue Liste</x-primary-button></a>
        @endif
    </div>

    <x-card>
        @if($lists->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Listen.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Spalten</th>
                        <th class="py-2 pr-4">Eintraege</th>
                        <th class="py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($lists as $l)
                            <tr>
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-slate-900">{{ $l->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $l->description }}</div>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($l->columns as $c)
                                            <span class="inline-flex items-center rounded-md
                                                @switch($c['role'])
                                                    @case('key') bg-amber-50 text-amber-700 @break
                                                    @case('responsible') bg-emerald-50 text-emerald-700 @break
                                                    @case('escalation') bg-rose-50 text-rose-700 @break
                                                    @default bg-slate-100 text-slate-700
                                                @endswitch px-2 py-0.5 text-xs">{{ $c['label'] }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-3 pr-4 text-slate-700">{{ $l->entries_count }}</td>
                                <td class="py-3 text-right space-x-3">
                                    <a href="{{ route('lists.edit', $l) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                    @if(auth()->user()->hasPermission('lists.manage'))
                                        <form method="POST" action="{{ route('lists.destroy', $l) }}" class="inline" onsubmit="return confirm('Liste wirklich loeschen?')">
                                            @csrf @method('DELETE')
                                            <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $lists->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
