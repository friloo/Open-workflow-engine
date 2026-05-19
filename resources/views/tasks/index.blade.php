<x-app-layout>
    <x-slot name="header">Meine Aufgaben</x-slot>
    <x-slot name="subheader">Offene Workflow-Schritte, die auf deine Entscheidung warten.</x-slot>

    @php
        $chips = [
            ['key' => 'all', 'label' => 'Alle', 'tone' => 'slate'],
            ['key' => 'overdue', 'label' => 'Ueberfaellig', 'tone' => 'rose'],
            ['key' => 'today', 'label' => 'Heute faellig', 'tone' => 'amber'],
            ['key' => 'week', 'label' => 'Diese Woche', 'tone' => 'indigo'],
            ['key' => 'mine', 'label' => 'Direkt an mich', 'tone' => 'emerald'],
        ];
    @endphp

    <form method="GET" class="flex flex-wrap items-center gap-2">
        @foreach($chips as $chip)
            @php
                $active = $filter === $chip['key'];
                $count = $counts[$chip['key']] ?? 0;
                $base = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition';
                $toneIdle = match($chip['tone']) {
                    'rose' => 'bg-white text-rose-700 border border-rose-200 hover:bg-rose-50',
                    'amber' => 'bg-white text-amber-700 border border-amber-200 hover:bg-amber-50',
                    'indigo' => 'bg-white text-indigo-700 border border-indigo-200 hover:bg-indigo-50',
                    'emerald' => 'bg-white text-emerald-700 border border-emerald-200 hover:bg-emerald-50',
                    default => 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50',
                };
                $toneActive = match($chip['tone']) {
                    'rose' => 'bg-rose-600 text-white shadow-sm',
                    'amber' => 'bg-amber-500 text-white shadow-sm',
                    'indigo' => 'bg-indigo-600 text-white shadow-sm',
                    'emerald' => 'bg-emerald-600 text-white shadow-sm',
                    default => 'bg-slate-700 text-white shadow-sm',
                };
            @endphp
            <a href="{{ route('tasks.index', ['filter' => $chip['key'], 'q' => $q ?: null]) }}"
               class="{{ $base }} {{ $active ? $toneActive : $toneIdle }}">
                {{ $chip['label'] }}
                <span class="rounded-full px-1.5 py-0.5 text-[10px] {{ $active ? 'bg-white/20' : 'bg-slate-100 text-slate-600' }}">{{ $count }}</span>
            </a>
        @endforeach
        <div class="ms-auto flex items-center gap-2">
            @if($q !== '')
                <input type="hidden" name="filter" value="{{ $filter }}">
            @endif
            <input type="text" name="q" value="{{ $q }}" placeholder="Workflow suchen …"
                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </form>

    <x-card>
        @if($open->isEmpty())
            <x-empty-state icon="inbox"
                title="Keine Aufgaben"
                description="@if($filter !== 'all')Keine Aufgaben in diesem Filter. Probier 'Alle'.@else🎉 Inbox Zero. Lass dich nicht ablenken — oder starte einen neuen Antrag.@endif">
                @if($filter !== 'all')
                    <a href="{{ route('tasks.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Alle anzeigen</a>
                @endif
            </x-empty-state>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($open as $step)
                    @php
                        $nodeLabel = data_get($step->instance->version?->definition, "drawflow.Home.data.{$step->step_key}.data.label", 'Aufgabe');
                        $overdue = $step->due_at && $step->due_at->isPast();
                        $assignedRole = $step->assignedRole?->name;
                    @endphp
                    <li>
                        <a href="{{ route('tasks.show', $step) }}"
                           class="flex items-start gap-4 px-2 py-3 -mx-2 rounded-lg hover:bg-slate-50 transition">
                            <div class="mt-1">
                                @if($overdue)
                                    <span class="inline-flex h-2 w-2 rounded-full bg-rose-500"></span>
                                @elseif($step->due_at && $step->due_at->isToday())
                                    <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                                @else
                                    <span class="inline-flex h-2 w-2 rounded-full bg-slate-300"></span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <span class="text-sm font-semibold text-slate-900 truncate">{{ $nodeLabel }}</span>
                                    <span class="text-xs text-slate-500">in {{ $step->instance->workflow->name }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                    @if($step->instance->starter)
                                        <span>von <span class="font-medium text-slate-700">{{ $step->instance->starter->name }}</span></span>
                                    @endif
                                    @if($assignedRole)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5">an Rolle <strong class="text-slate-700">{{ $assignedRole }}</strong></span>
                                    @endif
                                    <span>eingegangen {{ $step->assigned_at?->diffForHumans() }}</span>
                                </div>
                            </div>
                            <div class="text-right text-xs whitespace-nowrap">
                                @if($step->due_at)
                                    <div class="{{ $overdue ? 'text-rose-600 font-semibold' : 'text-slate-700' }}">
                                        {{ $step->due_at->format('d.m.Y H:i') }}
                                    </div>
                                    <div class="{{ $overdue ? 'text-rose-500' : 'text-slate-400' }}">
                                        @if($overdue)
                                            {{ $step->due_at->diffForHumans() }}
                                        @else
                                            in {{ $step->due_at->diffForHumans(null, true) }}
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-400">ohne Frist</span>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $open->links() }}</div>
        @endif
    </x-card>

    @if($myRecent->isNotEmpty())
        <x-card title="Zuletzt von mir bearbeitet">
            <ul class="divide-y divide-slate-100">
                @foreach($myRecent as $step)
                    <li class="py-3 flex items-start justify-between gap-4 text-sm">
                        <div>
                            <div class="text-slate-900">{{ $step->instance->workflow->name }}</div>
                            <div class="text-xs text-slate-500">{{ $step->completed_at?->diffForHumans() }} — {{ $step->decision }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                            @switch($step->decision)
                                @case('approved') bg-emerald-50 text-emerald-700 @break
                                @case('rejected') bg-rose-50 text-rose-700 @break
                                @case('forwarded') bg-amber-50 text-amber-700 @break
                                @default bg-slate-100 text-slate-700
                            @endswitch">{{ $step->decision }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-app-layout>
