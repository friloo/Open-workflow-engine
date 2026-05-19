<x-app-layout>
    <x-slot name="header">{{ $case->exists ? 'Akte bearbeiten' : 'Neue Akte' }}</x-slot>

    <form method="POST" action="{{ $case->exists ? route('cases.update', $case) : route('cases.store') }}">
        @csrf
        @if($case->exists) @method('PUT') @endif

        <x-card>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" :value="old('name', $case->name)" required />
                </div>
                <div>
                    <x-input-label for="reference" value="Referenz (z. B. Kunden-Nr.)" />
                    <x-text-input id="reference" name="reference" :value="old('reference', $case->reference)" />
                </div>
            </div>
            <div class="mt-4">
                <x-input-label for="description" value="Beschreibung" />
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $case->description) }}</textarea>
            </div>
            <div class="mt-4 flex justify-between">
                <a href="{{ route('cases.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Abbrechen</a>
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </x-card>
    </form>
</x-app-layout>
