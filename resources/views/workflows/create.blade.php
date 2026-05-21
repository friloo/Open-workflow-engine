<x-app-layout>
    <x-slot name="header">Neuer Workflow</x-slot>
    <x-slot name="subheader">Lege Metadaten fest. Den eigentlichen Ablauf entwirfst du danach im Designer.</x-slot>

    <x-card>
        <form method="POST" action="{{ route('workflows.store') }}">
            @include('workflows._form')
            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('workflows.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                <x-primary-button>Anlegen &amp; Designer öffnen</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
