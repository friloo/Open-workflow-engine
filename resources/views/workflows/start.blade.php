<x-app-layout>
    <x-slot name="header">{{ $workflow->name }} starten</x-slot>
    <x-slot name="subheader">{{ $workflow->description }}</x-slot>

    <x-card>
        <form method="POST" enctype="multipart/form-data" action="{{ route('workflows.start.submit', $workflow) }}" class="space-y-4">
            @csrf
            @include('workflows._form_fields', ['schema' => $schema])
            <div class="pt-2 flex justify-end gap-3">
                <a href="{{ route('workflows.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                <x-primary-button>Workflow starten</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
