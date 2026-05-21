<x-app-layout>
    <x-slot name="header">System-Update</x-slot>
    <x-slot name="subheader">
        Code-Updates über den zentralen Proxy. Aktueller Channel: <strong>{{ $channel }}</strong>.
    </x-slot>

    @if(session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($inMaintenance)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            Wartungsmodus ist aktiv (.maintenance existiert). Frontend antwortet aktuell mit 503.
            Wenn ein Update fehlgeschlagen ist, kann die Datei manuell entfernt werden.
        </div>
    @endif

    <div x-data="updaterPage()" x-init="loadStatus()" class="space-y-6">

        {{-- Aktuelle Version --}}
        <x-card title="Aktuelle Version">
            <div class="flex flex-wrap items-baseline gap-4">
                <div>
                    <div class="text-xs uppercase tracking-wider text-slate-500">SHA</div>
                    <div class="font-mono text-lg text-slate-900">
                        {{ $currentSha ? substr($currentSha, 0, 7) : '— (noch nie via Updater installiert)' }}
                    </div>
                </div>
                @if($currentSha)
                    <div class="font-mono text-xs text-slate-500 break-all">{{ $currentSha }}</div>
                @endif
            </div>
        </x-card>

        {{-- Channel-Auswahl --}}
        <x-card title="Update-Channel" description="Welche Branch nimmt der Updater? 'stable' für Produktion, 'development' für Vorab-Tests.">
            <form method="POST" action="{{ route('admin.update.channel') }}" class="flex items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Channel</label>
                    <select name="channel" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($channels as $c)
                            <option value="{{ $c }}" @selected($c === $channel)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>Speichern</x-primary-button>
            </form>
        </x-card>

        {{-- Update-Check & Install --}}
        <x-card title="Update prüfen und installieren">
            <div class="flex flex-wrap gap-2 mb-3">
                <button type="button" @click="check()" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50">
                    Auf Updates prüfen
                </button>
                <button type="button" @click="install()" x-show="hasUpdate" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                    Update installieren
                </button>
            </div>

            <div x-show="error" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" x-text="error"></div>

            <template x-if="checkResult">
                <div class="space-y-3">
                    <template x-if="! hasUpdate">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            ✓ Du bist auf dem neuesten Stand (<span x-text="checkResult.latest_sha?.substr(0,7)"></span>).
                        </div>
                    </template>
                    <template x-if="hasUpdate">
                        <div class="space-y-2">
                            <div class="text-sm text-slate-700">
                                Neue Version verfügbar: <strong x-text="checkResult.latest_sha?.substr(0,7)"></strong>
                            </div>
                            <template x-if="checkResult.latest_commit">
                                <div class="rounded border border-slate-200 bg-slate-50 p-2 text-xs">
                                    <div class="font-medium" x-text="checkResult.latest_commit.message"></div>
                                    <div class="text-slate-500" x-text="checkResult.latest_commit.author + ' · ' + checkResult.latest_commit.date"></div>
                                </div>
                            </template>
                            <template x-if="checkResult.changelog">
                                <details class="text-sm">
                                    <summary class="cursor-pointer text-indigo-600 hover:text-indigo-500">Changelog anzeigen</summary>
                                    <pre class="mt-2 rounded bg-slate-50 p-2 text-xs whitespace-pre-wrap" x-text="checkResult.changelog"></pre>
                                </details>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Progress --}}
            <template x-if="progress">
                <div class="mt-4 space-y-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-700" x-text="progress.step + ' — ' + progress.message"></span>
                        <span class="font-mono text-slate-500" x-text="progress.percent + ' %'"></span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full transition-all"
                             :class="progress.step === 'error' ? 'bg-rose-500' : (progress.step === 'done' ? 'bg-emerald-500' : 'bg-indigo-500')"
                             :style="`width: ${progress.percent}%`"></div>
                    </div>
                </div>
            </template>
        </x-card>

        {{-- Migrations + Caches --}}
        <x-card title="Migrationen und Caches"
                description="Updater-Migrationen (eigenes Schema) und App-Migrationen (php artisan migrate) laufen automatisch nach jedem Update. Hier kannst du beides auch manuell triggern — kein SSH nötig.">
            <div class="flex flex-wrap gap-2 mb-3">
                <button type="button" @click="runMigrations()" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                    Migrationen jetzt ausführen
                </button>
                <button type="button" @click="clearCaches()" :disabled="busy"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50">
                    App-Caches leeren (view/config/route/cache + opcache)
                </button>
                <button type="button" @click="loadMigrations()" :disabled="busy"
                    class="ms-auto text-sm text-indigo-600 hover:text-indigo-500">Status aktualisieren</button>
            </div>

            <div x-show="actionResult" x-cloak class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" x-text="actionResult"></div>

            <template x-if="migrations">
                <div class="space-y-4">
                    {{-- App-Migrationen (Laravel) --}}
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 mb-1">App-Migrationen (Laravel)</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-xs font-semibold uppercase text-slate-500">Angewendet (<span x-text="migrations.app?.applied?.length || 0"></span>)</div>
                                <ul class="mt-1 text-slate-700 max-h-40 overflow-y-auto">
                                    <template x-for="m in (migrations.app?.applied || [])" :key="m">
                                        <li class="font-mono text-xs" x-text="m"></li>
                                    </template>
                                    <li x-show="(migrations.app?.applied?.length || 0) === 0" class="text-slate-500 text-xs">noch keine</li>
                                </ul>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase text-slate-500">Ausstehend (<span x-text="migrations.app?.pending?.length || 0"></span>)</div>
                                <ul class="mt-1 text-slate-700 max-h-40 overflow-y-auto">
                                    <template x-for="m in (migrations.app?.pending || [])" :key="m">
                                        <li class="font-mono text-xs text-amber-700" x-text="m"></li>
                                    </template>
                                    <li x-show="(migrations.app?.pending?.length || 0) === 0" class="text-slate-500 text-xs">keine</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Updater-Migrationen --}}
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 mb-1">Updater-Migrationen (eigenes Schema)</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-xs font-semibold uppercase text-slate-500">Angewendet (<span x-text="migrations.updater?.applied?.length || 0"></span>)</div>
                                <ul class="mt-1 text-slate-700 max-h-40 overflow-y-auto">
                                    <template x-for="m in (migrations.updater?.applied || [])" :key="m">
                                        <li class="font-mono text-xs" x-text="m"></li>
                                    </template>
                                    <li x-show="(migrations.updater?.applied?.length || 0) === 0" class="text-slate-500 text-xs">noch keine</li>
                                </ul>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase text-slate-500">Ausstehend (<span x-text="migrations.updater?.pending?.length || 0"></span>)</div>
                                <ul class="mt-1 text-slate-700 max-h-40 overflow-y-auto">
                                    <template x-for="m in (migrations.updater?.pending || [])" :key="m">
                                        <li class="font-mono text-xs text-amber-700" x-text="m"></li>
                                    </template>
                                    <li x-show="(migrations.updater?.pending?.length || 0) === 0" class="text-slate-500 text-xs">keine</li>
                                </ul>
                            </div>
                        </div>
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
                    _poll: null,
                    csrf() {
                        return document.querySelector('meta[name=csrf-token]')?.content;
                    },
                    async loadStatus() {
                        await this.loadProgress();
                        await this.loadMigrations();
                    },
                    async check() {
                        this.busy = true; this.error = null;
                        try {
                            const r = await fetch(@js(route('admin.update.check')), {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                            });
                            const data = await r.json();
                            if (! data.ok) { this.error = data.error || 'Fehler'; return; }
                            this.checkResult = data.data;
                            this.hasUpdate = !! data.data.has_update;
                        } catch (e) {
                            this.error = 'Netzwerkfehler: ' + e.message;
                        }
                        this.busy = false;
                    },
                    async install() {
                        if (! confirm('Update jetzt installieren? Während der Installation ist das Frontend für alle User mit 503 gesperrt.')) return;
                        this.busy = true; this.error = null;
                        this._poll = setInterval(() => this.loadProgress(), 1500);
                        let ok = false;
                        try {
                            const r = await fetch(@js(route('admin.update.install')), {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                            });
                            const data = await r.json();
                            if (data.ok) {
                                ok = true;
                            } else {
                                this.error = data.error || 'Installation fehlgeschlagen';
                            }
                        } catch (e) {
                            this.error = 'Netzwerkfehler: ' + e.message;
                        }
                        clearInterval(this._poll);
                        await this.loadProgress();
                        this.busy = false;
                        await this.loadMigrations();

                        // Bei Erfolg laedt die Seite automatisch neu, damit der
                        // neue Code (Assets, Routes, View-Caches) sichtbar wird.
                        // Kurzer Delay damit der finale 'done'-Progress noch zu
                        // sehen ist.
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
                                this.actionResult = `Migrationen ausgeführt: ${u} Updater- und ${a} App-Migrationen.`;
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
