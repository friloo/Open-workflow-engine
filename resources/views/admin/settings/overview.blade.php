<x-app-layout>
    <x-slot name="header">Systemeinstellungen</x-slot>
    <x-slot name="subheader">Konfiguration der App. Waehle einen Bereich.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'overview'])

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('admin.settings.mail') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-900">Mail-Versand</h3>
                @if($status['mail_configured'])
                    <span class="text-xs rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5">aktiv</span>
                @else
                    <span class="text-xs rounded-full bg-slate-100 text-slate-500 px-2 py-0.5">nicht konfiguriert</span>
                @endif
            </div>
            <p class="text-sm text-slate-500">SMTP-Server fuer Workflow-Benachrichtigungen und Test-Mails.</p>
        </a>

        <a href="{{ route('admin.settings.sso') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-900">Anmeldung & SSO</h3>
                @if(! empty($status['sso_providers']))
                    <span class="text-xs rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5">{{ implode(' · ', $status['sso_providers']) }}</span>
                @else
                    <span class="text-xs rounded-full bg-slate-100 text-slate-500 px-2 py-0.5">nur lokal</span>
                @endif
            </div>
            <p class="text-sm text-slate-500">M365, OIDC (Keycloak/Authentik/Auth0/Okta), Google, SAML, LDAP/AD.</p>
        </a>

        <a href="{{ route('admin.settings.branding') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-900">Branding</h3>
                <span class="text-xs rounded-full bg-slate-100 text-slate-500 px-2 py-0.5">immer aktiv</span>
            </div>
            <p class="text-sm text-slate-500">App-Name, Logo, Primaerfarbe, Benutzer-Custom-Fields.</p>
        </a>

        <a href="{{ route('admin.settings.ai') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-900">KI</h3>
                @if($status['ai_configured'])
                    <span class="text-xs rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5">aktiv</span>
                @else
                    <span class="text-xs rounded-full bg-slate-100 text-slate-500 px-2 py-0.5">nicht konfiguriert</span>
                @endif
            </div>
            <p class="text-sm text-slate-500">OpenAI, DeepSeek, Ollama oder anderer OpenAI-kompatibler Endpoint.</p>
        </a>

        <a href="{{ route('admin.settings.documents') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-900">Dokumente</h3>
                <span class="text-xs rounded-full bg-slate-100 text-slate-500 px-2 py-0.5">{{ $status['document_types_count'] }} Archiv(e) · {{ $status['retention_rules_count'] }} Regel(n)</span>
            </div>
            <p class="text-sm text-slate-500">Archive (Dokumenttypen), Aufbewahrung, Berechtigungen pro Rolle.</p>
        </a>

        <a href="{{ route('admin.settings.sharing') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-900">Sharing</h3>
                <span class="text-xs rounded-full bg-slate-100 text-slate-500 px-2 py-0.5">immer aktiv</span>
            </div>
            <p class="text-sm text-slate-500">Cap fuer Ablauf-Datum, Pruefungs-Intervall externer Freigabe-Links.</p>
        </a>
    </div>
</x-app-layout>
