@php
    $u = auth()->user();
    // Wizard erscheint nur für Admins, die noch nicht durch sind UND nicht dismissed haben
    $showWizard = $u && $u->hasRole('admin')
        && ! $u->onboarding_completed_at && ! $u->onboarding_dismissed_at;
    if (! $showWizard) return;
@endphp

<div x-data="{
        open: true,
        step: 1,
        steps: [
            { title: 'Willkommen!', body: 'Open Workflow Engine ist installiert. Wir führen dich kurz durch die wichtigsten Schritte (geht in ~5 Min).' },
            { title: '1. Mail-Versand', body: 'Workflow-Benachrichtigungen brauchen einen SMTP-Server. Konfiguriere ihn unter Systemeinstellungen → Kommunikation.', link: '{{ route('admin.settings.communication') }}', linkLabel: 'Mail einrichten →' },
            { title: '2. Anmeldung & SSO', body: 'Optional: Microsoft 365, OIDC, Google, SAML oder LDAP einbinden, damit Mitarbeiter sich mit ihrem Firma-Account anmelden.', link: '{{ route('admin.settings.sso') }}', linkLabel: 'SSO einrichten →' },
            { title: '3. Erste Benutzer', body: 'Lege Mitarbeiter an oder importiere sie per CSV. Service-Accounts für Integrationen mit eigenem Flag.', link: '{{ route('admin.users.index') }}', linkLabel: 'Benutzer anlegen →' },
            { title: '4. Dokumenttypen', body: 'Definiere die Archive (Rechnung, Vertrag, ...). Sie sind die Basis für Berechtigungen + Schemas + Retention.', link: '{{ route('admin.settings.documents') }}', linkLabel: 'Dokumente einrichten →' },
            { title: '5. Erster Workflow', body: 'Bau deinen ersten Workflow im Designer — oder importiere eine der drei Cookbook-Vorlagen.', link: '{{ route('workflows.index') }}', linkLabel: 'Workflows →' },
            { title: '6. Reports & Audit', body: 'Wenn Daten reinkommen, findest du KPIs unter Reports und den Berechtigungs-Report unter Administration.', link: '{{ route('reports.index') }}', linkLabel: 'Reports anschauen →' },
            { title: 'Fertig!', body: 'Du kannst die Tour jederzeit erneut aufrufen, indem du in der Hilfe nach Erste Schritte suchst.' },
        ],
        async dismiss(complete = false) {
            const url = complete ? '{{ route('onboarding.complete') }}' : '{{ route('onboarding.dismiss') }}';
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            try {
                await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            } catch (e) {}
            this.open = false;
        },
        next() {
            if (this.step < this.steps.length) this.step++;
            else this.dismiss(true);
        },
        skip() { this.dismiss(false); }
    }"
    x-show="open" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @keydown.escape.window="skip()">
    <div class="absolute inset-0 bg-slate-900/50" @click="skip()"></div>
    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200">
            <div class="text-xs text-slate-500">
                Schritt <span x-text="step"></span> / <span x-text="steps.length"></span>
            </div>
            <button @click="skip()" class="text-slate-400 hover:text-slate-700 text-xs">Später</button>
        </div>
        <div class="px-6 py-6">
            <h2 class="text-xl font-semibold text-slate-900" x-text="steps[step - 1].title"></h2>
            <p class="mt-3 text-sm text-slate-700" x-text="steps[step - 1].body"></p>
            <template x-if="steps[step - 1].link">
                <a :href="steps[step - 1].link" target="_blank" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500" x-text="steps[step - 1].linkLabel"></a>
            </template>
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button @click="if(step > 1) step--" :disabled="step === 1"
                    class="text-xs text-slate-500 hover:text-slate-900 disabled:opacity-30">← Zurück</button>
            <div class="flex gap-1.5">
                <template x-for="(s, i) in steps" :key="i">
                    <span class="inline-block h-2 w-2 rounded-full" :class="i+1 <= step ? 'bg-indigo-500' : 'bg-slate-300'"></span>
                </template>
            </div>
            <button @click="next()" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <span x-show="step < steps.length">Weiter</span>
                <span x-show="step === steps.length">Fertig 🎉</span>
            </button>
        </div>
    </div>
</div>
