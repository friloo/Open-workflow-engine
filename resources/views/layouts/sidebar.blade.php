@php
    $user = auth()->user();
    $openTasks = $user
        ? \App\Models\WorkflowStepExecution::whereNull('completed_at')
            ->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id);
                if ($user->roles->isNotEmpty()) {
                    $q->orWhereIn('assigned_to_role_id', $user->roles->pluck('id'));
                }
            })->count()
        : 0;
    $nav = [
        [
            'group' => 'Allgemein',
            'items' => [
                ['name' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'active' => request()->routeIs('dashboard')],
                ['name' => 'Meine Aufgaben', 'route' => 'tasks.index', 'icon' => 'inbox', 'active' => request()->routeIs('tasks.*'), 'badge' => $openTasks ?: null],
            ],
        ],
        [
            'group' => 'Automatisierung',
            'when' => $user?->hasAnyPermission(['workflows.view','workflows.design','workflows.publish','workflows.run','forms.view','forms.manage']),
            'items' => [
                ['name' => 'Workflows', 'route' => 'workflows.index', 'icon' => 'workflow', 'active' => request()->routeIs('workflows.*'), 'when' => $user?->hasAnyPermission(['workflows.view','workflows.design','workflows.publish','workflows.run'])],
                ['name' => 'Vorgaenge', 'route' => 'workflow-instances.index', 'icon' => 'list', 'active' => request()->routeIs('workflow-instances.*'), 'when' => $user !== null],
                ['name' => 'Formulare', 'route' => 'forms.index', 'icon' => 'form', 'active' => request()->routeIs('forms.*'), 'when' => $user?->hasAnyPermission(['forms.view','forms.manage'])],
            ],
        ],
        [
            'group' => 'Stammdaten',
            'when' => $user?->hasAnyPermission(['lists.view','lists.manage','assets.view','assets.manage']),
            'items' => [
                ['name' => 'Listen', 'route' => 'lists.index', 'icon' => 'table', 'active' => request()->routeIs('lists.*'), 'when' => $user?->hasAnyPermission(['lists.view','lists.manage'])],
                ['name' => 'Assets', 'route' => 'assets.index', 'icon' => 'badge', 'active' => request()->routeIs('assets.*'), 'when' => $user?->hasAnyPermission(['assets.view','assets.manage'])],
            ],
        ],
        [
            'group' => 'Verwaltung',
            'when' => $user?->hasAnyPermission(['users.view','users.create','users.update','users.delete','roles.view','roles.manage','audit.view','system.settings']),
            'items' => [
                ['name' => 'Benutzer', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => request()->routeIs('admin.users.*'), 'when' => $user?->hasAnyPermission(['users.view','users.create','users.update','users.delete','users.import'])],
                ['name' => 'Rollen & Rechte', 'route' => 'admin.roles.index', 'icon' => 'shield', 'active' => request()->routeIs('admin.roles.*'), 'when' => $user?->hasAnyPermission(['roles.view','roles.manage'])],
                ['name' => 'Audit-Log', 'route' => 'admin.audit.index', 'icon' => 'list', 'active' => request()->routeIs('admin.audit.*'), 'when' => $user?->hasPermission('audit.view')],
                ['name' => 'Systemeinstellungen', 'route' => 'admin.settings.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.settings.*'), 'when' => $user?->hasPermission('system.settings')],
            ],
        ],
    ];
@endphp

<aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:flex lg:w-64 lg:flex-col">
    <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-slate-200 bg-white px-6 pb-4">
        <div class="flex h-16 shrink-0 items-center gap-2">
            <div class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-white font-bold">W</div>
            <span class="font-semibold text-slate-900">Workflow Engine</span>
        </div>

        <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
                @foreach($nav as $section)
                    @if(($section['when'] ?? true))
                        <li>
                            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $section['group'] }}</div>
                            <ul role="list" class="-mx-2 mt-2 space-y-1">
                                @foreach($section['items'] as $item)
                                    @if(($item['when'] ?? true))
                                        <li>
                                            <a href="{{ route($item['route']) }}"
                                                class="group flex items-center gap-x-3 rounded-md p-2 text-sm font-medium {{ $item['active'] ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                                @include('layouts.partials.icon', ['name' => $item['icon'], 'active' => $item['active']])
                                                <span class="flex-1">{{ $item['name'] }}</span>
                                                @if(! empty($item['badge']))
                                                    <span class="ms-auto inline-flex items-center justify-center rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $item['badge'] }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</aside>

<div x-show="sidebarOpen" x-transition.opacity class="relative z-50 lg:hidden" style="display:none;">
    <div class="fixed inset-0 bg-slate-900/80" @click="sidebarOpen = false"></div>
    <div class="fixed inset-y-0 left-0 z-50 w-72 overflow-y-auto bg-white px-6 pb-4">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-white font-bold">W</div>
                <span class="font-semibold text-slate-900">Workflow Engine</span>
            </div>
            <button @click="sidebarOpen = false" class="text-slate-500 hover:text-slate-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="mt-4">
            @foreach($nav as $section)
                @if(($section['when'] ?? true))
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-4">{{ $section['group'] }}</div>
                    @foreach($section['items'] as $item)
                        @if(($item['when'] ?? true))
                            <a href="{{ route($item['route']) }}"
                                class="mt-1 flex items-center gap-x-3 rounded-md p-2 text-sm font-medium {{ $item['active'] ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50' }}">
                                @include('layouts.partials.icon', ['name' => $item['icon'], 'active' => $item['active']])
                                <span class="flex-1">{{ $item['name'] }}</span>
                                @if(! empty($item['badge']))
                                    <span class="ms-auto inline-flex items-center justify-center rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </nav>
    </div>
</div>
