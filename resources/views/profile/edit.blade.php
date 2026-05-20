<x-app-layout>
    <x-slot name="header">Mein Profil</x-slot>
    <x-slot name="subheader">Persoenliche Daten und Benachrichtigungen.</x-slot>

    <div class="space-y-6">
        <x-card title="Profilinformationen" description="Aktualisiere deinen Namen und deine E-Mail-Adresse.">
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card title="Passwort aendern" description="Stelle ein langes, zufaelliges Passwort ein, damit dein Konto sicher bleibt.">
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card title="Vertretung" description="Waehrend deiner Abwesenheit gehen alle dir zugewiesenen Aufgaben automatisch an die hier hinterlegte Person.">
            @include('profile.partials.update-delegation-form')
        </x-card>

        <x-card title="Benachrichtigungen"
                description="Welche Ereignisse loesen eine Benachrichtigung aus — und auf welchem Kanal. Default fuer neue User: alles an.">
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
    </div>
</x-app-layout>
