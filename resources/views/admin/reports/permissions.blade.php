<x-app-layout>
    <x-slot name="header">
        Berechtigungs-Report
        <x-help-hint topic="admin" label="Anleitung" />
    </x-slot>
    <x-slot name="subheader">Wer ist in welcher Rolle? Welche Permissions hat welche Rolle? Knopf-Druck-Export fuer Audit + Compliance.</x-slot>

    <div class="mb-4 flex flex-wrap justify-end gap-2">
        <a href="{{ route('admin.reports.permissions.csv') }}"
           class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
            ⬇ CSV-Export
        </a>
        <a href="{{ route('admin.reports.permissions.pdf') }}"
           class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
            ⬇ PDF-Export
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Benutzer und ihre Rollen" description="Pro User: alle zugewiesenen Rollen.">
            <table class="min-w-full text-sm divide-y divide-slate-200">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2 pr-4">User</th>
                        <th class="py-2 pr-4">Rollen</th>
                        <th class="py-2 pr-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        <tr>
                            <td class="py-2 pr-4">
                                <div class="font-medium text-slate-900">{{ $u->name }}</div>
                                <div class="text-xs text-slate-500">{{ $u->email }}</div>
                            </td>
                            <td class="py-2 pr-4">
                                @forelse($u->roles as $r)
                                    <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700 me-1 mb-1">{{ $r->name }}</span>
                                @empty
                                    <span class="text-xs text-slate-400">— keine —</span>
                                @endforelse
                            </td>
                            <td class="py-2 pr-4 text-xs">
                                @if($u->is_service_account)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Service</span>
                                @elseif(! $u->is_active)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">inaktiv</span>
                                @else
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">aktiv</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        <x-card title="Rollen und ihre Permissions" description="Pro Rolle: alle Berechtigungen (gruppiert).">
            @foreach($roles as $r)
                <details class="border-b border-slate-100 py-2">
                    <summary class="flex items-center justify-between gap-3 cursor-pointer hover:bg-slate-50 px-1 py-1 rounded">
                        <div>
                            <span class="font-medium text-slate-900">{{ $r->name }}</span>
                            <span class="text-xs text-slate-500 ms-2">{{ $r->slug }}</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ $r->permissions->count() }} Permissions ·
                            {{ $r->users_count }} User
                        </div>
                    </summary>
                    <div class="mt-2 ms-2 text-xs">
                        @php($byGroup = $r->permissions->groupBy(fn ($p) => $p->group ?: 'Sonstige'))
                        @forelse($byGroup as $group => $perms)
                            <div class="mt-1">
                                <div class="text-[10px] uppercase font-semibold text-slate-400">{{ $group }}</div>
                                @foreach($perms as $p)
                                    <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 me-1 mb-1"
                                          title="{{ $p->slug }}">{{ $p->name }}</span>
                                @endforeach
                            </div>
                        @empty
                            <p class="text-slate-400">Keine Permissions.</p>
                        @endforelse
                    </div>
                </details>
            @endforeach
        </x-card>
    </div>

    <x-card title="Vollstaendige Matrix" description="Pro User → Rolle(n) → Permission(s). Lange Tabelle, ideal als CSV-Export.">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-slate-200">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2 pr-4">User</th>
                        <th class="py-2 pr-4">Rolle</th>
                        <th class="py-2 pr-4">Permission-Gruppe</th>
                        <th class="py-2 pr-4">Permission</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        @forelse($u->roles as $r)
                            @php($byGroup = $r->permissions->groupBy(fn ($p) => $p->group ?: 'Sonstige'))
                            @forelse($byGroup as $group => $perms)
                                @foreach($perms as $p)
                                    <tr>
                                        <td class="py-1.5 pr-4 text-slate-900">{{ $u->name }}</td>
                                        <td class="py-1.5 pr-4 text-slate-700">{{ $r->name }}</td>
                                        <td class="py-1.5 pr-4 text-xs text-slate-500">{{ $group }}</td>
                                        <td class="py-1.5 pr-4 text-xs"><code>{{ $p->slug }}</code> — {{ $p->name }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td class="py-1.5 pr-4 text-slate-900">{{ $u->name }}</td>
                                    <td class="py-1.5 pr-4 text-slate-700">{{ $r->name }}</td>
                                    <td class="py-1.5 pr-4 text-xs text-slate-400" colspan="2">— Rolle hat keine Permissions —</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td class="py-1.5 pr-4 text-slate-900">{{ $u->name }}</td>
                                <td class="py-1.5 pr-4 text-xs text-slate-400" colspan="3">— keine Rolle —</td>
                            </tr>
                        @endforelse
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
