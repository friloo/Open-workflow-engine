@if(session('importErrors'))
    <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        <strong>Import-Hinweise:</strong>
        <ul class="list-disc ps-4 mt-1">
            @foreach(session('importErrors') as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($entries->isEmpty())
    <p class="text-sm text-slate-500">Noch keine Eintraege.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    @foreach($list->columns as $c)
                        <th class="py-2 pr-4">{{ $c['label'] }}</th>
                    @endforeach
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($entries as $e)
                    <tr>
                        @foreach($list->columns as $c)
                            <td class="py-2 pr-4 text-slate-700">{{ $e->data[$c['key']] ?? '—' }}</td>
                        @endforeach
                        <td class="py-2 text-right">
                            <form method="POST" action="{{ route('lists.entries.destroy', [$list, $e]) }}" onsubmit="return confirm('Eintrag loeschen?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs text-rose-600 hover:text-rose-500">loeschen</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
@endif

<form method="POST" action="{{ route('lists.entries.store', $list) }}" class="mt-4 border-t border-slate-200 pt-4 grid grid-cols-1 gap-2">
    @csrf
    @foreach($list->columns as $c)
        <div>
            <label class="block text-xs font-medium text-slate-600">{{ $c['label'] }}</label>
            <input type="{{ $c['type'] === 'email' ? 'email' : ($c['type'] === 'number' ? 'number' : 'text') }}"
                name="cells[{{ $c['key'] }}]" placeholder="{{ $c['key'] }}"
                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    @endforeach
    <button class="mt-2 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Eintrag hinzufuegen</button>
</form>
