<x-app-layout>
    <x-slot name="header">Mein Profil</x-slot>
    <x-slot name="subheader">Persönliche Daten und Benachrichtigungen.</x-slot>

    <div class="space-y-6">
        <x-card title="Profilinformationen" description="Aktualisiere deinen Namen und deine E-Mail-Adresse.">
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card title="Passwort ändern" description="Stelle ein langes, zufälliges Passwort ein, damit dein Konto sicher bleibt.">
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card title="Vertretung" description="Während deiner Abwesenheit gehen alle dir zugewiesenen Aufgaben automatisch an die hier hinterlegte Person.">
            @include('profile.partials.update-delegation-form')
        </x-card>

        <x-card title="Benachrichtigungen"
                description="Welche Ereignisse lösen eine Benachrichtigung aus — und auf welchem Kanal. Default für neue User: alles an.">
            @if(session('status') === 'notifications-updated')
                <div class="mb-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    Benachrichtigungs-Einstellungen gespeichert.
                </div>
            @endif
            @if(! $user->email_notifications_enabled)
                <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <strong>E-Mail-Versand global deaktiviert</strong> in deinem Profil — auch wenn unten Mail-Haken stehen, bekommst du keine.
                </div>
            @endif
            <form method="POST" action="{{ route('profile.notifications.update') }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                                <th class="py-2 pr-4">Ereignis</th>
                                @foreach($notificationChannels as $channelKey => $channelLabel)
                                    <th class="py-2 px-3 text-center">{{ $channelLabel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($notificationCatalog as $eventKey => $meta)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <div class="text-sm font-medium text-slate-900">{{ $meta['label'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $meta['description'] }}</div>
                                    </td>
                                    @foreach($notificationChannels as $channelKey => $channelLabel)
                                        @php
                                            $matrixKey = $eventKey.':'.$channelKey;
                                            $checked = array_key_exists($matrixKey, $notificationMatrix) ? $notificationMatrix[$matrixKey] : true;
                                        @endphp
                                        <td class="py-2 px-3 text-center">
                                            <input type="checkbox" name="prefs[{{ $eventKey }}][{{ $channelKey }}]" value="1"
                                                @checked($checked)
                                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex justify-end">
                    <x-primary-button>Speichern</x-primary-button>
                </div>
            </form>
        </x-card>

        {{-- Web-Push: Browser-Notifications --}}
        @php($pushReady = !! \App\Support\Settings::get('auth.push.vapid_public'))
        <x-card title="Push-Benachrichtigungen" description="Werde direkt im Browser/Smartphone benachrichtigt, auch wenn OWE nicht offen ist.">
            @if(! $pushReady)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Push ist serverseitig noch nicht aktiviert. Der Administrator kann das per
                    <code>php artisan push:generate-vapid</code> einrichten.
                </div>
            @else
                <div x-data="{
                        supported: false,
                        subscribed: false,
                        busy: false,
                        msg: '',
                        err: '',
                        async refresh() {
                            this.supported = window.OWEPush?.isSupported() === true;
                            if (this.supported) this.subscribed = await window.OWEPush.isSubscribed();
                        },
                        async toggle() {
                            this.busy = true; this.err = ''; this.msg = '';
                            try {
                                if (this.subscribed) {
                                    await window.OWEPush.unsubscribe();
                                    this.subscribed = false;
                                    this.msg = 'Push deaktiviert.';
                                } else {
                                    await window.OWEPush.subscribe();
                                    this.subscribed = true;
                                    this.msg = 'Push aktiviert. Du bekommst ab jetzt Benachrichtigungen im Browser.';
                                }
                            } catch (e) {
                                this.err = e.message || 'Fehler.';
                            } finally { this.busy = false; }
                        }
                    }" x-init="refresh()" class="space-y-3">
                    <div x-show="! supported" x-cloak class="text-sm text-rose-700">
                        Dein Browser unterstützt keine Web-Push (z. B. iOS Safari ausserhalb des Home-Screens).
                    </div>
                    <div x-show="supported" x-cloak class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="toggle()" :disabled="busy"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60"
                            x-text="subscribed ? 'Push deaktivieren' : 'Push aktivieren'"></button>
                        <form method="POST" action="{{ route('push.test') }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Test-Push schicken</button>
                        </form>
                        <span x-show="msg" x-text="msg" class="text-xs text-emerald-700"></span>
                        <span x-show="err" x-text="err" class="text-xs text-rose-700"></span>
                    </div>
                    <p class="text-xs text-slate-500">
                        Nach „Aktivieren" fragt der Browser einmal nach Erlaubnis. Solange das Gerät
                        angemeldet ist, erhältst du für dieselben Events Push-Hinweise wie per Mail
                        / Glocke (Aufgaben zugewiesen, Eskalationen etc.) — je nach deinen
                        Benachrichtigungs-Einstellungen oben.
                    </p>
                </div>
            @endif
        </x-card>

        {{-- iCal-Feed: persönlicher Kalender-Link --}}
        @php($icalToken = auth()->user()->ical_token)
        <x-card title="Kalender-Feed (iCal)" description="Aufgaben und Vertrags-Fristen in Outlook oder Apple Calendar abonnieren.">
            @if($icalToken)
                @php($feedUrl = url('/ical/'.$icalToken.'.ics'))
                <p class="text-sm text-slate-700 mb-2">Dein Feed-Link (niemandem weitergeben, ist wie ein Passwort):</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" readonly value="{{ $feedUrl }}"
                           onclick="this.select()"
                           class="flex-1 min-w-[20rem] rounded-lg border-slate-300 bg-slate-50 text-xs font-mono shadow-sm">
                    <form method="POST" action="{{ route('profile.ical.rotate') }}" class="inline"
                          onsubmit="return confirm('Token neu erzeugen? Der alte Link wird sofort ungültig.')">
                        @csrf
                        <button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Neu erzeugen</button>
                    </form>
                    <form method="POST" action="{{ route('profile.ical.revoke') }}" class="inline"
                          onsubmit="return confirm('Feed deaktivieren?')">
                        @csrf
                        <button class="rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Deaktivieren</button>
                    </form>
                </div>
                <ul class="mt-3 text-xs text-slate-500 space-y-0.5 list-disc ms-5">
                    <li>Outlook: <em>Datei → Kontoeinstellungen → Internet-Kalender → Neu</em>, URL einfügen.</li>
                    <li>Apple Calendar: <em>Datei → Neues Kalenderabo</em>, URL einfügen.</li>
                    <li>Google Calendar: <em>Andere Kalender → Per URL hinzufügen</em>.</li>
                </ul>
            @else
                <p class="text-sm text-slate-700">Aktiviere den Feed, um alle offenen Aufgaben mit Fälligkeit und deine Vertrags-Fristen automatisch in deinen Kalender zu spielen.</p>
                <form method="POST" action="{{ route('profile.ical.rotate') }}" class="mt-3">
                    @csrf
                    <x-primary-button>iCal-Feed aktivieren</x-primary-button>
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
