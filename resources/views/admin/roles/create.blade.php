<x-app-layout>
    <x-slot name="header">{{ ! empty($copyFrom) ? 'Neue Rolle (Kopie von '.$copyFrom->name.')' : 'Neue Rolle' }}</x-slot>
    <x-slot name="subheader">Permissions + Dokumenttyp-Sichten + Listen-Zugriff. Optional aus bestehender Rolle vorbelegen.</x-slot>

    @if(empty($copyFrom))
        <x-card title="Aus bestehender Rolle kopieren (optional)"
                description="Spart Zeit: Permissions, Dokumenttypen und Listen-Zugriff werden vorbelegt — Name + Slug bleiben leer.">
            <form method="GET" action="{{ route('admin.roles.create') }}" class="flex items-end gap-2">
                <div class="flex-1 max-w-md">
                    <x-input-label for="copy_from" value="Quelle" />
                    <select id="copy_from" name="copy_from"
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— ohne Vorlage —</option>
                        @foreach($allRoles as $r)
                            <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->slug }})</option>
                        @endforeach
                    </select>
                </div>
                <x-secondary-button>Als Kopie anlegen</x-secondary-button>
            </form>
        </x-card>
    @endif

    <x-card>
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @include('admin.roles._form')
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.roles.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                <x-primary-button>Anlegen</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
