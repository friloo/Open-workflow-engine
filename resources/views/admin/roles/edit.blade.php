<x-app-layout>
    <x-slot name="header">Rolle: {{ $role->name }}</x-slot>
    <x-card>
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @method('PUT')
            @include('admin.roles._form')
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.roles.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
