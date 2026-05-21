<x-app-layout>
    <x-slot name="header">{{ $user->name }} bearbeiten</x-slot>
    <x-slot name="subheader">{{ $user->email }}@if($user->isServiceAccount()) · <span class="text-amber-700">Service-Account</span>@endif</x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-end gap-2 text-sm">
        <a href="{{ route('admin.users.tokens.index', $user) }}"
           class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">
            API-Tokens verwalten ({{ $user->apiTokens()->whereNull('revoked_at')->count() }})
        </a>
    </div>

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
