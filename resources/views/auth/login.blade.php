<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <h1 class="text-xl font-semibold text-slate-900 mb-1">{{ __('Anmelden') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ config('branding.app_name', config('app.name', 'Open Workflow Engine')) }}</p>

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
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Passwort')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
        </div>

        <div class="flex items-center justify-between text-sm">
            <label for="remember_me" class="inline-flex items-center gap-2 text-slate-700">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                {{ __('Angemeldet bleiben') }}
            </label>

            @if (Route::has('password.request'))
                <a class="text-indigo-600 hover:text-indigo-500" href="{{ route('password.request') }}">{{ __('Passwort vergessen?') }}</a>
            @endif
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center">{{ __('Anmelden') }}</x-primary-button>
        </div>
    </form>

    @php
        $ssoProviders = [
            ['enabled' => (bool) config('services.microsoft-azure.enabled'), 'route' => 'auth.m365.redirect',   'label' => 'Mit Microsoft anmelden', 'icon' => 'm365'],
            ['enabled' => (bool) config('services.oidc.enabled'),            'route' => 'auth.oidc.redirect',   'label' => config('services.oidc.button_label', 'Mit Single Sign-On anmelden'), 'icon' => 'oidc'],
            ['enabled' => (bool) config('services.google.enabled'),          'route' => 'auth.google.redirect', 'label' => 'Mit Google anmelden', 'icon' => 'google'],
            ['enabled' => (bool) config('services.saml.enabled'),            'route' => 'auth.saml.redirect',   'label' => config('services.saml.button_label', 'Mit SAML anmelden'), 'icon' => 'saml'],
        ];
        $enabledProviders = array_values(array_filter($ssoProviders, fn ($p) => $p['enabled']));
    @endphp

    @if(! empty($enabledProviders))
        <div class="my-6 flex items-center gap-3 text-xs text-slate-400">
            <span class="h-px flex-1 bg-slate-200"></span>
            <span>oder</span>
            <span class="h-px flex-1 bg-slate-200"></span>
        </div>
        <div class="space-y-2">
            @foreach($enabledProviders as $p)
                <a href="{{ route($p['route']) }}"
                    class="flex w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    @switch($p['icon'])
                        @case('m365')
                            <svg viewBox="0 0 21 21" width="18" height="18" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
                            @break
                        @case('google')
                            <svg viewBox="0 0 48 48" width="18" height="18" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 18.9 13 24 13c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-1.9 13.6-5.1l-6.3-5.2c-2 1.5-4.5 2.3-7.3 2.3-5.2 0-9.6-3.3-11.2-7.9l-6.6 5C9.6 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.2 4.3-4.1 5.7l6.3 5.2C42 35.5 44 30.1 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                            @break
                        @case('saml')
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.6 9.4 8 11 4.4-1.6 8-6 8-11V5l-8-3z"/></svg>
                            @break
                        @default
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 11c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4zM4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                    @endswitch
                    {{ $p['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</x-guest-layout>
