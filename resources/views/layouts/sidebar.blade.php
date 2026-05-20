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

    // Archive (Dokumenttypen) als Sub-Eintraege unter "Dokumente".
    $documentArchives = [];
    if ($user?->hasPermission('documents.search')) {
        $visibleArchives = \App\Support\DocumentTypes::visibleForUser($user);
        $currentArchive = request()->routeIs('documents.index') ? request()->query('type') : null;
        foreach ($visibleArchives as $a) {
            $documentArchives[] = [
                'name' => $a,
                'url' => route('documents.index', ['type' => $a]),
                'active' => request()->routeIs('documents.index') && $currentArchive === $a,
            ];
        }
        $includeUnclassified = $user->hasRole('admin') || (bool) \App\Support\Settings::get('attachments.unclassified_visible_for_all', false);
        if ($includeUnclassified) {
            $documentArchives[] = [
                'name' => 'Unklassifiziert',
                'url' => route('documents.index', ['type' => '__unclassified__']),
                'active' => request()->routeIs('documents.index') && $currentArchive === '__unclassified__',
                'italic' => true,
            ];
        }
    }

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
            'when' => $user?->hasAnyPermission(['workflows.view','workflows.design','workflows.run','forms.view','forms.manage']),
            'items' => [
                ['name' => 'Workflows', 'route' => 'workflows.index', 'icon' => 'workflow', 'active' => request()->routeIs('workflows.*'), 'when' => $user?->hasAnyPermission(['workflows.view','workflows.design','workflows.run'])],
                ['name' => 'Vorgaenge', 'route' => 'workflow-instances.index', 'icon' => 'list', 'active' => request()->routeIs('workflow-instances.*'), 'when' => $user !== null],
                ['name' => 'Formulare', 'route' => 'forms.index', 'icon' => 'form', 'active' => request()->routeIs('forms.*'), 'when' => $user?->hasAnyPermission(['forms.view','forms.manage'])],
            ],
        ],
        [
            'group' => 'Stammdaten',
            'when' => $user?->hasAnyPermission(['lists.view','lists.manage','assets.view','assets.manage','documents.search']),
            'items' => [
                ['name' => 'Listen', 'route' => 'lists.index', 'icon' => 'table', 'active' => request()->routeIs('lists.*'), 'when' => $user?->hasAnyPermission(['lists.view','lists.manage'])],
                ['name' => 'Assets', 'route' => 'assets.index', 'icon' => 'badge', 'active' => request()->routeIs('assets.*'), 'when' => $user?->hasAnyPermission(['assets.view','assets.manage'])],
                ['name' => 'Dokumente', 'route' => 'documents.index', 'icon' => 'list', 'active' => request()->routeIs('documents.*'), 'when' => $user?->hasPermission('documents.search'), 'children' => $documentArchives, 'children_expanded' => request()->routeIs('documents.*')],
                ['name' => 'Akten', 'route' => 'cases.index', 'icon' => 'document', 'active' => request()->routeIs('cases.*'), 'when' => $user?->hasPermission('documents.search')],
                ['name' => 'Tags', 'route' => 'tags.index', 'icon' => 'cog', 'active' => request()->routeIs('tags.*'), 'when' => $user?->hasPermission('documents.search')],
                ['name' => 'Freigaben', 'route' => 'shares.index', 'icon' => 'list', 'active' => request()->routeIs('shares.*'), 'when' => $user?->hasAnyPermission(['shares.create','shares.manage_all'])],
            ],
        ],
        [
            'group' => 'Benutzer & Rechte',
            'when' => $user?->hasAnyPermission(['users.view','users.create','users.update','users.delete','roles.view','roles.manage','audit.view']),
            'items' => [
                ['name' => 'Benutzer', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => request()->routeIs('admin.users.*'), 'when' => $user?->hasAnyPermission(['users.view','users.create','users.update','users.delete','users.import'])],
                ['name' => 'Rollen & Rechte', 'route' => 'admin.roles.index', 'icon' => 'shield', 'active' => request()->routeIs('admin.roles.*'), 'when' => $user?->hasAnyPermission(['roles.view','roles.manage'])],
                ['name' => 'Audit-Log', 'route' => 'admin.audit.index', 'icon' => 'list', 'active' => request()->routeIs('admin.audit.*'), 'when' => $user?->hasPermission('audit.view')],
            ],
        ],
        [
            'group' => 'Integrationen',
            'when' => $user?->hasAnyPermission(['mailboxes.manage','folder_inboxes.manage','webhooks.manage','incoming_webhooks.manage','secrets.manage']),
            'items' => [
                ['name' => 'E-Mail-Postfaecher', 'route' => 'admin.mailboxes.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.mailboxes.*'), 'when' => $user?->hasPermission('mailboxes.manage')],
                ['name' => 'Folder-Inboxen', 'route' => 'admin.folder-inboxes.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.folder-inboxes.*'), 'when' => $user?->hasPermission('folder_inboxes.manage')],
                ['name' => 'Webhooks (out)', 'route' => 'admin.webhooks.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.webhooks.*'), 'when' => $user?->hasPermission('webhooks.manage')],
                ['name' => 'Webhooks (in)', 'route' => 'admin.incoming-webhooks.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.incoming-webhooks.*'), 'when' => $user?->hasPermission('incoming_webhooks.manage')],
                ['name' => 'Secrets', 'route' => 'admin.secrets.index', 'icon' => 'shield', 'active' => request()->routeIs('admin.secrets.*'), 'when' => $user?->hasPermission('secrets.manage')],
            ],
        ],
        [
            'group' => 'System',
            'when' => $user?->hasAnyPermission(['system.settings','system.health','system.update','system.backup']),
            'items' => [
                ['name' => 'Systemeinstellungen', 'route' => 'admin.settings.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.settings.*'), 'when' => $user?->hasPermission('system.settings')],
                ['name' => 'Dokument-Schemas', 'route' => 'admin.document_schemas.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.document_schemas.*'), 'when' => $user?->hasPermission('system.settings')],
                ['name' => 'API-Dokumentation', 'route' => 'admin.api_docs.index', 'icon' => 'document', 'active' => request()->routeIs('admin.api_docs.*'), 'when' => $user?->hasPermission('system.settings')],
                ['name' => 'System-Health', 'route' => 'admin.health.index', 'icon' => 'shield', 'active' => request()->routeIs('admin.health.*'), 'when' => $user?->hasPermission('system.health')],
                ['name' => 'Performance', 'route' => 'admin.perf.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.perf.*'), 'when' => $user?->hasPermission('system.health')],
                ['name' => 'System-Update', 'route' => 'admin.update.index', 'icon' => 'cog', 'active' => request()->routeIs('admin.update.*'), 'when' => $user?->hasPermission('system.update')],
                ['name' => 'Backups', 'route' => 'admin.backups.index', 'icon' => 'shield', 'active' => request()->routeIs('admin.backups.*'), 'when' => $user?->hasPermission('system.backup')],
            ],
        ],
    ];
