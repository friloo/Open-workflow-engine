{{-- Wird sowohl von admin/settings/support.blade.php als auch von der konsolidierten
     Kommunikation-Seite eingebunden. Erwartet Variable $support. --}}
<x-card title="IT-Support"
        description="Wenn aktiviert, erscheint im Menue ein Eintrag, ueber den Benutzer Tickets erstellen koennen. Je nach Modus geht das per E-Mail an deine Support-Adresse, per HTTP an dein Ticketsystem, oder beides parallel.">
    @php
        $supportState = [
            'enabled' => (bool) ($support['enabled'] ?? false),
            'mode' => $support['mode'] ?? 'mail',
            'headers' => array_values($support['api_headers'] ?? []),
        ];
    @endphp
    <form method="POST" action="{{ route('admin.settings.support.update') }}" class="space-y-5 max-w-3xl"
          x-data='@json($supportState)'>
        @csrf

        <div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" x-model="enabled"
                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <span><strong>Support-Formular aktivieren</strong> — Benutzer sehen einen Menue-Eintrag und koennen Tickets uebermitteln.</span>
            </label>
        </div>

        <div x-show="enabled" x-transition class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="sidebar_label" value="Bezeichnung im Menue" />
                    <x-text-input id="sidebar_label" name="sidebar_label" value="{{ $support['sidebar_label'] ?? 'IT-Support' }}" />
                </div>
                <div>
                    <x-input-label for="mode" value="Modus" />
                    <select id="mode" name="mode" x-model="mode"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="mail">Nur Mail</option>
                        <option value="api">Nur Ticketsystem (API)</option>
                        <option value="both">Beides (parallel)</option>
                    </select>
                </div>
            </div>

            {{-- Mail-Konfig --}}
            <div x-show="mode === 'mail' || mode === 'both'" x-transition class="rounded-lg border border-slate-200 p-4 bg-slate-50/50">
                <h4 class="text-sm font-semibold text-slate-900 mb-3">Mail-Versand</h4>
                <div>
                    <x-input-label for="email" value="Support-Adresse" />
                    <x-text-input id="email" name="email" type="email" value="{{ $support['email'] ?? '' }}" placeholder="support@example.com" />
                    <x-input-error :messages="$errors->get('email')" />
                    <p class="mt-1 text-xs text-slate-500">Die User-Adresse wird automatisch als Reply-To gesetzt.</p>
                </div>
            </div>

            {{-- API-Konfig --}}
            <div x-show="mode === 'api' || mode === 'both'" x-transition class="rounded-lg border border-slate-200 p-4 bg-slate-50/50 space-y-4">
                <h4 class="text-sm font-semibold text-slate-900">Ticketsystem (HTTP-API)</h4>

                {{-- KI: API-Doku oder curl reinpasten, KI fuellt alles aus --}}
                <div class="rounded-lg border border-violet-200 bg-violet-50 p-3 space-y-2"
                     x-data="{ aiInput: '', aiBusy: false, aiError: '', aiNotes: '' }">
                    <div class="flex items-center gap-2">
                        <span class="text-base">+</span>
                        <h5 class="text-xs font-semibold text-violet-800">KI-Import: curl, OpenAPI oder API-Doku einfuegen</h5>
                    </div>
                    <p class="text-[11px] text-violet-700">
                        Du kannst hier einen kompletten curl-Befehl, einen OpenAPI-Snippet
                        oder die Endpunkt-Beschreibung aus einer Doku reinpasten.
                        Die KI fuellt URL, Methode, Header, Auth und Body-Template
                        automatisch aus — Beispielwerte werden durch die richtigen
                        Platzhalter ersetzt.
                    </p>
                    <textarea x-model="aiInput" rows="6"
                        placeholder='curl -X POST "https://example.com/api/tickets" -H "Authorization: Bearer xyz" -d "{\"subject\":\"Test\"}"'
                        class="block w-full rounded-lg border-violet-300 bg-white text-xs shadow-sm focus:border-violet-500 focus:ring-violet-500 font-mono"></textarea>
                    <div class="flex items-center gap-2">
                        <button type="button" :disabled="aiBusy || !aiInput.trim()"
                            @click="window.supportAiImport(@js(route('admin.ai.suggest_http')), aiInput, (result, error) => { aiBusy = false; if (error) { aiError = error; aiNotes = ''; } else { aiError = ''; aiNotes = result.notes || ''; } }); aiBusy = true; aiError = '';"
                            class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-violet-500 disabled:opacity-50">
                            <span x-show="!aiBusy">API uebernehmen</span>
                            <span x-show="aiBusy" x-cloak>analysiere &hellip;</span>
                        </button>
                        <span x-text="aiError" class="text-xs text-rose-700"></span>
                        <span x-show="aiNotes" x-text="'Hinweis: ' + aiNotes" class="text-xs text-violet-700"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <x-input-label for="api_method" value="Methode" />
                        <select id="api_method" name="api_method"
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(['POST','PUT','PATCH'] as $m)
                                <option value="{{ $m }}" @selected(($support['api_method'] ?? 'POST') === $m)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="api_url" value="URL" />
                        <x-text-input id="api_url" name="api_url" value="{{ $support['api_url'] ?? '' }}" placeholder="https://ticket.example.com/api/v1/tickets" />
                        <x-input-error :messages="$errors->get('api_url')" />
                    </div>
                </div>

                {{-- Headers --}}
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Header (Authorization, X-API-Key, …)</label>
                    <div class="space-y-1.5"
                         @support-headers-replaced.window="headers = $event.detail || []"
                         @support-headers-add.window="
                             const e = $event.detail;
                             if (!headers.some(h => h.key && e.key && h.key.toLowerCase() === e.key.toLowerCase())) headers.push(e);
                             else headers = headers.map(h => h.key && e.key && h.key.toLowerCase() === e.key.toLowerCase() ? e : h);
                         ">
                        <template x-for="(h, idx) in headers" :key="idx">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="`api_headers[${idx}][key]`" x-model="h.key"
                                    placeholder="Header-Name" class="w-1/3 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                                <input type="text" :name="`api_headers[${idx}][value]`" x-model="h.value"
                                    placeholder="Wert (kann @{{ secret.NAME }} enthalten)" class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" @click="headers.splice(idx, 1)" class="text-xs text-rose-600 hover:text-rose-500">x</button>
                            </div>
                        </template>
                        <button type="button" @click="headers.push({key:'', value:''})"
                            class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">+ Header</button>
                    </div>
                </div>

                {{-- Body-Template --}}
                <div>
                    <x-input-label value="Body-Template (JSON)" />
                    <textarea id="api_body_template" name="api_body_template" rows="10"
                        class="block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">{{ $support['api_body_template'] ?? '' }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">
                        Platzhalter: <code>@{{ subject }}</code>, <code>@{{ description }}</code>,
                        <code>@{{ user_name }}</code>, <code>@{{ user_email }}</code>,
                        <code>@{{ user_id }}</code>, <code>@{{ app_name }}</code>,
                        <code>@{{ app_url }}</code>, <code>@{{ timestamp }}</code>.
                        Die Werte werden JSON-sicher escaped.
                    </p>
                </div>
            </div>

            <x-input-error :messages="$errors->get('support')" />
        </div>

        <div class="flex justify-end">
            <x-primary-button>Speichern</x-primary-button>
        </div>
    </form>
</x-card>

<script>
    // Holt eine HTTP-Konfig aus curl/Doku via KI und schreibt sie in
    // die sichtbaren Form-Felder (URL, Methode, Headers, Body, Auth).
    // Die KI bekommt die Support-Platzhalter mitgeteilt, damit sie
    // Beispielwerte intelligent durch Mustache-Platzhalter ersetzt.
    window.supportAiImport = function (endpointUrl, input, done) {
        const placeholders = [
            'subject', 'description', 'user_name', 'user_email',
            'user_id', 'app_name', 'app_url', 'timestamp',
        ];
        fetch(endpointUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                input: input,
                purpose: 'Support-Ticket im Ticketsystem anlegen, ausgeloest aus dem internen Support-Formular',
                available_fields: placeholders,
            }),
        })
        .then(async r => {
            const j = await r.json();
            if (!r.ok) { done(null, j.error || 'KI-Fehler.'); return; }
            const s = j.suggestion || {};
            applySupportSuggestion(s);
            done(s, null);
        })
        .catch(e => done(null, e.message));
    };

    function applySupportSuggestion(s) {
        if (s.url) document.getElementById('api_url').value = s.url;
        if (s.method) {
            const sel = document.getElementById('api_method');
            if (sel && ['POST','PUT','PATCH'].includes(s.method)) sel.value = s.method;
        }
        // Headers (Authorization rausfiltern — kommt ueber separate Auth)
        if (Array.isArray(s.headers)) {
            const filtered = s.headers.filter(h => h.key && !/^authorization$/i.test(h.key));
            window.dispatchEvent(new CustomEvent('support-headers-replaced', { detail: filtered }));
        }
        // Auth: wenn KI was gefunden hat, in Header-Liste einfuegen
        if (s.auth_type === 'bearer' && s.auth_token) {
            window.dispatchEvent(new CustomEvent('support-headers-add', {
                detail: { key: 'Authorization', value: 'Bearer ' + s.auth_token }
            }));
        } else if (s.auth_type === 'api_key_header' && s.auth_header_name) {
            window.dispatchEvent(new CustomEvent('support-headers-add', {
                detail: { key: s.auth_header_name, value: s.auth_token || '' }
            }));
        }
        if (s.body_template) document.getElementById('api_body_template').value = s.body_template;
    }
</script>
