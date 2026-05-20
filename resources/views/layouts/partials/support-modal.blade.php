@php
    $supportLabel = (string) (\App\Support\Settings::get('support.sidebar_label', '') ?: 'IT-Support');
@endphp

{{-- Support-Modal: in app-layout x-data steckt $supportOpen.
     Bei Klick auf das Lifebuoy-Icon im Topbar gehts auf.
     Beim Submit wird die aktuelle URL automatisch unten an die
     Description angehaengt. Submit per fetch — bei Erfolg zeigt
     das Modal eine kurze Bestaetigung und schliesst nach 1.5s. --}}
<div x-show="supportOpen"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none;"
     @keydown.escape.window="supportOpen = false">
    <div class="absolute inset-0 bg-slate-900/40" @click="supportOpen = false"></div>

    <div class="relative w-full max-w-xl rounded-xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden"
         x-data="{
            subject: '',
            description: '',
            busy: false,
            error: '',
            success: '',
            submit() {
                this.busy = true; this.error = ''; this.success = '';
                const desc = this.description.trim()
                    + '\n\n— Aufgerufen von: ' + window.location.href;
                fetch(@js(route('support.send')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ subject: this.subject, description: desc }),
                })
                .then(async r => {
                    const j = await r.json().catch(() => ({}));
                    if (! r.ok) {
                        this.error = j.error || j.message || 'Senden fehlgeschlagen.';
                        return;
                    }
                    this.success = j.status || 'Anfrage gesendet. Danke!';
                    setTimeout(() => {
                        this.supportOpen = false;
                        this.subject = ''; this.description = ''; this.success = '';
                    }, 1500);
                })
                .catch(e => { this.error = e.message; })
                .finally(() => { this.busy = false; });
            },
         }"
         x-init="$watch('supportOpen', v => { if (v) { $nextTick(() => $refs.subjectInput?.focus()); error=''; success=''; } })">

        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">{{ $supportLabel }}</h2>
            <button type="button" @click="supportOpen = false" class="text-slate-400 hover:text-slate-600" aria-label="Schliessen">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form @submit.prevent="submit()" class="px-4 py-4 space-y-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Du sendest als <strong>{{ auth()->user()->name }}</strong>
                ({{ auth()->user()->email }}). Die aktuelle URL wird automatisch angehaengt,
                damit das IT-Team direkt sieht, wo du bist.
            </div>

            <div>
                <label for="support_subject" class="block text-xs font-medium text-slate-600 mb-1">Betreff</label>
                <input type="text" id="support_subject" x-ref="subjectInput" x-model="subject" required
                    placeholder="Kurze Zusammenfassung"
                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="support_description" class="block text-xs font-medium text-slate-600 mb-1">Was ist passiert?</label>
                <textarea id="support_description" x-model="description" rows="6" required
                    placeholder="Beschreib so genau wie moeglich, was du gemacht hast und was nicht funktioniert. Schritte zum Reproduzieren, Fehlermeldungen, was du erwartet hattest."
                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <p class="mt-1 text-[11px] text-slate-500">Die URL <code class="bg-slate-100 px-1 rounded" x-text="window.location.pathname"></code> wird ans Ende angehaengt.</p>
            </div>

            <div x-show="error" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" x-text="error"></div>
            <div x-show="success" x-cloak class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-text="success"></div>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" @click="supportOpen = false" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                <button type="submit" :disabled="busy || !subject.trim() || !description.trim()"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 disabled:cursor-wait">
                    <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-show="!busy">Senden</span>
                    <span x-show="busy" x-cloak>Sende &hellip;</span>
                </button>
            </div>
        </form>
    </div>
</div>
