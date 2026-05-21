<x-app-layout>
    <x-slot name="header">Statistik · {{ $workflow->name }}</x-slot>
    <x-slot name="subheader">Durchlaufzeiten, Engpässe und Throughput für diesen Workflow.</x-slot>

    @php
        if (! function_exists('owe_fmt_duration')) {
            function owe_fmt_duration($seconds) {
                if ($seconds === null) return '—';
                $s = (int) $seconds;
                if ($s < 60) return $s.' s';
                if ($s < 3600) return round($s/60, 1).' min';
                if ($s < 86400) return round($s/3600, 1).' h';
                return round($s/86400, 1).' Tage';
            }
        }
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-stat-card label="Laufend" :value="$stats['instances']['running']" tone="indigo" />
        <x-stat-card label="Abgeschlossen" :value="$stats['instances']['completed']" tone="emerald" />
        <x-stat-card label="Fehlgeschlagen" :value="$stats['instances']['failed']" tone="rose" />
        <x-stat-card label="Überfällige Aufgaben" :value="$stats['overdue_tasks']" tone="amber" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-stat-card label="Median-Laufzeit"
            :value="owe_fmt_duration($stats['duration']['p50'])"
            :hint="'n='.$stats['duration']['n']" />
        <x-stat-card label="Durchschnitt"
            :value="owe_fmt_duration($stats['duration']['avg'])" />
        <x-stat-card label="95-Perzentil (langsamste 5%)"
            :value="owe_fmt_duration($stats['duration']['p95'])"
            tone="amber" />
    </div>

    <x-card title="Engpässe — langsamste Schritte" description="Durchschnittliche Bearbeitungszeit pro Schritt (assigned -> completed).">
        @if(empty($stats['bottlenecks']))
            <x-empty-state title="Noch keine abgeschlossenen Schritte" description="Sobald Vorgänge durchgelaufen sind, erscheinen hier die Engpässe." />
        @else
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Schritt</th>
                    <th class="py-2 pr-4 text-right">Anzahl</th>
                    <th class="py-2 pr-4 text-right">Durchschnitt</th>
                    <th class="py-2 pr-4 text-right">p95</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($stats['bottlenecks'] as $b)
                        <tr>
                            <td class="py-2 pr-4 font-medium">{{ $b['label'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ $b['n'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ owe_fmt_duration($b['avg']) }}</td>
                            <td class="py-2 pr-4 text-right">{{ owe_fmt_duration($b['p95']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <x-card title="Throughput (letzte 12 Wochen)">
        @php $max = max(1, max(array_merge(array_column($stats['throughput'], 'started'), array_column($stats['throughput'], 'completed')))); @endphp
        <div class="flex items-end gap-2 h-32">
            @foreach($stats['throughput'] as $w)
                <div class="flex-1 flex flex-col items-center justify-end">
                    <div class="w-full flex items-end gap-0.5 h-24">
                        <div class="flex-1 bg-indigo-400 rounded-t" style="height: {{ (int) (($w['started'] / $max) * 100) }}%" title="gestartet: {{ $w['started'] }}"></div>
                        <div class="flex-1 bg-emerald-500 rounded-t" style="height: {{ (int) (($w['completed'] / $max) * 100) }}%" title="abgeschlossen: {{ $w['completed'] }}"></div>
                    </div>
                    <div class="mt-1 text-[10px] text-slate-500 -rotate-45 origin-left whitespace-nowrap">{{ $w['week'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex items-center gap-4 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-indigo-400"></span> gestartet</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></span> abgeschlossen</span>
        </div>
    </x-card>
</x-app-layout>
