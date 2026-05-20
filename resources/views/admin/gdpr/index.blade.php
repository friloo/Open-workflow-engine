<x-app-layout>
    <x-slot name="header">DSGVO-Anfragen</x-slot>
    <x-slot name="subheader">Auskunft (Art. 15) und Vergessenwerden (Art. 17) — Pruefer-faehiger Audit-Trail garantiert.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Auskunft --}}
        <x-card title="Auskunft (Art. 15 DSGVO)"
                description="Sammelt alle personenbezogenen Daten zu einer Email-Adresse als ZIP mit JSON-Export.">
            <form method="POST" action="{{ route('admin.gdpr.export') }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="export_email" value="Email-Adresse der betroffenen Person" />
                    <x-text-input id="export_email" name="email" type="email" required placeholder="max@example.de" />
                </div>
                <x-input-error :messages="$errors->get('email')" />
                <x-primary-button>Auskunft als ZIP herunterladen</x-primary-button>
            </form>
            <p class="mt-3 text-xs text-slate-500">
                Im ZIP: <code>export.json</code> mit User-Profil, Workflow-Vorgaengen,
                Schritt-Historie, Kommentaren, Anhang-Metadata, Audit-Log-Eintraegen,
                Saved-Searches und Notification-Praeferenzen.
                Die Aktion selbst wird im Audit-Log unter <code>gdpr.access_request</code>
                festgehalten.
            </p>
        </x-card>

        {{-- Vergessenwerden --}}
        <x-card title="Vergessenwerden (Art. 17 DSGVO)"
                description="Anonymisiert den User-Datensatz. Workflow-Historie + Belege bleiben (gesetzliche Aufbewahrung), aber Name/Email/Custom-Felder werden ersetzt.">
            <form method="POST" action="{{ route('admin.gdpr.anonymize') }}" class="space-y-3"
                  onsubmit="return confirm('Diesen User wirklich ANONYMISIEREN? Aktion ist nicht rueckgaengig zu machen.')">
                @csrf
                <div>
                    <x-input-label for="anon_email" value="Email-Adresse" />
                    <x-text-input id="anon_email" name="email" type="email" required />
                </div>
                <div>
                    <x-input-label for="anon_reason" value="Grund (Begruendung der Anonymisierung)" />
                    <textarea id="anon_reason" name="reason" rows="3" required maxlength="500"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="z. B. 'Loeschungs-Antrag der Person vom 19.05.2026'"></textarea>
                </div>
                <div>
                    <x-input-label for="anon_confirm" value="Sicherheits-Bestaetigung" />
                    <x-text-input id="anon_confirm" name="confirm_text" placeholder='Tippe "ANONYMISIEREN"' required />
                </div>
                <x-input-error :messages="$errors->get('email')" />
                <x-input-error :messages="$errors->get('confirm_text')" />
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500">
                    Anonymisieren
                </button>
            </form>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                <strong>Was passiert:</strong>
                <ul class="mt-1 list-disc pl-5 space-y-0.5">
                    <li>name → "Anonymisiert #&lt;id&gt;", email → "anonymized-&lt;id&gt;@deleted.local"</li>
                    <li>Passwort wird auf einen Random-Hash gesetzt (Login nicht mehr moeglich)</li>
                    <li>Rollen, Custom-Fields, Supervisor-Link werden geloescht</li>
                    <li>2FA, API-Tokens, Sessions, Saved-Searches, Notification-Prefs werden geloescht</li>
                    <li>Workflow-Historie + hochgeladene Belege bleiben (begruendete Aufbewahrung)</li>
                    <li>Audit-Eintrag <code>gdpr.anonymization</code> mit Before-/After-Snapshot</li>
                </ul>
            </div>
        </x-card>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
        Mehr Hintergrund unter <a href="{{ route('help.show', 'dsgvo') }}" class="text-indigo-600 hover:text-indigo-500">Anleitung „DSGVO-Anfragen"</a>.
    </div>
</x-app-layout>
