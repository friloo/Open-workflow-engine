@php
    $authUser = auth()->user();
@endphp

<x-app-layout>
    <x-slot name="header">Hallo, {{ $authUser->name }}</x-slot>
    <x-slot name="subheader">Was heute auf dich wartet.</x-slot>

    @if($delegatedTo)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            <strong>Vertretung aktiv</strong> — {{ $delegatedTo->name }} ({{ $delegatedTo->email }})
            übernimmt deine neuen Aufgaben.
            <a href="{{ route('two-factor.show') }}" class="hidden"></a>
            <a href="{{ route('profile.edit') }}" class="ms-2 underline">ändern</a>
        </div>
    @endif

    @if($onboarding)
        <x-card>
            <div class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Erste Schritte</h2>
                        <p class="text-xs text-slate-500">Diese Karte verschwindet, sobald alle Punkte abgehakt sind.</p>
                    </div>
                    <div class="text-sm text-slate-600">
                        <strong class="text-slate-900">{{ $onboarding['done'] }}</strong> / {{ $onboarding['total'] }} erledigt
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all"
                         style="width: {{ round($onboarding['done'] / $onboarding['total'] * 100) }}%"></div>
                </div>

                <ul class="space-y-2">
                    @foreach($onboarding['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}"
                               class="flex items-start gap-3 rounded-lg border p-3 transition
                                      {{ $item['done']
                                          ? 'border-emerald-100 bg-emerald-50/40 hover:bg-emerald-50'
                                          : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50' }}">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full
                                             {{ $item['done'] ? 'bg-emerald-500 text-white' : 'border-2 border-slate-300 bg-white' }}">
                                    @if($item['done'])
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    @endif
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium {{ $item['done'] ? 'text-emerald-900 line-through opacity-70' : 'text-slate-900' }}">
                                        {{ $item['label'] }}
                                    </div>
                                    <div class="text-xs {{ $item['done'] ? 'text-emerald-700/70' : 'text-slate-500' }}">
                                        {{ $item['hint'] }}
                                    </div>
                                </div>
                                @unless($item['done'])
                                    <span class="text-xs text-indigo-600 self-center">jetzt →</span>
                                @endunless
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-card>
    @endif

    {{-- KPI-Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card label="Offene Aufgaben" :value="$myOpenCount" tone="indigo"
            :hint="$myOverdueCount > 0 ? $myOverdueCount.' davon überfällig' : null" />
        <a href="{{ route('documents.inbox') }}" class="block">
            <x-stat-card label="Postkorb" :value="$inboxCount" tone="amber"
                hint="Dokumente ohne Workflow" />
        </a>
        <a href="{{ route('workflow-instances.index') }}" class="block">
            <x-stat-card label="Meine Vorgänge" :value="$myRecentInstances->count()" tone="emerald"
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

    {{-- Persönliche Statistik (letzte 30 Tage) — nur wenn User was entschieden hat --}}
    @if($myStats)
        <x-card title="Meine Aktivität · letzte 30 Tage" description="Was du seit {{ $myStats['since']->format('d.m.') }} entschieden hast.">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-3xl font-semibold text-slate-900">{{ $myStats['total'] }}</div>
                    <div class="text-xs text-slate-500 mt-1">Entscheidungen insgesamt</div>
                    <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                        @if($myStats['approved'])
                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-emerald-700 font-medium">{{ $myStats['approved'] }} genehmigt</span>
                        @endif
                        @if($myStats['rejected'])
                            <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-rose-700 font-medium">{{ $myStats['rejected'] }} abgelehnt</span>
                        @endif
                        @if($myStats['forwarded'])
                            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-amber-700 font-medium">{{ $myStats['forwarded'] }} weitergeleitet</span>
                        @endif
                    </div>
                </div>
                <div>
                    @php
                        $m = $myStats['avg_minutes'];
                        if ($m < 60) { $avgLabel = $m.' min'; }
                        elseif ($m < 24*60) { $avgLabel = round($m/60, 1).' h'; }
                        else { $avgLabel = round($m/(24*60), 1).' d'; }
                    @endphp
                    <div class="text-3xl font-semibold text-slate-900">{{ $avgLabel }}</div>
                    <div class="text-xs text-slate-500 mt-1">Durchschnittliche Bearbeitungszeit</div>
                    <div class="text-[11px] text-slate-400 mt-3">von „eingegangen" bis „entschieden"</div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Top-Workflows</div>
                    @if(empty($myStats['top_workflows']))
                        <p class="text-sm text-slate-500">—</p>
                    @else
                        <ul class="space-y-1.5 text-sm">
                            @foreach($myStats['top_workflows'] as $w)
                                <li class="flex items-baseline justify-between gap-2">
                                    <span class="text-slate-700 truncate">{{ $w['name'] }}</span>
                                    <span class="text-xs text-slate-500">{{ $w['count'] }}×</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </x-card>
    @endif

    @if(! empty($activityFeed))
        <x-card title="Aktivität" description="Was zuletzt für dich passiert ist oder bald wichtig wird.">
            <ul class="divide-y divide-slate-100">
                @foreach($activityFeed as $item)
                    @php
                        $toneCls = [
                            'rose' => 'bg-rose-50 text-rose-600',
                            'amber' => 'bg-amber-50 text-amber-600',
                            'indigo' => 'bg-indigo-50 text-indigo-600',
                            'emerald' => 'bg-emerald-50 text-emerald-600',
                            'slate' => 'bg-slate-100 text-slate-600',
                        ][$item['tone'] ?? 'slate'] ?? 'bg-slate-100 text-slate-600';
                        $iconSvg = [
                            'bell' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>',
                            'alert' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
                            'clock' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
                            'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.232 13.5L15 16.5m0 0 1.5 1.5M15 16.5v-3M9 11.25v6.75M5.625 6.375a3 3 0 0 1 3-3h6.75M5.625 6.375A3.375 3.375 0 0 1 9 3"/>',
                            'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
                        ][$item['icon'] ?? 'bell'] ?? '<circle cx="12" cy="12" r="9"/>';
                    @endphp
                    <li class="py-3 flex items-start gap-3">
                        <div class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $toneCls }}">
                            <svg class="h-4 w-4" fill="none" stroke-width="1.8" stroke="currentColor" viewBox="0 0 24 24">{!! $iconSvg !!}</svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline gap-2">
                                @if($item['url'])
                                    <a href="{{ $item['url'] }}" class="text-sm font-medium text-slate-900 hover:text-indigo-600 truncate">{{ $item['title'] }}</a>
                                @else
                                    <span class="text-sm font-medium text-slate-900 truncate">{{ $item['title'] }}</span>
                                @endif
                                @if(! empty($item['unread']))
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">neu</span>
                                @endif
                            </div>
                            @if(! empty($item['body']))
                                <div class="text-xs text-slate-500 truncate">{{ $item['body'] }}</div>
                            @endif
                        </div>
                        <div class="text-xs text-slate-400 shrink-0 whitespace-nowrap">
                            <x-fmt-date :value="$item['at']" format="relative" />
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Meine offenen Aufgaben" description="Direkt zugewiesen oder via Rolle. Sortiert nach Frist.">
            @if($myOpenTasks->isEmpty())
                <p class="text-sm text-slate-500">Aktuell nichts offen. </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($myOpenTasks as $step)
                        @php
                            $node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
                            $overdue = $step->due_at && $step->due_at->isPast();
                        @endphp
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
                                        {{ $overdue ? 'überfällig' : 'bis' }} <x-fmt-date :value="$step->due_at" />
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

        <x-card title="Meine letzten Vorgänge">
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
                                <div class="text-xs text-slate-500"><x-fmt-date :value="$i->started_at" format="relative" /></div>
                            </div>
                            @php $tone = ['running' => 'indigo', 'completed' => 'emerald', 'failed' => 'rose', 'cancelled' => 'slate'][$i->status] ?? 'slate'; @endphp
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
                            @php $tone = ['warn' => 'amber', 'fail' => 'rose'][$w['status']] ?? 'slate'; @endphp
                            <span class="inline-flex items-center rounded-full bg-{{ $tone }}-50 px-2 py-0.5 text-xs font-medium text-{{ $tone }}-700">{{ $w['status'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.health.index') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">Vollständig anzeigen &rarr;</a>
            </x-card>
        </div>
    @endif
</x-app-layout>
