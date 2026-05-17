<x-app-layout>
    <x-slot name="header">{{ $user->name }} bearbeiten</x-slot>
    <x-slot name="subheader">{{ $user->email }}</x-slot>

    <x-card>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @method('PUT')
            @include('admin.users._form')
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
