<x-app-layout>
    <x-slot name="header">Systemeinstellungen · KI</x-slot>
    <x-slot name="subheader">OpenAI-kompatibler Endpoint für Workflow-Assistenz und HTTP-Knoten-Generierung.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'ai'])

    <x-card title="KI-Integration" description="OpenAI, DeepSeek oder Ollama. Wird z. B. im HTTP-Knoten genutzt, um aus API-Beschreibungen Header/Body zu generieren.">
        <form method="POST" action="{{ route('admin.ai.update') }}" class="space-y-3 max-w-2xl"
              x-data="{ busy: false, action: '' }" @submit="busy = true">
            @csrf
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <input type="hidden" name="enabled" value="0">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="enabled" value="1"
                           class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           @checked($ai['enabled'] ?? true)>
                    <span>
                        <span class="block text-sm font-medium text-slate-900">KI-Features aktivieren</span>
                        <span class="block text-xs text-slate-500">
                            Wenn deaktiviert: alle KI-Funktionen sind ausgeblendet (Workflow-Entwurf,
                            HTTP-Vorschlag, NL-Suche, Feldextraktor). Das Tool funktioniert vollständig
                            ohne KI weiter — du brauchst dann keinen Provider zu konfigurieren.
                        </span>
                    </span>
                </label>
            </div>
            <div>
                <x-input-label for="ai_provider" value="Anbieter" />
                <select id="ai_provider" name="provider" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        x-data x-on:change="
                            if ($event.target.value==='openai') { document.getElementById('ai_base_url').value='https://api.openai.com/v1'; document.getElementById('ai_model').value='gpt-4o-mini'; }
                            if ($event.target.value==='deepseek') { document.getElementById('ai_base_url').value='https://api.deepseek.com/v1'; document.getElementById('ai_model').value='deepseek-chat'; }
                            if ($event.target.value==='ollama') { document.getElementById('ai_base_url').value='http://localhost:11434/v1'; document.getElementById('ai_model').value='llama3.1'; }
                        ">
                    <option value="openai" @selected($ai['provider']==='openai')>OpenAI</option>
                    <option value="deepseek" @selected($ai['provider']==='deepseek')>DeepSeek</option>
                    <option value="ollama" @selected($ai['provider']==='ollama')>Ollama (lokal)</option>
                    <option value="custom" @selected($ai['provider']==='custom')>Anderer (OpenAI-kompatibel)</option>
                </select>
            </div>
            <div>
                <x-input-label for="ai_base_url" value="Base-URL" />
                <x-text-input id="ai_base_url" name="base_url" value="{{ $ai['base_url'] }}" />
            </div>
            <div>
                <x-input-label for="ai_model" value="Modell" />
                <x-text-input id="ai_model" name="model" value="{{ $ai['model'] }}" placeholder="z. B. gpt-4o-mini" />
            </div>
            <div>
                <x-input-label for="ai_api_key" value="API-Key (bei Ollama leer)" />
                <x-text-input id="ai_api_key" name="api_key" type="password" autocomplete="new-password" placeholder="@if(! empty($ai['api_key']))(unverändert lassen)@endif" />
                <p class="mt-1 text-xs text-slate-500">Verschlüsselt gespeichert.</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 space-y-2">
                <div class="text-sm font-semibold text-slate-900">Aktive KI-Funktionen</div>
                <p class="text-xs text-slate-500">
                    Jede Funktion einzeln an/aus. Funktionen mit Zugriff auf Produktivdaten sind extra markiert.
                </p>
                @foreach(\App\Services\AIClient::knownFeatures() as $key => $meta)
                    @php($current = $ai['feature.'.$key] ?? $meta['default'])
                    <label class="flex items-start gap-3 rounded-lg border border-slate-100 p-2.5 cursor-pointer hover:bg-slate-50">
                        <input type="hidden" name="features[{{ $key }}]" value="0">
                        <input type="checkbox" name="features[{{ $key }}]" value="1"
                               class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                               @checked($current)>
                        <span class="flex-1">
                            <span class="flex items-center gap-2">
                                <span class="text-sm font-medium text-slate-900">{{ $meta['label'] }}</span>
                                @if($meta['data_access'])
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700" title="Liest oder verarbeitet Produktivdaten">Produktivdaten</span>
                                @endif
                            </span>
                            <span class="block text-xs text-slate-500">{{ $meta['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="busy" @click="action='save'"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 disabled:cursor-wait">
                    <svg x-show="busy && action==='save'" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-show="!(busy && action==='save')">Speichern</span>
                    <span x-show="busy && action==='save'" x-cloak>Speichere &hellip;</span>
                </button>
                <button type="submit" formaction="{{ route('admin.ai.ping') }}" :disabled="busy"
                    @click="action='ping'"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60 disabled:cursor-wait">
                    <svg x-show="busy && action==='ping'" x-cloak class="h-3.5 w-3.5 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-show="!(busy && action==='ping')">Verbindung testen</span>
                    <span x-show="busy && action==='ping'" x-cloak>Verbinde &hellip;</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('ai')" />
        </form>
    </x-card>
</x-app-layout>
