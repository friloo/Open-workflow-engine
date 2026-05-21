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

    {{-- SLA-Quote: wie viele Schritte wurden vor Frist abgeschlossen --}}
    @if(($stats['sla']['n'] ?? 0) > 0)
        <x-card title="SLA-Quote" description="Wie viele Schritte mit gesetzter Frist wurden rechtzeitig abgeschlossen.">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500">In-Time-Quote</div>
                    <div class="mt-1 text-3xl font-semibold {{ $stats['sla']['on_time_pct'] >= 80 ? 'text-emerald-700' : ($stats['sla']['on_time_pct'] >= 60 ? 'text-amber-700' : 'text-rose-700') }}">
                        {{ $stats['sla']['on_time_pct'] }}%
                    </div>
                    <div class="text-xs text-slate-500">{{ $stats['sla']['on_time'] }} von {{ $stats['sla']['n'] }} pünktlich</div>
                </div>
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Verspätete Schritte</div>
                    <div class="mt-1 text-3xl font-semibold text-rose-700">{{ $stats['sla']['late'] }}</div>
                    <div class="text-xs text-slate-500">vom Total</div>
                </div>
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Durchschnittliche Verspätung</div>
                    <div class="mt-1 text-3xl font-semibold text-slate-900">{{ owe_fmt_duration($stats['sla']['avg_lateness_sec']) }}</div>
                    <div class="text-xs text-slate-500">bei verspäteten Schritten</div>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Approval-Entscheidungs-Verteilung --}}
    @if(! empty($stats['decisions']))
        <x-card title="Approval-Entscheidungen" description="Verteilung von genehmigt / abgelehnt / eskaliert pro Approval-Knoten.">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Knoten</th>
                    <th class="py-2 pr-4 text-right">Total</th>
                    <th class="py-2 pr-4">Verteilung</th>
                    <th class="py-2 pr-4 text-right">Genehmigt</th>
                    <th class="py-2 pr-4 text-right">Abgelehnt</th>
                    <th class="py-2 pr-4 text-right">Eskaliert</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($stats['decisions'] as $d)
                        @php
                            $approvedPct = $d['total'] ? (int) round($d['approved'] / $d['total'] * 100) : 0;
                            $rejectedPct = $d['total'] ? (int) round($d['rejected'] / $d['total'] * 100) : 0;
                            $escalatedPct = max(0, 100 - $approvedPct - $rejectedPct);
                        @endphp
                        <tr>
                            <td class="py-2 pr-4 font-medium">{{ $d['label'] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $d['total'] }}</td>
                            <td class="py-2 pr-4">
                                <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="bg-emerald-500" style="width: {{ $approvedPct }}%" title="genehmigt {{ $approvedPct }}%"></div>
                                    <div class="bg-rose-500" style="width: {{ $rejectedPct }}%" title="abgelehnt {{ $rejectedPct }}%"></div>
                                    <div class="bg-amber-500" style="width: {{ $escalatedPct }}%" title="eskaliert {{ $escalatedPct }}%"></div>
                                </div>
                            </td>
                            <td class="py-2 pr-4 text-right text-emerald-700 tabular-nums">{{ $d['approved'] }}</td>
                            <td class="py-2 pr-4 text-right text-rose-700 tabular-nums">{{ $d['rejected'] }}</td>
                            <td class="py-2 pr-4 text-right text-amber-700 tabular-nums">{{ $d['escalated'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endif

    {{-- Top-Bearbeiter --}}
    @if(! empty($stats['top_assignees']))
        <x-card title="Top-Bearbeiter" description="Wer treibt diesen Workflow voran — abgeschlossene Schritte und durchschnittliche Bearbeitungszeit.">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Bearbeiter</th>
                    <th class="py-2 pr-4 text-right">Abgeschlossen</th>
                    <th class="py-2 pr-4 text-right">Ø-Zeit</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($stats['top_assignees'] as $a)
                        <tr>
                            <td class="py-2 pr-4 font-medium">{{ $a['name'] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $a['count'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ owe_fmt_duration($a['avg']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endif

    {{-- Heuristische Hinweise --}}
    @if(! empty($stats['suggestions']))
        <x-card title="Hinweise" description="Heuristische Empfehlungen aus den Auswertungen oben.">
            <ul class="space-y-2">
                @foreach($stats['suggestions'] as $s)
                    @php
                        $tone = $s['tone'] ?? 'slate';
                        $cls = [
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
                            'rose' => 'border-rose-200 bg-rose-50 text-rose-800',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            'slate' => 'border-slate-200 bg-slate-50 text-slate-700',
                        ][$tone] ?? 'border-slate-200 bg-slate-50 text-slate-700';
                    @endphp
                    <li class="rounded-lg border {{ $cls }} px-4 py-2 text-sm">{{ $s['text'] }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

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
