<x-app-layout>
    <x-slot name="header">Systemeinstellungen · KI</x-slot>
    <x-slot name="subheader">OpenAI-kompatibler Endpoint fuer Workflow-Assistenz und HTTP-Knoten-Generierung.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'ai'])

    <x-card title="KI-Integration" description="OpenAI, DeepSeek oder Ollama. Wird z. B. im HTTP-Knoten genutzt, um aus API-Beschreibungen Header/Body zu generieren.">
        <form method="POST" action="{{ route('admin.ai.update') }}" class="space-y-3 max-w-2xl">
            @csrf
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
                <x-text-input id="ai_api_key" name="api_key" type="password" autocomplete="new-password" placeholder="@if(! empty($ai['api_key']))(unveraendert lassen)@endif" />
                <p class="mt-1 text-xs text-slate-500">Verschluesselt gespeichert.</p>
            </div>
            <div class="flex gap-2">
                <x-primary-button>Speichern</x-primary-button>
                <button type="submit" formaction="{{ route('admin.ai.ping') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Verbindung testen</button>
            </div>
            <x-input-error :messages="$errors->get('ai')" />
        </form>
    </x-card>
</x-app-layout>
