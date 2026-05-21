<x-app-layout>
    <x-slot name="header">Suche · Natürliche Sprache</x-slot>
    <x-slot name="subheader">
        @if($available)
            Stell deine Frage in eigenen Worten — die KI übersetzt sie in einen Filter.
        @else
            KI ist deaktiviert oder nicht konfiguriert. Frag deinen Admin oder nutze die <a href="{{ route('search.global') }}" class="text-indigo-600 hover:text-indigo-500">erweiterte Suche</a>.
        @endif
    </x-slot>

    @if(! $available)
        <x-card>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>Natürlich-sprachliche Suche derzeit nicht verfügbar.</strong>
                <p class="mt-1">Diese Funktion braucht eine konfigurierte KI. Der Rest der Anwendung
                funktioniert ohne KI weiterhin vollständig.</p>
                @if(auth()->user()->hasPermission('system.settings'))
                    <p class="mt-2">
                        <a href="{{ route('admin.settings.ai') }}" class="font-medium underline">KI-Einstellungen öffnen →</a>
                    </p>
                @endif
            </div>
        </x-card>
    @else
        <x-card>
            <div x-data="nlSearch()" class="space-y-4">
                <form @submit.prevent="run()" class="flex gap-2">
                    <input type="text" x-model="query" placeholder="z. B. 'Meine Verträge die im Juli auslaufen mit Wert über 10k'"
                        class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autofocus>
                    <button type="submit" :disabled="busy"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                        <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-show="!busy">Suchen</span>
                        <span x-show="busy" x-cloak>Frage KI …</span>
                    </button>
                </form>

                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="text-slate-500">Beispiele:</span>
                    @foreach([
                        'Meine offenen Aufgaben',
                        'Verträge die in 30 Tagen auslaufen',
                        'Vorgänge die ich gestartet habe diesen Monat',
                        'Rechnungen über 5000 Euro',
                        'Akten die noch offen sind',
                    ] as $ex)
                        <button type="button" @click="query = @js($ex); run()"
                                class="rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-700 hover:bg-slate-200">{{ $ex }}</button>
                    @endforeach
                </div>

                <div x-show="error" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></div>

                <template x-if="filter && Object.keys(filter).length">
                    <details class="text-xs text-slate-500">
                        <summary class="cursor-pointer">Interpretierter Filter (Debug)</summary>
                        <pre class="mt-1 rounded bg-slate-50 p-2 overflow-x-auto" x-text="JSON.stringify(filter, null, 2)"></pre>
                    </details>
                </template>

                <template x-if="results">
                    <div class="space-y-4">
                        <template x-for="(items, group) in results" :key="group">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                                    <span x-text="groupLabel(group)"></span>
                                    <span class="text-slate-400" x-text="'· ' + items.length + ' Treffer'"></span>
                                </h3>
                                <ul x-show="items.length > 0" class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                                    <template x-for="item in items" :key="item.id">
                                        <li class="px-3 py-2.5">
                                            <a :href="item.url" class="block hover:bg-slate-50">
                                                <div class="text-sm font-medium text-slate-900" x-text="item.name"></div>
                                                <div class="text-xs text-slate-500">
                                                    <span x-text="item.subtitle"></span>
                                                    <span x-show="item.meta" class="text-slate-400" x-text="' · ' + item.meta"></span>
                                                </div>
                                            </a>
                                        </li>
                                    </template>
                                </ul>
                                <p x-show="items.length === 0" class="text-sm text-slate-500">Keine Treffer.</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </x-card>

        @push('scripts')
            <script>
                function nlSearch() {
                    return {
                        query: '',
                        busy: false,
                        error: null,
                        filter: null,
                        results: null,
                        labels: {
                            contracts: 'Verträge',
                            cases: 'Akten',
                            instances: 'Workflow-Vorgänge',
                            documents: 'Dokumente',
                        },
                        groupLabel(key) {
                            return this.labels[key] || key;
                        },
                        async run() {
                            if (! this.query.trim() || this.busy) return;
                            this.busy = true; this.error = null;
                            this.results = null; this.filter = null;
                            try {
                                const csrf = document.querySelector('meta[name=csrf-token]')?.content;
                                const r = await fetch(@js(route('search.nl.ask')), {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                    },
                                    body: JSON.stringify({ query: this.query }),
                                });
                                const data = await r.json();
                                if (! data.ok) {
                                    this.error = data.error || 'Unbekannter Fehler';
                                } else {
                                    this.filter = data.filter || {};
                                    this.results = data.results || {};
                                }
                            } catch (e) {
                                this.error = 'Netzwerkfehler: ' + e.message;
                            }
                            this.busy = false;
                        },
                    };
                }
            </script>
        @endpush
    @endif
</x-app-layout>
