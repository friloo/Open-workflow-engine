@php
    $authUser = auth()->user();
@endphp

<x-app-layout>
    <x-slot name="header">Hallo, {{ $authUser->name }}</x-slot>
    <x-slot name="subheader">Was heute auf dich wartet.</x-slot>

    @if($delegatedTo)
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            <strong>Vertretung aktiv</strong> — {{ $delegatedTo->name }} ({{ $delegatedTo->email }})
            uebernimmt deine neuen Aufgaben.
            <a href="{{ route('two-factor.show') }}" class="hidden"></a>
            <a href="{{ route('profile.edit') }}" class="ms-2 underline">aendern</a>
        </div>
    @endif

    {{-- KPI-Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card label="Offene Aufgaben" :value="$myOpenCount" tone="indigo"
            :hint="$myOverdueCount > 0 ? $myOverdueCount.' davon ueberfaellig' : null" />
        <a href="{{ route('documents.inbox') }}" class="block">
            <x-stat-card label="Postkorb" :value="$inboxCount" tone="amber"
                hint="Dokumente ohne Workflow" />
        </a>
        <a href="{{ route('workflow-instances.index') }}" class="block">
            <x-stat-card label="Meine Vorgaenge" :value="$myRecentInstances->count()" tone="emerald"
                hint="letzte 5 angezeigt" />
        </a>
        @if($adminInfo)
            <a href="{{ route('admin.health.index') }}" class="block">
                <x-stat-card label="System-Health"
                    :value="match($adminInfo['health_status']) { 'ok' => 'OK', 'warn' => 'Warn', 'fail' => 'Fehler' }"
                    :tone="match($adminInfo['health_status']) { 'ok' => 'emerald', 'warn' => 'amber', default => 'rose' }"
                    :hint="count($adminInfo['health_warns']).' Hinweis(e)'" />
            </a>
        @else
            <x-stat-card label="Notifications"
                :value="auth()->user()->appNotifications()->whereNull('read_at')->count()"
                tone="slate" hint="ungelesen" />
        @endif
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Meine offenen Aufgaben" description="Direkt zugewiesen oder via Rolle. Sortiert nach Frist.">
            @if($myOpenTasks->isEmpty())
                <p class="text-sm text-slate-500">Aktuell nichts offen. </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($myOpenTasks as $step)
                        @php($node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null)
                        @php($overdue = $step->due_at && $step->due_at->isPast())
                        <li class="py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('tasks.show', $step) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                        {{ data_get($node, 'data.label', 'Aufgabe') }}
                                    </a>
                                    <div class="text-xs text-slate-500">{{ $step->instance->workflow?->name }} · von {{ $step->instance->starter?->name ?? '—' }}</div>
                                </div>
                                <div class="shrink-0 text-right text-xs {{ $overdue ? 'text-rose-700 font-semibold' : 'text-slate-500' }}">
                                    @if($step->due_at)
                                        {{ $overdue ? 'ueberfaellig' : 'bis' }} {{ $step->due_at->format('d.m.Y') }}
                                    @else
                                        ohne Frist
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('tasks.index') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">Alle Aufgaben &rarr;</a>
            @endif
        </x-card>

        <x-card title="Meine letzten Vorgaenge">
            @if($myRecentInstances->isEmpty())
                <p class="text-sm text-slate-500">Du hast noch keinen Workflow gestartet.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($myRecentInstances as $i)
                        <li class="py-2 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('workflow-instances.show', $i) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                    {{ $i->workflow?->name }} <span class="text-xs text-slate-500">#{{ $i->id }}</span>
                                </a>
                                <div class="text-xs text-slate-500">{{ $i->started_at?->diffForHumans() }}</div>
                            </div>
                            @php($tone = ['running' => 'indigo', 'completed' => 'emerald', 'failed' => 'rose', 'cancelled' => 'slate'][$i->status] ?? 'slate')
                            <span class="inline-flex items-center rounded-full bg-{{ $tone }}-50 px-2 py-0.5 text-xs font-medium text-{{ $tone }}-700">{{ $i->status }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    @if($adminInfo && ! empty($adminInfo['health_warns']))
        <div class="mt-6">
            <x-card title="System-Hinweise" description="Aus dem Health-Check.">
                <ul class="divide-y divide-slate-100">
                    @foreach($adminInfo['health_warns'] as $w)
                        <li class="py-2 flex items-center justify-between gap-2">
                            <div>
                                <div class="font-medium text-slate-900">{{ $w['name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $w['message'] }}</div>
                            </div>
                            @php($tone = ['warn' => 'amber', 'fail' => 'rose'][$w['status']] ?? 'slate')
                            <span class="inline-flex items-center rounded-full bg-{{ $tone }}-50 px-2 py-0.5 text-xs font-medium text-{{ $tone }}-700">{{ $w['status'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.health.index') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">Vollstaendig anzeigen &rarr;</a>
            </x-card>
        </div>
    @endif
</x-app-layout>
