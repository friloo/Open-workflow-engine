<x-app-layout>
    <x-slot name="header">
        Reports & KPIs
        <x-help-hint topic="stats" label="Anleitung Reports" />
    </x-slot>
    <x-slot name="subheader">Workflow-Durchlaufzeiten, SLA-Verletzungen, Bottlenecks und Auslastung.</x-slot>

    {{-- Zeitraum-Wahl --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-slate-500">Zeitraum:</span>
        @foreach([7 => '7 Tage', 14 => '14 Tage', 30 => '30 Tage', 90 => '90 Tage', 180 => '6 Monate', 365 => '1 Jahr'] as $d => $label)
            <a href="?days={{ $d }}"
               class="rounded-full px-3 py-1 text-xs font-medium {{ $rangeDays === $d ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </form>

    {{-- KPI-Karten --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <x-card>
            <div class="text-xs uppercase tracking-wider text-slate-500">Gestartet</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($volume['total'], 0, ',', '.') }}</div>
        </x-card>
        <x-card>
            <div class="text-xs uppercase tracking-wider text-slate-500">Abgeschlossen</div>
            <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ number_format($volume['completed'], 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">{{ $volume['completionRate'] }}% Quote</div>
        </x-card>
        <x-card>
            <div class="text-xs uppercase tracking-wider text-slate-500">Laufend</div>
            <div class="mt-1 text-2xl font-semibold text-indigo-700">{{ number_format($volume['running'], 0, ',', '.') }}</div>
        </x-card>
        <x-card>
            <div class="text-xs uppercase tracking-wider text-slate-500">Abgebrochen</div>
            <div class="mt-1 text-2xl font-semibold text-slate-700">{{ number_format($volume['cancelled'], 0, ',', '.') }}</div>
        </x-card>
        <x-card>
            <div class="text-xs uppercase tracking-wider text-slate-500">Fehlgeschlagen</div>
            <div class="mt-1 text-2xl font-semibold text-rose-700">{{ number_format($volume['failed'], 0, ',', '.') }}</div>
        </x-card>
    </div>

    {{-- Trends (einfaches Inline-SVG-Sparkline) --}}
    <x-card title="Volumen-Trend" description="Gestartete und abgeschlossene Workflows pro Tag.">
        @php
            $max = max(1, $daily->max(fn ($d) => max($d['started'], $d['completed'])));
            $w = 800; $h = 120; $pad = 20;
            $count = max(1, $daily->count());
            $step = ($w - 2*$pad) / max(1, $count - 1);
        @endphp
        <div class="overflow-x-auto">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-32" preserveAspectRatio="none">
                {{-- Started: indigo --}}
                <polyline fill="none" stroke="#6366f1" stroke-width="2"
                    points="@foreach($daily as $i => $d){{ ($pad + $i*$step) }},{{ ($h - $pad - ($d['started']/$max)*($h-2*$pad)) }} @endforeach"/>
                {{-- Completed: emerald --}}
                <polyline fill="none" stroke="#10b981" stroke-width="2"
                    points="@foreach($daily as $i => $d){{ ($pad + $i*$step) }},{{ ($h - $pad - ($d['completed']/$max)*($h-2*$pad)) }} @endforeach"/>
            </svg>
        </div>
        <div class="mt-2 flex justify-end gap-4 text-xs">
            <span class="inline-flex items-center gap-1"><span class="h-2 w-3 rounded bg-indigo-500"></span>Gestartet</span>
            <span class="inline-flex items-center gap-1"><span class="h-2 w-3 rounded bg-emerald-500"></span>Abgeschlossen</span>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Durchlaufzeiten --}}
        <x-card title="Durchlaufzeiten pro Workflow" description="Median (gegen Ausreisser robust) und Mittel in Stunden, für abgeschlossene Instanzen im Zeitraum.">
            @if($leadTimes->isEmpty())
                <p class="text-sm text-slate-500">Keine abgeschlossenen Instanzen im Zeitraum.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th class="py-2 pr-4">Workflow</th>
                            <th class="py-2 pr-4 text-right">N</th>
                            <th class="py-2 pr-4 text-right">Median (h)</th>
                            <th class="py-2 text-right">Mittel (h)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($leadTimes as $row)
                            <tr>
                                <td class="py-2 pr-4">
                                    <a href="{{ route('workflows.stats.show', $row['workflow_id']) }}" class="text-slate-900 hover:text-indigo-600">{{ $row['name'] }}</a>
                                </td>
                                <td class="py-2 pr-4 text-right text-slate-500">{{ $row['n'] }}</td>
                                <td class="py-2 pr-4 text-right font-medium">{{ number_format($row['median_h'], 1, ',', '.') }}</td>
                                <td class="py-2 text-right text-slate-500">{{ number_format($row['avg_h'], 1, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        {{-- SLA-Verletzungen --}}
        <x-card title="SLA-Verletzungen jetzt" description="Aktuell offene Aufgaben mit überschrittenem Fälligkeits-Datum.">
            @if($slaViolations->isEmpty())
                <p class="text-sm text-emerald-700">Keine überfälligen Aufgaben — alles im grünen Bereich.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th class="py-2 pr-4">Workflow</th>
                            <th class="py-2 text-right">Überfällig</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($slaViolations as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['name'] }}</td>
                                <td class="py-2 text-right font-semibold text-rose-600">{{ $row['overdue'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        {{-- Bottlenecks --}}
        <x-card title="Top-Verzögerer-Knoten" description="Welcher Knoten dauert (Median) am längsten vom Zuweisen bis zum Abschluss?">
            @if($slowestSteps->isEmpty())
                <p class="text-sm text-slate-500">Keine abgeschlossenen Aufgaben im Zeitraum.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th class="py-2 pr-4">Workflow</th>
                            <th class="py-2 pr-4">Knoten</th>
                            <th class="py-2 pr-4 text-right">N</th>
                            <th class="py-2 text-right">Median (h)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($slowestSteps as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['workflow_name'] }}</td>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $row['step_key'] }}</td>
                                <td class="py-2 pr-4 text-right text-slate-500">{{ $row['n'] }}</td>
                                <td class="py-2 text-right font-medium">{{ number_format($row['median_h'], 1, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        {{-- Top-Empfänger --}}
        <x-card title="Top-Empfänger" description="Wer hat aktuell die meisten offenen Aufgaben?">
            @if($topAssignees->isEmpty())
                <p class="text-sm text-slate-500">Keine offenen Aufgaben.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500">
                            <th class="py-2 pr-4">Mitarbeiter</th>
                            <th class="py-2 pr-4 text-right">Offen</th>
                            <th class="py-2 text-right">davon überfällig</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($topAssignees as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['name'] }}</td>
                                <td class="py-2 pr-4 text-right font-medium">{{ $row['open'] }}</td>
                                <td class="py-2 text-right {{ $row['overdue'] > 0 ? 'text-rose-600 font-semibold' : 'text-slate-500' }}">{{ $row['overdue'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>
</x-app-layout>
