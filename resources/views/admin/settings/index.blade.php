<x-app-layout>
    <x-slot name="header">Systemeinstellungen</x-slot>
    <x-slot name="subheader">Mail-Versand fuer Benachrichtigungen aus Workflows konfigurieren.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card title="SMTP / Mail-Versand" description="Wird auf .env-Werte angewendet, sobald gespeichert.">
                <form method="POST" action="{{ route('admin.settings.mail.update') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="transport" value="Transport" />
                            <select id="transport" name="transport" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="smtp" @selected($mail['transport']==='smtp')>SMTP</option>
                                <option value="log" @selected($mail['transport']==='log')>Log (keine Zustellung)</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="host" value="Host" />
                            <x-text-input id="host" name="host" value="{{ $mail['host'] ?? '' }}" placeholder="smtp.example.com" />
                            <x-input-error :messages="$errors->get('host')" />
                        </div>
                        <div>
                            <x-input-label for="port" value="Port" />
                            <x-text-input id="port" name="port" type="number" value="{{ $mail['port'] ?? 587 }}" />
                        </div>
                        <div>
                            <x-input-label for="encryption" value="Verschluesselung" />
                            <select id="encryption" name="encryption" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="" @selected(empty($mail['encryption']))>Keine</option>
                                <option value="tls" @selected(($mail['encryption'] ?? '')==='tls')>STARTTLS</option>
                                <option value="ssl" @selected(($mail['encryption'] ?? '')==='ssl')>SSL/TLS</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="username" value="Benutzername" />
                            <x-text-input id="username" name="username" value="{{ $mail['username'] ?? '' }}" autocomplete="off" />
                        </div>
                        <div>
                            <x-input-label for="password" value="Passwort" />
                            <x-text-input id="password" name="password" type="password" placeholder="@if(! empty($mail['password']))(unveraendert lassen)@endif" autocomplete="new-password" />
                            <p class="mt-1 text-xs text-slate-500">Verschluesselt gespeichert. Leer = bisherigen Wert beibehalten.</p>
                        </div>
                        <div>
                            <x-input-label for="from_address" value="Absender-E-Mail" />
                            <x-text-input id="from_address" name="from_address" type="email" value="{{ $mail['from_address'] ?? '' }}" required />
                            <x-input-error :messages="$errors->get('from_address')" />
                        </div>
                        <div>
                            <x-input-label for="from_name" value="Absender-Name" />
                            <x-text-input id="from_name" name="from_name" value="{{ $mail['from_name'] ?? config('app.name') }}" required />
                        </div>
                        <div>
                            <x-input-label for="timeout" value="Timeout (Sekunden)" />
                            <x-text-input id="timeout" name="timeout" type="number" value="{{ $mail['timeout'] ?? 10 }}" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Speichern</x-primary-button>
                    </div>
                </form>
            </x-card>
        </div>

        <x-card title="Test-Mail" description="Sendet eine Test-Nachricht mit den oben gespeicherten Einstellungen.">
            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="to" value="An (E-Mail)" />
                    <x-text-input id="to" name="to" type="email" value="{{ auth()->user()->email }}" required />
                    <x-input-error :messages="$errors->get('to')" />
                    <x-input-error :messages="$errors->get('mail')" />
                </div>
                <x-primary-button>Test-Mail senden</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
