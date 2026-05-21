<x-app-layout>
    <x-slot name="header">Performance-Audit</x-slot>
    <x-slot name="subheader">Routen, die {{ $threshold_ms }}ms oder {{ $threshold_queries }} Queries ueberschritten haben.</x-slot>

    @if($entries->isEmpty())
        <x-empty-state title="Alles im gruenen Bereich"
            description="Keine Eintraege im Performance-Log. Die Routen laufen innerhalb der konfigurierten Threshold-Werte (siehe PERF_THRESHOLD_MS / PERF_THRESHOLD_QUERIES in der .env)." />
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <x-card title="Letzte Eintraege" description="Sortiert vom neuesten zum aeltesten. Bis zu 200 Eintraege.">
                    <div class="overflow-x-auto">
                        <div class="overflow-x-auto -mx-4 sm:mx-0">
<table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                                    <th class="py-2 pr-4">Zeit</th>
                                    <th class="py-2 pr-4">Route</th>
                                    <th class="py-2 pr-4 text-right">Dauer</th>
                                    <th class="py-2 pr-4 text-right">Queries</th>
                                    <th class="py-2 pr-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($entries as $e)
                                    <tr>
                                        <td class="py-2 pr-4 text-xs text-slate-500 whitespace-nowrap">{{ $e['timestamp'] }}</td>
                                        <td class="py-2 pr-4 font-mono text-xs"><span class="text-slate-400">{{ $e['method'] }}</span> {{ $e['route'] }}</td>
                                        <td class="py-2 pr-4 text-right">
                                            <span class="{{ $e['duration_ms'] > $threshold_ms * 2 ? 'text-rose-600 font-semibold' : 'text-amber-700' }}">{{ $e['duration_ms'] }}ms</span>
                                        </td>
                                        <td class="py-2 pr-4 text-right">
                                            <span class="{{ $e['queries'] > $threshold_queries * 2 ? 'text-rose-600 font-semibold' : 'text-slate-700' }}">{{ $e['queries'] }}</span>
                                        </td>
                                        <td class="py-2 pr-4 text-right text-xs text-slate-500">{{ $e['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
</div>
                    </div>
                </x-card>
            </div>

            <x-card title="Haeufigste langsame Routen" description="Top 15 nach Anzahl Vorkommen.">
                @if($top->isEmpty())
                    <p class="text-sm text-slate-500">Noch nichts aggregiert.</p>
                @else
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach($top as $t)
                            <li class="py-2">
                                <div class="font-mono text-xs text-slate-900 truncate">{{ $t['route'] }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $t['count'] }}× · max {{ $t['max_ms'] }}ms · ø {{ $t['avg_ms'] }}ms · max {{ $t['max_queries'] }} Q
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @endif

    <div class="text-xs text-slate-500">
        Threshold-Werte (in .env): <code>PERF_THRESHOLD_MS</code> ({{ $threshold_ms }})
        und <code>PERF_THRESHOLD_QUERIES</code> ({{ $threshold_queries }}).
        Per <code>APP_PERF_HEADER=true</code> kannst du zusaetzlich pro Response
        Header <code>X-Server-Duration</code> + <code>X-DB-Queries</code> einblenden — praktisch im Browser-DevTools.
    </div>
</x-app-layout>
