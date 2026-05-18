<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <h1 class="text-xl font-semibold text-slate-900 mb-1">Anmelden</h1>
    <p class="text-sm text-slate-500 mb-6">Open Workflow Engine</p>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc ps-5 space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
        </div>

        <div>
            <x-input-label for="password" value="Passwort" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
        </div>

        <div class="flex items-center justify-between text-sm">
            <label for="remember_me" class="inline-flex items-center gap-2 text-slate-700">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Angemeldet bleiben
            </label>

            @if (Route::has('password.request'))
                <a class="text-indigo-600 hover:text-indigo-500" href="{{ route('password.request') }}">Passwort vergessen?</a>
            @endif
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center">Anmelden</x-primary-button>
        </div>
    </form>

    @if(config('services.microsoft-azure.enabled'))
        <div class="my-6 flex items-center gap-3 text-xs text-slate-400">
            <span class="h-px flex-1 bg-slate-200"></span>
            <span>oder</span>
            <span class="h-px flex-1 bg-slate-200"></span>
        </div>
        <a href="{{ route('auth.m365.redirect') }}"
            class="flex w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
            <svg viewBox="0 0 21 21" width="18" height="18" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
            Mit Microsoft anmelden
        </a>
    @endif
</x-guest-layout>
