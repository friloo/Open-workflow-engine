<x-app-layout>
    <x-slot name="header">Zwei-Faktor-Anmeldung</x-slot>
    <x-slot name="subheader">Per Authenticator-App (TOTP). Optional, jeder Benutzer entscheidet selbst.</x-slot>

    <x-card>
        @if(auth()->user()->hasTwoFactorEnabled())
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm">
                <strong>2FA ist aktiviert.</strong> Beim naechsten Login wird zusaetzlich der Code abgefragt.
            </div>

            @if($recoveryCodes)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-semibold mb-2">Neue Recovery-Codes (nur jetzt angezeigt!)</p>
                    <ul class="grid grid-cols-2 gap-2 font-mono text-xs">
                        @foreach($recoveryCodes as $c)
                            <li class="rounded bg-white px-2 py-1 border border-amber-200">{{ $c }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-3">Speicher die Codes z. B. im Passwort-Manager. Jeder Code funktioniert genau einmal.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('two-factor.recovery') }}" class="mt-4">
                @csrf
                <x-secondary-button>Recovery-Codes neu erzeugen</x-secondary-button>
            </form>

            <hr class="my-6 border-slate-200">

            <form method="POST" action="{{ route('two-factor.disable') }}" class="space-y-3" onsubmit="return confirm('2FA wirklich deaktivieren?')">
                @csrf @method('DELETE')
                <div>
                    <x-input-label for="password" value="Passwort zur Bestaetigung" />
                    <x-text-input id="password" name="password" type="password" autocomplete="current-password" required />
                </div>
                <button class="inline-flex items-center rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">2FA deaktivieren</button>
            </form>
        @else
            <p class="text-sm text-slate-700 mb-4">Scanne den QR-Code mit deiner Authenticator-App (z. B. Aegis, Google Authenticator, 1Password) und gib einmal den 6-stelligen Code ein, um die Einrichtung abzuschliessen.</p>

            @if($pending)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                    <div class="rounded-lg border border-slate-200 p-4 bg-white inline-flex justify-center">
                        {!! $pending['qr'] !!}
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Geheimer Schluessel (manuell eingeben)</label>
                            <code class="mt-1 inline-block break-all rounded bg-slate-100 px-2 py-1 text-xs">{{ $pending['secret'] }}</code>
                        </div>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="code" value="Code aus App" />
                                <x-text-input id="code" name="code" required autocomplete="one-time-code" inputmode="numeric" class="font-mono" />
                            </div>
                            <x-primary-button>Aktivieren</x-primary-button>
                        </form>
                    </div>
                </div>
            @endif
        @endif
    </x-card>
</x-app-layout>
