<x-guest-layout>
    <h1 class="text-xl font-semibold text-slate-900 mb-1">Zwei-Faktor-Verifizierung</h1>
    <p class="text-sm text-slate-500 mb-6">Bitte gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc ps-5 space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="code" value="Code" />
            <x-text-input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus placeholder="123 456" class="font-mono text-lg" />
        </div>
        <div class="pt-2">
            <x-primary-button class="w-full justify-center">Bestaetigen</x-primary-button>
        </div>
    </form>

    <details class="mt-6 text-sm text-slate-600">
        <summary class="cursor-pointer text-slate-700 hover:text-slate-900">Stattdessen einen Recovery-Code verwenden</summary>
        <form method="POST" action="{{ route('two-factor.challenge') }}" class="mt-3 space-y-3">
            @csrf
            <x-text-input name="recovery_code" placeholder="XXXXX-XXXXX" class="font-mono w-full" autocomplete="off" />
            <x-primary-button class="w-full justify-center">Recovery-Code einloesen</x-primary-button>
        </form>
    </details>
</x-guest-layout>
