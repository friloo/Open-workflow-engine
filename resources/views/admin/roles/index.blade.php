<x-app-layout>
    <x-slot name="header">Rollen & Rechte</x-slot>
    <x-slot name="subheader">Berechtigungen werden Rollen zugewiesen, nicht einzelnen Personen.</x-slot>

    <div class="mb-4 flex justify-end">
        @if(auth()->user()->hasPermission('roles.manage'))
            <a href="{{ route('admin.roles.create') }}"><x-primary-button type="button">Neue Rolle</x-primary-button></a>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($roles as $r)
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-semibold text-slate-900">{{ $r->name }}</h3>
                            @if($r->is_system)
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">System</span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-500">{{ $r->description ?? '—' }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $r->users_count }} zugewiesene Benutzer</p>
                    </div>
                    <div class="text-right space-y-2 shrink-0">
                        @if(auth()->user()->hasPermission('roles.manage'))
                            <a href="{{ route('admin.roles.edit', $r) }}" class="block text-sm text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                            <a href="{{ route('admin.roles.create', ['copy_from' => $r->id]) }}" class="block text-sm text-slate-600 hover:text-slate-900">Kopieren</a>
                            @if(! $r->is_system)
                                <form method="POST" action="{{ route('admin.roles.destroy', $r) }}" onsubmit="return confirm('Rolle wirklich loeschen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @forelse($r->permissions as $p)
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $p->slug }}</span>
                    @empty
                        <span class="text-xs text-slate-400">Keine Permissions</span>
                    @endforelse
                </div>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