@endphp

<aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:flex lg:w-64 lg:flex-col">
    <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-slate-200 bg-white px-6 pb-4">
        <div class="flex h-16 shrink-0 items-center gap-2">
            <div class="grid h-8 w-8 place-items-center rounded-lg text-white font-bold" style="background:{{ config('branding.primary_color', '#6366f1') }};">{{ config('branding.logo_text', 'W') }}</div>
            <span class="font-semibold text-slate-900">{{ config('app.name') }}</span>
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
                                            @if(! empty($item['children']) && ($item['children_expanded'] ?? false))
                                                <ul class="mt-1 ms-7 space-y-0.5 border-l border-slate-200 ps-3">
                                                    @foreach($item['children'] as $child)
                                                        <li>
                                                            <a href="{{ $child['url'] }}"
                                                               class="block truncate rounded-md px-2 py-1 text-xs {{ ($child['italic'] ?? false) ? 'italic' : '' }} {{ $child['active'] ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                                                {{ $child['name'] }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
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
                            @if(! empty($item['children']) && ($item['children_expanded'] ?? false))
                                <div class="ms-7 mt-1 space-y-0.5 border-l border-slate-200 ps-3">
                                    @foreach($item['children'] as $child)
                                        <a href="{{ $child['url'] }}"
                                           class="block truncate rounded-md px-2 py-1 text-xs {{ ($child['italic'] ?? false) ? 'italic' : '' }} {{ $child['active'] ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50' }}">
                                            {{ $child['name'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @endforeach
                @endif
            @endforeach
        </nav>
    </div>
</div>
