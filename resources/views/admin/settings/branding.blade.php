<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Branding</x-slot>
    <x-slot name="subheader">Erscheinungsbild der App und benutzerdefinierte Profilfelder.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'branding'])

    <x-card title="Branding" description="Wird zur Laufzeit auf das Layout angewendet.">
        <form method="POST" action="{{ route('admin.settings.branding.update') }}" class="space-y-6 max-w-xl">
            @csrf
            <div class="space-y-3">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">App</h4>
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
            </div>

            <div class="space-y-3 pt-2 border-t border-slate-200">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Login-Seite</h4>
                <p class="text-xs text-slate-500">
                    Wenn ein Hintergrund-Bild URL gesetzt ist, ueberschreibt es den Farbverlauf.
                </p>
                <div>
                    <x-input-label for="login_subtitle" value="Untertitel (z.B. Firmenname)" />
                    <x-text-input id="login_subtitle" name="login_subtitle" maxlength="255" value="{{ $branding['login_subtitle'] ?? '' }}" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="login_bg_from" value="Verlauf Start (#hex)" />
                        <x-text-input id="login_bg_from" name="login_bg_from" type="color" value="{{ $branding['login_bg_from'] ?? '#eef2ff' }}" />
                    </div>
                    <div>
                        <x-input-label for="login_bg_to" value="Verlauf Ende (#hex)" />
                        <x-text-input id="login_bg_to" name="login_bg_to" type="color" value="{{ $branding['login_bg_to'] ?? '#f1f5f9' }}" />
                    </div>
                </div>
                <div>
                    <x-input-label for="login_bg_image" value="Hintergrund-Bild URL (optional)" />
                    <x-text-input id="login_bg_image" name="login_bg_image" maxlength="1024" value="{{ $branding['login_bg_image'] ?? '' }}" placeholder="https://...jpg" />
                </div>
            </div>

            <x-primary-button>Speichern</x-primary-button>
        </form>
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
</x-app-layout>
