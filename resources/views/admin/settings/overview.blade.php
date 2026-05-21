<x-app-layout>
    <x-slot name="header">Systemeinstellungen</x-slot>
    <x-slot name="subheader">Konfiguration der App. Waehle einen Bereich.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'overview'])

    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
        <p class="font-semibold text-amber-900">⚠️ Hinweis fuer Administratoren</p>
        <p class="mt-1 text-amber-800">
            Open Workflow Engine wurde von
            <a href="https://loheide.eu" target="_blank" rel="noopener" class="font-medium text-amber-900 underline">Friederich Loheide (loheide.eu)</a>
            entwickelt — u. a. unter Mithilfe von KI-Code-Generierung.
        </p>
        <p class="mt-2 text-amber-800">
            <strong>Nutzung auf eigenes Risiko.</strong> Keine Gewaehr fuer Fehlerfreiheit, DSGVO-/GoBD-Konformitaet,
            Datensicherheit oder Folgeschaeden. Vor produktivem Einsatz Backups einrichten, Audit-Trail testen,
            Berechtigungen prüfen, ggf. extern auditieren lassen.
            <a href="{{ route('help.show', 'about') }}" class="font-medium text-amber-900 underline">Vollstaendiger Disclaimer →</a>
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('admin.settings.communication') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-900">Kommunikation</h3>
                @if($status['mail_configured'])
                    <span class="text-xs rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5">SMTP aktiv</span>
                @else
                    <span class="text-xs rounded-full bg-amber-100 text-amber-700 px-2 py-0.5">SMTP fehlt</span>
                @endif
            </div>
            <p class="text-sm text-slate-500">SMTP fuer Mails, IT-Support-Formular, Microsoft Teams.</p>
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
                <h3 class="text-base font-semibold text-slate-900">Dokumente & Sharing</h3>
                <span class="text-xs rounded-full bg-slate-100 text-slate-500 px-2 py-0.5">{{ $status['document_types_count'] }} Archiv(e) · {{ $status['retention_rules_count'] }} Regel(n)</span>
            </div>
            <p class="text-sm text-slate-500">Archive (Dokumenttypen), Aufbewahrung, Berechtigungen pro Rolle, Freigabe-Caps.</p>
        </a>
    </div>
</x-app-layout>
