<x-app-layout>
    <x-slot name="header">Workflow-Statistik</x-slot>
    <x-slot name="subheader">Auf einen Blick: laufende Instanzen, überfällige Aufgaben, Wochen-Throughput.</x-slot>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-stat-card label="Laufend" :value="$global['instances']['running']" tone="indigo" />
        <x-stat-card label="Abgeschlossen" :value="$global['instances']['completed']" tone="emerald" />
        <x-stat-card label="Fehlgeschlagen" :value="$global['instances']['failed']" tone="rose" />
        <x-stat-card label="Überfällige Aufgaben" :value="$global['overdue_tasks']" tone="amber" />
    </div>

    <x-card title="Throughput (letzte 12 Wochen)" description="Anzahl gestartet vs. abgeschlossen pro Kalenderwoche.">
        @php($max = max(1, max(array_merge(array_column($global['throughput'], 'started'), array_column($global['throughput'], 'completed')))))
        <div class="flex items-end gap-2 h-32">
            @foreach($global['throughput'] as $w)
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

    <x-card title="Pro Workflow">
        @if($workflows->isEmpty())
            <x-empty-state icon="workflow" title="Noch keine Workflows" description="Lege links unter Workflows einen ersten Workflow an — danach gibt's hier Statistiken." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($workflows as $w)
                    <li class="flex items-center justify-between py-2">
                        <div>
                            <div class="font-medium text-slate-900">{{ $w->name }}</div>
                            <div class="text-xs text-slate-500">Status: {{ $w->status }}</div>
                        </div>
                        <a href="{{ route('workflows.stats.show', $w) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Statistik &rarr;</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
