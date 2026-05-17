<x-app-layout>
    <x-slot name="header">Audit-Log</x-slot>
    <x-slot name="subheader">Revisionssichere, hashverkettete Historie aller relevanten Aktionen.</x-slot>

    <div class="mb-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-col sm:flex-row gap-2">
            <x-text-input name="q" value="{{ $search }}" placeholder="Suche im Log..." />
            <select name="event" class="block rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Alle Ereignisse</option>
                @foreach($events as $e)
                    <option value="{{ $e }}" @selected($filterEvent === $e)>{{ $e }}</option>
                @endforeach
            </select>
            <x-secondary-button type="submit">Filtern</x-secondary-button>
        </form>
        @if(auth()->user()->hasPermission('audit.verify'))
            <a href="{{ route('admin.audit.verify') }}"><x-primary-button type="button">Integritaetskette pruefen</x-primary-button></a>
        @endif
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-4">Zeit</th>
                        <th class="py-2 pr-4">Benutzer</th>
                        <th class="py-2 pr-4">Ereignis</th>
                        <th class="py-2 pr-4">Beschreibung</th>
                        <th class="py-2 pr-4">Objekt</th>
                        <th class="py-2">Hash</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($entries as $e)
                        <tr>
                            <td class="py-3 pr-4 text-slate-700 whitespace-nowrap">{{ $e->created_at?->format('d.m.Y H:i:s') }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ $e->user?->name ?? '—' }}</td>
                            <td class="py-3 pr-4"><span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $e->event }}</span></td>
                            <td class="py-3 pr-4 text-slate-700">{{ $e->description }}</td>
                            <td class="py-3 pr-4 text-xs text-slate-500">
                                @if($e->auditable_type)
                                    <code class="bg-slate-100 rounded px-1">{{ class_basename($e->auditable_type) }}#{{ $e->auditable_id }}</code>
                                @endif
                            </td>
                            <td class="py-3 text-xs font-mono text-slate-500" title="{{ $e->hash }}">{{ \Illuminate\Support\Str::limit($e->hash, 12, '…') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">Keine Eintraege.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    </x-card>
</x-app-layout>
