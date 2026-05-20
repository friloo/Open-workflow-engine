<x-app-layout>
    <x-slot name="header">
        Systemeinstellungen · Anmeldung & SSO
        <x-help-hint topic="sso" label="Anleitung Anmeldung &amp; SSO" />
    </x-slot>
    <x-slot name="subheader">Microsoft 365, OpenID Connect (Keycloak/Authentik/Auth0/Okta), Google Workspace, SAML 2.0 und LDAP/Active Directory — alles in einer Uebersicht.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'sso'])

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    {{-- Microsoft 365 / Entra ID — eigenes Form (separate Test+Sync-Actions) --}}
    <div id="m365" class="mb-6">
        <x-card title="Microsoft 365 / Entra ID"
                description="SSO-Anmeldung und optionaler Benutzer-Sync via Microsoft Graph.">
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

                <div class="flex flex-wrap justify-end gap-2">
                    <x-primary-button>Microsoft-365-Konfiguration speichern</x-primary-button>
                </div>
            </form>

            <div class="mt-4 border-t border-slate-200 pt-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.settings.m365.test') }}" class="inline-block">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Verbindung testen
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.settings.m365.sync') }}" class="inline-block"
                      onsubmit="return confirm('Benutzer-Synchronisation jetzt starten?')">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Benutzer-Sync jetzt ausfuehren
                    </button>
                </form>
                <span class="text-xs text-slate-500 self-center">
                    Cron: <code>php artisan m365:sync-users</code> &mdash;
                    Benoetigt <code>User.Read.All</code> als Application-Permission mit Admin-Consent.
                </span>
                <x-input-error :messages="$errors->get('m365')" />
            </div>
        </x-card>
    </div>

    <form method="POST" action="{{ route('admin.settings.sso.update') }}" class="space-y-6">
        @csrf

        {{-- OIDC --}}
        <x-card title="OpenID Connect (generisch)"
                description="Funktioniert mit Keycloak, Authentik, Auth0, Okta, Zitadel und allen anderen OIDC-kompatiblen Providern. Discovery-Endpoint wird automatisch verwendet.">
            <div class="space-y-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="oidc_enabled" value="0">
                    <input type="checkbox" name="oidc_enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($oidc['enabled'])>
                    OIDC-Anmeldung aktivieren (Login-Button erscheint auf der Anmeldeseite)
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="oidc_auto_provision" value="0">
                    <input type="checkbox" name="oidc_auto_provision" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($oidc['auto_provision'])>
                    Neue Benutzer beim ersten Login automatisch anlegen
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-input-label for="oidc_issuer" value="Issuer-URL" />
                        <x-text-input id="oidc_issuer" name="oidc_issuer" value="{{ $oidc['issuer'] }}"
                                      placeholder="https://id.example.com/realms/main" />
                        <p class="mt-1 text-xs text-slate-500">Discovery wird unter <code>&lt;issuer&gt;/.well-known/openid-configuration</code> abgerufen.</p>
                        <x-input-error :messages="$errors->get('oidc_issuer')" />
                    </div>
                    <div>
                        <x-input-label for="oidc_client_id" value="Client-ID" />
                        <x-text-input id="oidc_client_id" name="oidc_client_id" value="{{ $oidc['client_id'] }}" autocomplete="off" />
                    </div>
                    <div>
                        <x-input-label for="oidc_client_secret" value="Client-Secret" />
                        <x-text-input id="oidc_client_secret" name="oidc_client_secret" type="password"
                                      placeholder="@if(! empty($oidc['client_secret']))(unveraendert lassen)@endif" autocomplete="new-password" />
                        <p class="mt-1 text-xs text-slate-500">Verschluesselt gespeichert. Leer = unveraendert.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="oidc_redirect" value="Redirect-URI" />
                        <x-text-input id="oidc_redirect" name="oidc_redirect" value="{{ $oidc['redirect'] }}" />
                        <p class="mt-1 text-xs text-slate-500">Diese URL beim IdP als zulaessige Redirect-URI eintragen.</p>
                    </div>
                    <div>
                        <x-input-label for="oidc_scopes" value="Scopes" />
                        <x-text-input id="oidc_scopes" name="oidc_scopes" value="{{ $oidc['scopes'] }}" placeholder="openid email profile" />
                    </div>
                    <div>
                        <x-input-label for="oidc_button_label" value="Button-Text auf Login" />
                        <x-text-input id="oidc_button_label" name="oidc_button_label" value="{{ $oidc['button_label'] }}" />
                    </div>
                    <div>
                        <x-input-label for="oidc_default_role" value="Standardrolle fuer neue Benutzer" />
                        <select id="oidc_default_role" name="oidc_default_role" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($roles as $r)
                                <option value="{{ $r->slug }}" @selected($oidc['default_role']===$r->slug)>{{ $r->name }} ({{ $r->slug }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Google --}}
        <x-card title="Google Workspace"
                description="SSO fuer Google-Workspace-Konten. Mit Hosted-Domain laesst sich auf einen Tenant einschraenken (z. B. firma.de).">
            <div class="space-y-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="google_enabled" value="0">
                    <input type="checkbox" name="google_enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($google['enabled'])>
                    Google-Anmeldung aktivieren
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="google_auto_provision" value="0">
                    <input type="checkbox" name="google_auto_provision" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($google['auto_provision'])>
                    Neue Benutzer beim ersten Login automatisch anlegen
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="google_client_id" value="Client-ID" />
                        <x-text-input id="google_client_id" name="google_client_id" value="{{ $google['client_id'] }}" autocomplete="off" />
                    </div>
                    <div>
                        <x-input-label for="google_client_secret" value="Client-Secret" />
                        <x-text-input id="google_client_secret" name="google_client_secret" type="password"
                                      placeholder="@if(! empty($google['client_secret']))(unveraendert lassen)@endif" autocomplete="new-password" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="google_redirect" value="Redirect-URI" />
                        <x-text-input id="google_redirect" name="google_redirect" value="{{ $google['redirect'] }}" />
                    </div>
                    <div>
                        <x-input-label for="google_hosted_domain" value="Hosted-Domain (optional)" />
                        <x-text-input id="google_hosted_domain" name="google_hosted_domain" value="{{ $google['hosted_domain'] }}" placeholder="firma.de" />
                        <p class="mt-1 text-xs text-slate-500">Leer lassen, um alle Google-Konten zuzulassen.</p>
                    </div>
                    <div>
                        <x-input-label for="google_default_role" value="Standardrolle" />
                        <select id="google_default_role" name="google_default_role" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($roles as $r)
                                <option value="{{ $r->slug }}" @selected($google['default_role']===$r->slug)>{{ $r->name }} ({{ $r->slug }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- SAML --}}
        <x-card title="SAML 2.0"
                description="SP-initiated SAML 2.0. Service-Provider-Metadaten unter /auth/saml/metadata herunterladen und beim IdP einreichen.">
            <div class="space-y-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="saml_enabled" value="0">
                    <input type="checkbox" name="saml_enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($saml['enabled'])>
                    SAML-Anmeldung aktivieren
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="saml_auto_provision" value="0">
                    <input type="checkbox" name="saml_auto_provision" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($saml['auto_provision'])>
                    Neue Benutzer beim ersten Login automatisch anlegen
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="saml_idp_entity_id" value="IdP Entity-ID" />
                        <x-text-input id="saml_idp_entity_id" name="saml_idp_entity_id" value="{{ $saml['idp_entity_id'] }}" />
                    </div>
                    <div>
                        <x-input-label for="saml_idp_sso_url" value="IdP SingleSignOn-URL" />
                        <x-text-input id="saml_idp_sso_url" name="saml_idp_sso_url" value="{{ $saml['idp_sso_url'] }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="saml_idp_x509_cert" value="IdP X.509-Zertifikat (PEM)" />
                        <textarea id="saml_idp_x509_cert" name="saml_idp_x509_cert" rows="6"
                                  class="block w-full rounded-lg border-slate-300 text-xs font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="-----BEGIN CERTIFICATE-----&#10;MIIC...&#10;-----END CERTIFICATE-----">{{ $saml['idp_x509_cert'] }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="saml_sp_entity_id" value="SP Entity-ID (eigener)" />
                        <x-text-input id="saml_sp_entity_id" name="saml_sp_entity_id" value="{{ $saml['sp_entity_id'] }}" />
                    </div>
                    <div>
                        <x-input-label for="saml_email_attribute" value="Attribut fuer E-Mail" />
                        <x-text-input id="saml_email_attribute" name="saml_email_attribute" value="{{ $saml['email_attribute'] }}" />
                    </div>
                    <div>
                        <x-input-label for="saml_name_attribute" value="Attribut fuer Anzeigename" />
                        <x-text-input id="saml_name_attribute" name="saml_name_attribute" value="{{ $saml['name_attribute'] }}" />
                    </div>
                    <div>
                        <x-input-label for="saml_default_role" value="Standardrolle" />
                        <select id="saml_default_role" name="saml_default_role" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($roles as $r)
                                <option value="{{ $r->slug }}" @selected($saml['default_role']===$r->slug)>{{ $r->name }} ({{ $r->slug }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2 text-slate-700">
                        <input type="hidden" name="saml_want_assertions_signed" value="0">
                        <input type="checkbox" name="saml_want_assertions_signed" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($saml['want_assertions_signed'])>
                        Signierte Assertions verlangen
                    </label>
                    <label class="inline-flex items-center gap-2 text-slate-700">
                        <input type="hidden" name="saml_want_messages_signed" value="0">
                        <input type="checkbox" name="saml_want_messages_signed" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($saml['want_messages_signed'])>
                        Signierte Nachrichten verlangen
                    </label>
                </div>

                <p class="text-xs text-slate-500">
                    Metadaten-Endpoint (zum Import beim IdP):
                    <a href="{{ route('auth.saml.metadata') }}" target="_blank" class="text-indigo-600 hover:text-indigo-500">{{ route('auth.saml.metadata') }}</a>
                </p>
            </div>
        </x-card>

        {{-- LDAP / Active Directory --}}
        <x-card title="LDAP / Active Directory"
                description="Direkte Bind-Authentifizierung gegen LDAP-Server (Active Directory, OpenLDAP, 389DS, Samba 4). Kein Redirect — User melden sich mit AD-Login direkt auf der OWE-Loginseite an.">
            @if(! $ldapExtensionLoaded)
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>Hinweis:</strong> PHP-LDAP-Extension nicht geladen.
                    Bitte <code>php-ldap</code> auf dem Server installieren
                    (Debian/Ubuntu: <code>apt install php8.2-ldap</code>).
                    Konfiguration kannst du trotzdem speichern; aktiviert wird LDAP
                    erst nach Installation der Extension.
                </div>
            @endif

            <div class="space-y-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="ldap_enabled" value="0">
                    <input type="checkbox" name="ldap_enabled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($ldap['enabled'])>
                    LDAP-Anmeldung aktivieren (auf der Login-Seite Username/Passwort gegen AD pruefen)
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="ldap_auto_provision" value="0">
                    <input type="checkbox" name="ldap_auto_provision" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($ldap['auto_provision'])>
                    Neue Benutzer beim ersten Login automatisch anlegen
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <x-input-label for="ldap_host" value="Host" />
                        <x-text-input id="ldap_host" name="ldap_host" value="{{ $ldap['host'] }}"
                                      placeholder="ldap.firma.local oder ldaps://ldap.firma.local" />
                    </div>
                    <div>
                        <x-input-label for="ldap_port" value="Port" />
                        <x-text-input id="ldap_port" name="ldap_port" type="number" value="{{ $ldap['port'] }}" />
                        <p class="mt-1 text-xs text-slate-500">389 = LDAP, 636 = LDAPS.</p>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="ldap_use_tls" value="0">
                            <input type="checkbox" name="ldap_use_tls" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($ldap['use_tls'])>
                            StartTLS auf Port 389 verwenden (empfohlen, wenn nicht schon LDAPS auf 636)
                        </label>
                    </div>
                    <div class="sm:col-span-3">
                        <x-input-label for="ldap_base_dn" value="Base-DN" />
                        <x-text-input id="ldap_base_dn" name="ldap_base_dn" value="{{ $ldap['base_dn'] }}"
                                      placeholder="DC=firma,DC=local" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="ldap_bind_dn" value="Service-Account DN" />
                        <x-text-input id="ldap_bind_dn" name="ldap_bind_dn" value="{{ $ldap['bind_dn'] }}"
                                      placeholder="CN=svc-owe,OU=Service-Accounts,DC=firma,DC=local" />
                    </div>
                    <div>
                        <x-input-label for="ldap_bind_password" value="Service-Account Passwort" />
                        <x-text-input id="ldap_bind_password" name="ldap_bind_password" type="password"
                                      placeholder="@if(! empty($ldap['bind_password']))(unveraendert lassen)@endif" autocomplete="new-password" />
                    </div>
                    <div class="sm:col-span-3">
                        <x-input-label for="ldap_user_filter" value="User-Filter ({username} wird ersetzt)" />
                        <x-text-input id="ldap_user_filter" name="ldap_user_filter" value="{{ $ldap['user_filter'] }}" />
                        <p class="mt-1 text-xs text-slate-500">
                            AD: <code>(&(objectClass=user)(sAMAccountName={username}))</code>,
                            OpenLDAP: <code>(&(objectClass=inetOrgPerson)(uid={username}))</code>.
                            <code>{username}</code> wird RFC4515-eskapiert.
                        </p>
                    </div>
                    <div>
                        <x-input-label for="ldap_email_attribute" value="Attribut fuer E-Mail" />
                        <x-text-input id="ldap_email_attribute" name="ldap_email_attribute" value="{{ $ldap['email_attribute'] }}" />
                    </div>
                    <div>
                        <x-input-label for="ldap_name_attribute" value="Attribut fuer Anzeigename" />
                        <x-text-input id="ldap_name_attribute" name="ldap_name_attribute" value="{{ $ldap['name_attribute'] }}" />
                    </div>
                    <div>
                        <x-input-label for="ldap_default_role" value="Standardrolle" />
                        <select id="ldap_default_role" name="ldap_default_role" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($roles as $r)
                                <option value="{{ $r->slug }}" @selected($ldap['default_role']===$r->slug)>{{ $r->name }} ({{ $r->slug }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </x-card>

        <div class="flex justify-end">
            <x-primary-button>Speichern</x-primary-button>
        </div>
    </form>

    {{-- Separate Test-Form ausserhalb des Save-Forms damit man speichern + testen unabhaengig kann --}}
    <x-card title="LDAP-Verbindung testen"
            description="Pruefe die Konfiguration mit einem echten AD-User. Passwort wird nirgendwo gespeichert.">
        <form method="POST" action="{{ route('admin.settings.sso.test_ldap') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <x-input-label for="test_username" value="Test-Username (AD-Login)" />
                <x-text-input id="test_username" name="test_username" autocomplete="off" />
            </div>
            <div>
                <x-input-label for="test_password" value="Test-Passwort" />
                <x-text-input id="test_password" name="test_password" type="password" autocomplete="new-password" />
            </div>
            <div>
                <x-primary-button>Verbindung testen</x-primary-button>
            </div>
        </form>
        <x-input-error :messages="$errors->get('ldap')" />
    </x-card>
</x-app-layout>
