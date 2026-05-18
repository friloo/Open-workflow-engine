<x-app-layout>
    <x-slot name="header">Eigene Vorlage importieren</x-slot>
    <x-slot name="subheader">JSON-Datei einer fruehen Export-Aktion oder per Einfuegen.</x-slot>

    <x-card title="Aus Datei">
        <form method="POST" action="{{ route('workflows.templates.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="source" value="upload">
            <div>
                <x-input-label for="file" value="JSON-Datei" />
                <input type="file" id="file" name="file" accept=".json,application/json" required
                    class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
            </div>
            <div>
                <x-input-label for="name_override" value="Anderer Name (optional)" />
                <x-text-input id="name_override" name="name_override" placeholder="Bleibt sonst beim Namen aus der Datei" />
            </div>
            <x-primary-button>Importieren</x-primary-button>
        </form>
    </x-card>

    <x-card title="Aus JSON einfuegen">
        <form method="POST" action="{{ route('workflows.templates.import.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="source" value="paste">
            <div>
                <x-input-label for="json" value="JSON" />
                <textarea id="json" name="json" rows="14" required spellcheck="false"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"
                    placeholder='{"owe_workflow_template":1, "name":"...", "trigger_type":"manual", "definition":{...}, "form_schema":[...]}'></textarea>
            </div>
            <div>
                <x-input-label for="name_override2" value="Anderer Name (optional)" />
                <x-text-input id="name_override2" name="name_override" />
            </div>
            <x-primary-button>Importieren</x-primary-button>
        </form>
    </x-card>

    <div class="mt-4 text-xs text-slate-500">
        <p>Ein Workflow kann via *Workflows -> ... -> Export* (Detail-Seite) als JSON heruntergeladen werden.</p>
    </div>
</x-app-layout>
