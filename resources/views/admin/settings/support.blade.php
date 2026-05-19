<x-app-layout>
    <x-slot name="header">Systemeinstellungen · IT-Support</x-slot>
    <x-slot name="subheader">Support-Formular fuer eingeloggte Benutzer (Mail oder Ticketsystem-API).</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'support'])

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
                        <div class="space-y-1.5">
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
                        <div class="flex items-center justify-between">
                            <x-input-label value="Body-Template (JSON)" />
                            <button type="button"
                                x-data="{ busy: false }"
                                @click="busy = true; window.supportAiGenerate(@js(route('admin.support.ai_template')), () => { busy = false; });"
                                class="text-xs text-indigo-600 hover:text-indigo-500"
                                :disabled="busy">
                                <span x-show="!busy">+ KI: aus Beschreibung generieren</span>
                                <span x-show="busy" x-cloak>generiere &hellip;</span>
                            </button>
                        </div>
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
        window.supportAiGenerate = function (url, done) {
            const desc = prompt('Beschreib kurz das Ziel-API (z. B. "Zammad Tickets erstellen mit customer_id").');
            if (!desc) { done(); return; }
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ description: desc }),
            })
            .then(r => r.json())
            .then(j => {
                if (j.template) {
                    document.getElementById('api_body_template').value = j.template;
                } else {
                    alert(j.error || 'KI hat nichts geliefert.');
                }
            })
            .catch(e => alert('Fehler: ' + e.message))
            .finally(done);
        };
    </script>
</x-app-layout>
