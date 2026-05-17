@php
    use App\Models\AuditLog;
    use App\Models\Role;
    use App\Models\User;
    use App\Models\Workflow;

    $authUser = auth()->user();
    $userCount = User::count();
    $roleCount = Role::count();
    $workflowActive = Workflow::active()->count();
    $workflowDraft = Workflow::where('status', 'draft')->count();
    $auditTotal = AuditLog::count();
    $recentAudit = $authUser->hasPermission('audit.view')
        ? AuditLog::with('user')->orderByDesc('id')->limit(5)->get()
        : collect();
@endphp

<x-app-layout>
    <x-slot name="header">Hallo, {{ $authUser->name }}</x-slot>
    <x-slot name="subheader">Willkommen im Open Workflow Engine Cockpit.</x-slot>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        <x-card title="Workflows" description="aktiv / im Entwurf">
            <div class="text-3xl font-semibold text-slate-900">{{ $workflowActive }} <span class="text-base text-slate-400">/ {{ $workflowDraft }}</span></div>
            @if($authUser->hasAnyPermission(['workflows.view','workflows.design']))
                <a href="{{ route('workflows.index') }}" class="mt-3 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-500">Zu den Workflows &rarr;</a>
            @endif
        </x-card>

        <x-card title="Benutzer" description="Aktive Konten im System">
            <div class="text-3xl font-semibold text-slate-900">{{ $userCount }}</div>
            @if($authUser->hasAnyPermission(['users.view']))
                <a href="{{ route('admin.users.index') }}" class="mt-3 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-500">Zur Benutzerverwaltung &rarr;</a>
            @endif
        </x-card>

        <x-card title="Rollen" description="Berechtigungspakete">
            <div class="text-3xl font-semibold text-slate-900">{{ $roleCount }}</div>
            @if($authUser->hasAnyPermission(['roles.view','roles.manage']))
                <a href="{{ route('admin.roles.index') }}" class="mt-3 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-500">Zu Rollen & Rechten &rarr;</a>
            @endif
        </x-card>

        <x-card title="Audit-Eintraege" description="Revisionssichere Historie">
            <div class="text-3xl font-semibold text-slate-900">{{ number_format($auditTotal, 0, ',', '.') }}</div>
            @if($authUser->hasPermission('audit.view'))
                <a href="{{ route('admin.audit.index') }}" class="mt-3 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-500">Zum Audit-Log &rarr;</a>
            @endif
        </x-card>
    </div>

    @if($authUser->hasPermission('audit.view'))
        <div class="mt-8">
            <x-card title="Zuletzt im Audit-Log">
                @if($recentAudit->isEmpty())
                    <p class="text-sm text-slate-500">Noch keine Eintraege.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($recentAudit as $entry)
                            <li class="py-3 flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-slate-900">{{ $entry->event }}</div>
                                    <div class="text-sm text-slate-500">{{ $entry->description }}</div>
                                </div>
                                <div class="shrink-0 text-right text-xs text-slate-500">
                                    <div>{{ $entry->user?->name ?? 'System' }}</div>
                                    <div>{{ $entry->created_at?->diffForHumans() }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @endif
</x-app-layout>
