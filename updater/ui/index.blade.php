<x-app-layout>
    <x-slot name="header">System-Update</x-slot>
    <x-slot name="subheader">
        Code, Migrationen und Caches aktuell halten — direkt aus der Anwendung, ohne SSH.
    </x-slot>

    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($inMaintenance)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <strong>Wartungsmodus aktiv.</strong> Das Frontend antwortet aktuell mit 503. Wenn ein Update fehlgeschlagen ist, kann die Datei <code>.maintenance</code> manuell entfernt werden.
        </div>
    @endif

    <div x-data="updaterPage()" x-init="loadStatus()" class="space-y-6">

        {{-- ───── Hero: aktuelle Version + Channel ───── --}}
        <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-indigo-50 via-white to-slate-50 p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-6">
                <div class="grid h-14 w-14 place-items-center rounded-xl bg-indigo-600 text-white shadow">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs uppercase tracking-widest text-slate-500">Aktuelle Version</div>
                    <div class="mt-0.5 flex flex-wrap items-baseline gap-3">
                        <span class="font-mono text-2xl font-semibold text-slate-900">
                            {{ $currentSha ? substr($currentSha, 0, 7) : '—' }}
                        </span>
                        @if($currentSha)
                            <span class="font-mono text-[11px] text-slate-400 break-all">{{ $currentSha }}</span>
                        @else
                            <span class="text-xs text-slate-500">noch nie via Updater installiert</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-widest text-slate-500 text-right">Channel</div>
                    <form method="POST" action="{{ route('admin.update.channel') }}" class="mt-1 flex items-center gap-2">
                        @csrf
                        <select name="channel" onchange="this.form.submit()"
                                class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($channels as $c)
                                <option value="{{ $c }}" @selected($c === $channel)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </div>

        {{-- ───── Update prüfen + installieren ───── --}}
        <x-card title="Update">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <button type="button" @click="check()" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    <span x-show="!busy || !checkInFlight">Auf Updates prüfen</span>
                    <span x-show="busy && checkInFlight" x-cloak>Prüfe …</span>
                </button>
                <button type="button" @click="install()" x-show="hasUpdate && !installInFlight && (!progress || progress.step === 'done' || progress.step === 'error')" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                    Update installieren
                </button>
            </div>

            <div x-show="error" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></div>

            {{-- Check-Resultat --}}
            <template x-if="checkResult && !installInFlight">
                <div class="mt-2">
                    <template x-if="!hasUpdate">
                        <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            <span>Du bist auf dem neuesten Stand (<strong x-text="checkResult.latest_sha?.substr(0,7)"></strong>).</span>
                        </div>
                    </template>
                    <template x-if="hasUpdate">
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 space-y-2">
                            <div class="flex items-center gap-3">
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-indigo-600 text-white text-xs font-bold">↑</span>
                                <div>
                                    <div class="text-sm font-semibold text-indigo-900">Neue Version verfügbar</div>
                                    <div class="font-mono text-xs text-indigo-700" x-text="checkResult.latest_sha?.substr(0,7)"></div>
                                </div>
                            </div>
                            <template x-if="checkResult.latest_commit">
                                <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
                                    <div class="font-medium text-slate-900" x-text="checkResult.latest_commit.message"></div>
                                    <div class="mt-0.5 text-xs text-slate-500" x-text="checkResult.latest_commit.author + ' · ' + checkResult.latest_commit.date"></div>
                                </div>
                            </template>
                            <template x-if="checkResult.changelog">
                                <details class="rounded-lg border border-slate-200 bg-white text-sm">
                                    <summary class="cursor-pointer px-3 py-2 text-indigo-600 hover:text-indigo-500">Changelog anzeigen</summary>
                                    <pre class="border-t border-slate-100 px-3 py-2 text-xs whitespace-pre-wrap text-slate-700" x-text="checkResult.changelog"></pre>
                                </details>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Progress (Step-Liste statt nur Bar) --}}
            <template x-if="installInFlight || (progress && progress.step !== 'done' && progress.step !== 'error')">
                <div class="mt-5 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-900" x-text="progress?.message || 'Vorbereitung …'"></div>
                            <div class="text-[11px] text-slate-500">Schritt: <span class="font-mono" x-text="progress?.step || 'init'"></span></div>
                        </div>
                        <div class="font-mono text-lg font-semibold text-indigo-700" x-text="(progress?.percent || 0) + ' %'"></div>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-white overflow-hidden border border-slate-200">
                        <div class="h-full transition-all duration-300"
                             :class="progress?.step === 'error' ? 'bg-rose-500' : 'bg-gradient-to-r from-indigo-500 to-violet-500'"
                             :style="`width: ${progress?.percent || 0}%`"></div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-1 text-[11px]">
                        <template x-for="s in stepList" :key="s.key">
                            <div class="flex items-center gap-1.5"
                                 :class="stepIdx(s.key) <= stepIdx(progress?.step) ? 'text-indigo-700 font-medium' : 'text-slate-400'">
                                <span class="grid h-4 w-4 place-items-center rounded-full text-[9px]"
                                      :class="stepIdx(s.key) < stepIdx(progress?.step) ? 'bg-emerald-500 text-white' : (stepIdx(s.key) === stepIdx(progress?.step) ? 'bg-indigo-500 text-white' : 'bg-slate-200 text-slate-500')"
                                      x-text="stepIdx(s.key) < stepIdx(progress?.step) ? '✓' : ''"></span>
                                <span x-text="s.label"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Done-Banner (kurz sichtbar bevor Auto-Reload feuert) --}}
            <template x-if="progress && progress.step === 'done' && installInFlight === false">
                <div class="mt-4 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <span>Update abgeschlossen. Seite wird neu geladen …</span>
                </div>
            </template>
        </x-card>

        {{-- ───── Migrationen + Caches ───── --}}
        <x-card title="Migrationen und Caches"
                description="Beides läuft automatisch nach jedem Update. Hier sind sie auch manuell triggerbar — kein SSH nötig.">

            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button" @click="runMigrations()" :disabled="busy || pendingTotal === 0"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Migrationen ausführen
                    <span x-show="pendingTotal > 0" class="inline-flex items-center rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] font-bold" x-text="pendingTotal"></span>
                </button>
                <button type="button" @click="clearCaches()" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                    App-Caches leeren
                </button>
                <button type="button" @click="loadMigrations()" :disabled="busy"
                    class="ms-auto inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    Aktualisieren
                </button>
            </div>

            <div x-show="actionResult" x-cloak class="mb-3 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span x-text="actionResult"></span>
            </div>

            <template x-if="migrations">
                <div class="space-y-4">
                    {{-- Wenn nichts ausstehend: Erfolgs-Banner --}}
                    <template x-if="pendingTotal === 0">
                        <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            <span>Keine ausstehenden Migrationen — Schema ist aktuell.</span>
                        </div>
                    </template>

                    {{-- App-Migrationen pending --}}
                    <template x-if="(migrations.app?.pending?.length || 0) > 0">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <h3 class="text-sm font-semibold text-slate-900">App-Migrationen (Laravel)</h3>
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700"
                                      x-text="(migrations.app?.pending?.length || 0) + ' ausstehend'"></span>
                            </div>
                            <ul class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
                                <template x-for="m in (migrations.app?.pending || [])" :key="m">
                                    <li class="px-3 py-2 flex items-center gap-2 text-sm">
                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                        <span class="font-mono text-xs text-slate-700" x-text="m"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    {{-- Updater-Migrationen pending --}}
                    <template x-if="(migrations.updater?.pending?.length || 0) > 0">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <h3 class="text-sm font-semibold text-slate-900">Updater-Migrationen</h3>
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700"
                                      x-text="(migrations.updater?.pending?.length || 0) + ' ausstehend'"></span>
                            </div>
                            <ul class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
                                <template x-for="m in (migrations.updater?.pending || [])" :key="m">
                                    <li class="px-3 py-2 flex items-center gap-2 text-sm">
                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                        <span class="font-mono text-xs text-slate-700" x-text="m"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    {{-- Mini-Stats unten --}}
                    <div class="flex flex-wrap gap-4 text-xs text-slate-500 pt-2 border-t border-slate-100">
                        <span>App-Migrationen: <strong class="text-slate-700" x-text="(migrations.app?.applied?.length || 0)"></strong> angewendet</span>
                        <span>Updater-Migrationen: <strong class="text-slate-700" x-text="(migrations.updater?.applied?.length || 0)"></strong> angewendet</span>
                    </div>
                </div>
            </template>
        </x-card>
    </div>

    @push('scripts')
        <script>
            function updaterPage() {
                return {
                    busy: false,
                    error: null,
                    actionResult: null,
                    checkResult: null,
                    progress: null,
                    migrations: null,
                    hasUpdate: false,
                    checkInFlight: false,
                    installInFlight: false,
                    _poll: null,

                    stepList: [
                        { key: 'check',     label: 'Version' },
                        { key: 'download',  label: 'Download' },
                        { key: 'extract',   label: 'Entpacken' },
                        { key: 'apply',     label: 'Anwenden' },
                        { key: 'migrate',   label: 'Migration' },
                        { key: 'cleanup',   label: 'Cache' },
                        { key: 'done',      label: 'Fertig' },
                    ],
                    stepIdx(key) {
                        const i = this.stepList.findIndex(s => s.key === key);
                        return i === -1 ? 0 : i;
                    },

                    get pendingTotal() {
                        return (this.migrations?.app?.pending?.length || 0)
                             + (this.migrations?.updater?.pending?.length || 0);
                    },

                    csrf() {
                        return document.querySelector('meta[name=csrf-token]')?.content;
                    },
                    async loadStatus() {
                        await this.loadProgress();
                        await this.loadMigrations();
                    },
                    async check() {
                        this.busy = true; this.checkInFlight = true; this.error = null;
                        try {
                            const r = await fetch(@js(route('admin.update.check')), {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                            });
                            const data = await r.json();
                            if (! data.ok) { this.error = data.error || 'Fehler'; }
                            else {
                                this.checkResult = data.data;
                                this.hasUpdate = !! data.data.has_update;
                            }
                        } catch (e) {
                            this.error = 'Netzwerkfehler: ' + e.message;
                        }
                        this.busy = false; this.checkInFlight = false;
                    },
                    async install() {
                        if (! confirm('Update jetzt installieren? Während der Installation ist das Frontend für alle User mit 503 gesperrt.')) return;
                        this.busy = true; this.installInFlight = true; this.error = null;
                        this._poll = setInterval(() => this.loadProgress(), 1500);
                        let ok = false;
                        try {
                            const r = await fetch(@js(route('admin.update.install')), {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                            });
                            const data = await r.json();
                            if (data.ok) { ok = true; }
                            else { this.error = data.error || 'Installation fehlgeschlagen'; }
                        } catch (e) {
                            this.error = 'Netzwerkfehler: ' + e.message;
                        }
                        clearInterval(this._poll);
                        await this.loadProgress();
                        this.installInFlight = false;
                        this.busy = false;
                        await this.loadMigrations();

                        if (ok) {
                            setTimeout(() => window.location.reload(), 1200);
                        }
                    },
                    async loadProgress() {
                        try {
                            const r = await fetch(@js(route('admin.update.progress')), { headers: { 'Accept': 'application/json' } });
                            const data = await r.json();
                            this.progress = data.data || null;
                        } catch (e) { /* still */ }
                    },
                    async loadMigrations() {
                        try {
                            const r = await fetch(@js(route('admin.update.migrations')), { headers: { 'Accept': 'application/json' } });
                            const data = await r.json();
                            this.migrations = data.data || null;
                        } catch (e) { /* still */ }
                    },
                    async runMigrations() {
                        if (! confirm('Migrationen jetzt ausführen?')) return;
                        this.busy = true; this.actionResult = null; this.error = null;
                        try {
                            const r = await fetch(@js(route('admin.update.migrations.run')), {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                            });
                            const data = await r.json();
                            if (! data.ok) {
                                this.error = data.error || 'Migration fehlgeschlagen';
                            } else {
                                const u = data.data.updater_applied || 0;
                                const a = data.data.app_applied || 0;
                                this.actionResult = `Migrationen ausgeführt: ${a} App- und ${u} Updater-Migrationen.`;
                            }
                        } catch (e) {
                            this.error = 'Netzwerkfehler: ' + e.message;
                        }
                        this.busy = false;
                        await this.loadMigrations();
                    },
                    async clearCaches() {
                        this.busy = true; this.actionResult = null; this.error = null;
                        try {
                            const r = await fetch(@js(route('admin.update.caches.clear')), {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                            });
                            const data = await r.json();
                            if (! data.ok) {
                                this.error = data.error || 'Cache-Clear fehlgeschlagen';
                            } else {
                                const ok = Object.entries(data.data).filter(([k,v]) => v === 'ok').map(([k]) => k);
                                this.actionResult = 'Caches geleert: ' + ok.join(', ');
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
</x-app-layout>
