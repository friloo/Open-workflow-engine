<x-app-layout>
    <x-slot name="header">Benutzerverwaltung</x-slot>
    <x-slot name="subheader">Konten, Vorgesetzte und Rollen pflegen.</x-slot>

    <div class="mb-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <form method="GET" class="flex gap-2 max-w-md w-full">
            <x-text-input name="q" value="{{ $search }}" placeholder="Name, E-Mail, Abteilung..." />
            <x-secondary-button type="submit">Suchen</x-secondary-button>
        </form>
        <div class="flex gap-2">
            @if(auth()->user()->hasPermission('users.import'))
                <a href="{{ route('admin.users.import') }}">
                    <x-secondary-button type="button">CSV-Import</x-secondary-button>
                </a>
            @endif
            @if(auth()->user()->hasPermission('users.create'))
                <a href="{{ route('admin.users.create') }}">
                    <x-primary-button type="button">Neuer Benutzer</x-primary-button>
                </a>
            @endif
        </div>
    </div>

    <x-card>
        @if($users->isEmpty())
            <x-empty-state title="Keine Benutzer gefunden" description="Aenderst du gerade Filter, probier sie zurückzusetzen — oder lege einen ersten Benutzer an." />
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">E-Mail</th>
                        <th class="py-2 pr-4">Abteilung</th>
                        <th class="py-2 pr-4">Vorgesetzter</th>
                        <th class="py-2 pr-4">Rollen</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $u->name }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ $u->email }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ $u->department ?? '—' }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ $u->supervisor?->name ?? '—' }}</td>
                            <td class="py-3 pr-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($u->roles as $r)
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $r->name }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-3 pr-4">
                                @if($u->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">inaktiv</span>
                                @endif
                                @if($u->isServiceAccount())
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700" title="API-/Service-Konto">Service</span>
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                @if(auth()->user()->hasPermission('users.update'))
                                    <a href="{{ route('admin.users.edit', $u) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                @endif
                                @if(auth()->user()->hasPermission('users.delete') && $u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline ms-3" onsubmit="return confirm('Benutzer wirklich löschen?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm font-medium text-rose-600 hover:text-rose-500">Löschen</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
