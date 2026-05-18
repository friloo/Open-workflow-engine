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

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
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
            <form method="POST" action="{{ route('admin.settings.m365.test') }}" class="space-y-2">
                @csrf
                <x-primary-button>Test-Verbindung</x-primary-button>
                <x-input-error :messages="$errors->get('m365')" />
            </form>
            <p class="mt-3 text-xs text-slate-500">Holt ein App-Token via Tenant und fragt Graph nach einem Benutzer.</p>

            <hr class="my-4 border-slate-200">
            <p class="text-xs text-slate-500 mb-3">
                <strong>Benutzer-Sync</strong> erfordert in Azure AD:
                <code>User.Read.All</code> als Application-Permission mit Admin Consent.
            </p>
            <form method="POST" action="{{ route('admin.settings.m365.sync') }}" onsubmit="return confirm('Synchronisation jetzt starten?')">
                @csrf
                <x-secondary-button>Sync jetzt ausfuehren</x-secondary-button>
            </form>
            <p class="mt-3 text-xs text-slate-500">Cron: <code>php artisan m365:sync-users</code></p>
        </x-card>
    </div>

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="KI-Integration" description="OpenAI, DeepSeek oder Ollama. Wird z. B. im HTTP-Knoten genutzt, um aus API-Beschreibungen Header/Body zu generieren.">
            @php($ai = \App\Support\Settings::group('ai') + ['provider'=>'openai','base_url'=>'https://api.openai.com/v1','model'=>'gpt-4o-mini','api_key'=>''])
            <form method="POST" action="{{ route('admin.ai.update') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="ai_provider" value="Anbieter" />
                    <select id="ai_provider" name="provider" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            x-data x-on:change="
                                if ($event.target.value==='openai') { document.getElementById('ai_base_url').value='https://api.openai.com/v1'; document.getElementById('ai_model').value='gpt-4o-mini'; }
                                if ($event.target.value==='deepseek') { document.getElementById('ai_base_url').value='https://api.deepseek.com/v1'; document.getElementById('ai_model').value='deepseek-chat'; }
                                if ($event.target.value==='ollama') { document.getElementById('ai_base_url').value='http://localhost:11434/v1'; document.getElementById('ai_model').value='llama3.1'; }
                            ">
                        <option value="openai" @selected($ai['provider']==='openai')>OpenAI</option>
                        <option value="deepseek" @selected($ai['provider']==='deepseek')>DeepSeek</option>
                        <option value="ollama" @selected($ai['provider']==='ollama')>Ollama (lokal)</option>
                        <option value="custom" @selected($ai['provider']==='custom')>Anderer (OpenAI-kompatibel)</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="ai_base_url" value="Base-URL" />
                    <x-text-input id="ai_base_url" name="base_url" value="{{ $ai['base_url'] }}" />
                </div>
                <div>
                    <x-input-label for="ai_model" value="Modell" />
                    <x-text-input id="ai_model" name="model" value="{{ $ai['model'] }}" placeholder="z. B. gpt-4o-mini" />
                </div>
                <div>
                    <x-input-label for="ai_api_key" value="API-Key (bei Ollama leer)" />
                    <x-text-input id="ai_api_key" name="api_key" type="password" autocomplete="new-password" placeholder="@if(! empty($ai['api_key']))(unveraendert lassen)@endif" />
                    <p class="mt-1 text-xs text-slate-500">Verschluesselt gespeichert.</p>
                </div>
                <div class="flex gap-2">
                    <x-primary-button>Speichern</x-primary-button>
                    <button type="submit" formaction="{{ route('admin.ai.ping') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Verbindung testen</button>
                </div>
                <x-input-error :messages="$errors->get('ai')" />
            </form>
        </x-card>

        <x-card title="Branding" description="Wird zur Laufzeit auf das Layout angewendet.">
            <form method="POST" action="{{ route('admin.settings.branding.update') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="app_name" value="App-Name" />
                    <x-text-input id="app_name" name="app_name" value="{{ $branding['app_name'] }}" />
                </div>
                <div>
                    <x-input-label for="logo_text" value="Logo-Text (1-4 Buchstaben)" />
                    <x-text-input id="logo_text" name="logo_text" maxlength="4" value="{{ $branding['logo_text'] }}" />
                </div>
                <div>
                    <x-input-label for="primary_color" value="Primaerfarbe (#hex)" />
                    <x-text-input id="primary_color" name="primary_color" type="color" value="{{ $branding['primary_color'] }}" />
                </div>
                <x-primary-button>Speichern</x-primary-button>
            </form>
        </x-card>

        <x-card title="Dokumenttypen" description="Klassen fuer hochgeladene Dateien (z. B. Rechnung, Vertrag, Fuehrerschein).">
            <form method="POST" action="{{ route('admin.settings.document_types.update') }}"
                  x-data='@json(["types" => $documentTypes ?: []])'>
                @csrf
                <div class="space-y-2"
                     x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                     x-sort="types.splice($event.newIndex, 0, types.splice($event.oldIndex, 1)[0])">
                    <template x-for="(t, idx) in types" :key="idx">
                        <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2" x-sort:item="idx">
                            <span class="drag-handle cursor-grab select-none text-xs text-slate-400 px-1">⋮⋮</span>
                            <input type="text" :name="`types[${idx}]`" x-model="types[idx]" placeholder="z. B. Rechnung" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <button type="button" @click="types.splice(idx,1)" class="text-xs text-rose-600 hover:text-rose-500">×</button>
                        </div>
                    </template>
                    <button type="button" @click="types.push('')" class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Typ</button>
                </div>
                <div class="mt-3 flex justify-end">
                    <x-primary-button>Speichern</x-primary-button>
                </div>
            </form>
        </x-card>

        <x-card title="Berechtigungen je Rolle" description="Lege fest, welche Dokumenttypen eine Rolle in der Dokumenten-Suche sieht. Admin sieht immer alles.">
            @if(empty($documentTypes))
                <p class="text-sm text-slate-500">Lege zuerst Dokumenttypen oben an.</p>
            @else
                <form method="POST" action="{{ route('admin.settings.role_document_types.update') }}">
                    @csrf
                    <div class="space-y-3">
                        @foreach($roles as $role)
                            @php($allowed = $roleDocumentTypes[$role->slug] ?? [])
                            <div class="rounded-lg border border-slate-200 p-3">
                                <div class="text-sm font-medium text-slate-900">{{ $role->name }} <code class="ms-1 text-xs text-slate-500">{{ $role->slug }}</code></div>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($documentTypes as $dt)
                                        <label class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2 py-0.5 text-xs has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                            <input type="checkbox" name="roles[{{ $role->slug }}][]" value="{{ $dt }}" @checked(in_array($dt, $allowed))
                                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            {{ $dt }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 flex justify-end">
                        <x-primary-button>Berechtigungen speichern</x-primary-button>
                    </div>
                </form>
            @endif
        </x-card>

        <x-card title="Benutzerdefinierte Felder" description="Werden in der Benutzerverwaltung gerendert und sind in Workflows nutzbar.">
            <form method="POST" action="{{ route('admin.settings.custom_fields.update') }}"
                  x-data='@json(["fields" => $customFields ?: []])'>
                @csrf
                <div class="space-y-3"
                     x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                     x-sort="fields.splice($event.newIndex, 0, fields.splice($event.oldIndex, 1)[0])">
                    <template x-for="(f, idx) in fields" :key="idx">
                        <div class="rounded-lg border border-slate-200 p-3 bg-white" x-sort:item="idx">
                            <input type="hidden" :name="`fields[${idx}][key]`" x-model="f.key">
                            <input type="hidden" :name="`fields[${idx}][label]`" x-model="f.label">
                            <input type="hidden" :name="`fields[${idx}][type]`" x-model="f.type">
                            <input type="hidden" :name="`fields[${idx}][options]`" :value="(f.options || []).join('\n')">
                            <div class="flex items-center justify-between">
                                <span class="drag-handle cursor-grab select-none text-xs text-slate-400">⋮⋮ Feld <span x-text="idx+1"></span></span>
                                <button type="button" @click="fields.splice(idx,1)" class="text-xs text-rose-600 hover:text-rose-500">entfernen</button>
                            </div>
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                <input type="text" x-model="f.label" placeholder="Bezeichnung" class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <input type="text" x-model="f.key" @input="f.key = f.key.toString().toLowerCase().replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'')" placeholder="key" class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                <select x-model="f.type" class="rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="text">Text</option><option value="number">Zahl</option><option value="date">Datum</option><option value="select">Auswahl</option>
                                </select>
                            </div>
                            <template x-if="f.type==='select'">
                                <textarea :value="(f.options||[]).join('\n')" @input="f.options = $event.target.value.split('\n').map(s=>s.trim()).filter(Boolean)" rows="2" placeholder="Optionen je Zeile" class="mt-2 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            </template>
                        </div>
                    </template>
                    <button type="button" @click="fields.push({key:'feld_'+(fields.length+1), label:'Feld '+(fields.length+1), type:'text', options:[]})" class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Feld</button>
                </div>
                <div class="mt-3 flex justify-end">
                    <x-primary-button>Speichern</x-primary-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
