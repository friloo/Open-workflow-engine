<x-app-layout>
    <x-slot name="header">Neue Rolle</x-slot>
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
