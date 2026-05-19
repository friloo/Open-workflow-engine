<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Microsoft 365</x-slot>
    <x-slot name="subheader">SSO-Anmeldung und Benutzer-Synchronisation aus Entra ID.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'm365'])

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card title="Microsoft 365 / Entra ID" description="SSO-Anmeldung und Benutzer-Synchronisation.">
                <form method="POST" action="{{ route('admin.settings.m365.update') }}" class="space-y-4">
                    @csrf
                    <div class="flex flex-col gap-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($m365['enabled'])>
                            Microsoft-Anmeldung aktivieren (Login-Button erscheint auf der Anmeldeseite)
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="auto_provision" value="0">
                            <input type="checkbox" name="auto_provision" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($m365['auto_provision'])>
                            Neue Benutzer beim ersten Login automatisch anlegen
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="client_id" value="Client-ID" />
                            <x-text-input id="client_id" name="client_id" value="{{ $m365['client_id'] }}" placeholder="00000000-0000-0000-0000-000000000000" autocomplete="off" />
                            <x-input-error :messages="$errors->get('client_id')" />
                        </div>
                        <div>
                            <x-input-label for="tenant_id" value="Tenant-ID" />
                            <x-text-input id="tenant_id" name="tenant_id" value="{{ $m365['tenant_id'] }}" placeholder="common, organizations oder GUID" autocomplete="off" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="client_secret" value="Client-Secret" />
                            <x-text-input id="client_secret" name="client_secret" type="password" placeholder="@if(! empty($m365['client_secret']))(unveraendert lassen)@endif" autocomplete="new-password" />
                            <p class="mt-1 text-xs text-slate-500">Verschluesselt gespeichert. Leer = bisherigen Wert beibehalten.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="redirect_uri" value="Redirect-URI" />
                            <x-text-input id="redirect_uri" name="redirect_uri" value="{{ $m365['redirect_uri'] }}" />
                            <p class="mt-1 text-xs text-slate-500">Diese URL muss exakt in der Azure-AD-App als Redirect-URI eingetragen sein.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="default_role" value="Standardrolle fuer neue Benutzer" />
                            <select id="default_role" name="default_role" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($roles as $r)
                                    <option value="{{ $r->slug }}" @selected($m365['default_role']===$r->slug)>{{ $r->name }} ({{ $r->slug }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Speichern</x-primary-button>
                    </div>
                </form>
            </x-card>
        </div>

        <x-card title="Verbindung testen" description="Prueft Credentials und Graph-Berechtigungen.">
            <form method="POST" action="{{ route('admin.settings.m365.test') }}" class="space-y-2"
                  x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button type="submit" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 disabled:cursor-wait">
                    <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-show="!busy">Test-Verbindung</span>
                    <span x-show="busy" x-cloak>Pruefe Tenant &hellip;</span>
                </button>
                <x-input-error :messages="$errors->get('m365')" />
            </form>
            <p class="mt-3 text-xs text-slate-500">Holt ein App-Token via Tenant und fragt Graph nach einem Benutzer.</p>

            <hr class="my-4 border-slate-200">
            <p class="text-xs text-slate-500 mb-3">
                <strong>Benutzer-Sync</strong> erfordert in Azure AD:
                <code>User.Read.All</code> als Application-Permission mit Admin Consent.
            </p>
            <form method="POST" action="{{ route('admin.settings.m365.sync') }}"
                  x-data="{ busy: false }"
                  @submit="if (!confirm('Synchronisation jetzt starten?')) { $event.preventDefault(); return; } busy = true">
                @csrf
                <button type="submit" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60 disabled:cursor-wait">
                    <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-show="!busy">Sync jetzt ausfuehren</span>
                    <span x-show="busy" x-cloak>Synchronisiere &hellip;</span>
                </button>
            </form>
            <p class="mt-3 text-xs text-slate-500">Cron: <code>php artisan m365:sync-users</code></p>
        </x-card>
    </div>
</x-app-layout>
