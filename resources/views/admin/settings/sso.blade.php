<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Single Sign-On</x-slot>
    <x-slot name="subheader">OpenID Connect (Keycloak/Authentik/Auth0/Okta), Google Workspace und SAML 2.0.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'sso'])

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

        <div class="flex justify-end">
            <x-primary-button>Speichern</x-primary-button>
        </div>
    </form>
</x-app-layout>
