<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Infrastruktur</x-slot>
    <x-slot name="subheader">Storage, Queue, Suche und Office-Vorschau direkt aus dem UI konfigurieren. Aenderungen ueberschreiben die .env-Defaults.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'infrastructure'])

    <form method="POST" action="{{ route('admin.settings.infrastructure.update') }}" class="space-y-6"
          x-data="{
            disk: @js($stored['attachments_disk'] ?? $effective['attachments_disk']),
            searchDriver: @js($stored['search_driver'] ?? $effective['search_driver']),
            testing: false,
            results: null,
            runTest() {
                this.testing = true; this.results = null;
                fetch(@js(route('admin.settings.infrastructure.test')), {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json'},
                }).then(r => r.json()).then(j => { this.results = j.results; })
                .catch(e => alert('Test fehlgeschlagen: '+e.message))
                .finally(() => { this.testing = false; });
            }
          }">
        @csrf

        {{-- Storage --}}
        <x-card title="Dokumenten-Storage"
                description="Wo die Anhaenge gespeichert werden. Bei Wechsel migrierst du bestehende Files via 'php artisan attachments:migrate-disk &lt;ziel&gt;'.">
            <div class="space-y-4">
                <div>
                    <x-input-label value="Disk" />
                    <div class="mt-1 grid grid-cols-2 gap-3">
                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 cursor-pointer hover:bg-slate-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                            <input type="radio" name="attachments_disk" value="local" x-model="disk" class="mt-1">
                            <div>
                                <div class="text-sm font-medium text-slate-900">Lokal</div>
                                <div class="text-xs text-slate-500">storage/app/private/. Default. Reicht bis ~50GB / einzelner Server.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 cursor-pointer hover:bg-slate-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                            <input type="radio" name="attachments_disk" value="s3" x-model="disk" class="mt-1">
                            <div>
                                <div class="text-sm font-medium text-slate-900">S3 / MinIO / Wasabi</div>
                                <div class="text-xs text-slate-500">Object-Storage. Pflicht bei Multi-Server-Setups, sinnvoll ab grossen Mengen.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div x-show="disk === 's3'" x-transition class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="s3_key" value="Access-Key-ID" />
                            <x-text-input id="s3_key" name="s3_key" value="{{ $stored['s3_key'] ?? $effective['s3_key'] }}" placeholder="AKIA…" autocomplete="off" />
                        </div>
                        <div>
                            <x-input-label for="s3_secret" value="Secret-Access-Key" />
                            <x-text-input id="s3_secret" name="s3_secret" type="password" value="{{ $stored['s3_secret'] ?? $effective['s3_secret'] }}" placeholder="(unveraendert lassen)" autocomplete="new-password" />
                            <p class="mt-1 text-[11px] text-slate-500">Mit **** = bleibt unveraendert. Klar-Text neu = wird ueberschrieben.</p>
                        </div>
                        <div>
                            <x-input-label for="s3_region" value="Region" />
                            <x-text-input id="s3_region" name="s3_region" value="{{ $stored['s3_region'] ?? $effective['s3_region'] }}" placeholder="eu-central-1" />
                        </div>
                        <div>
                            <x-input-label for="s3_bucket" value="Bucket" />
                            <x-text-input id="s3_bucket" name="s3_bucket" value="{{ $stored['s3_bucket'] ?? $effective['s3_bucket'] }}" placeholder="owe-dokumente" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="s3_endpoint" value="Endpoint (nur MinIO / Wasabi / B2)" />
                            <x-text-input id="s3_endpoint" name="s3_endpoint" value="{{ $stored['s3_endpoint'] ?? $effective['s3_endpoint'] }}" placeholder="https://s3.eu-central-1.wasabisys.com" />
                            <p class="mt-1 text-[11px] text-slate-500">Bei AWS S3 leer lassen.</p>
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="s3_use_path_style" value="0">
                        <input type="checkbox" name="s3_use_path_style" value="1" @checked($stored['s3_use_path_style'] ?? $effective['s3_use_path_style']) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Path-Style-Endpoint nutzen (Pflicht bei MinIO / B2)
                    </label>
                </div>
            </div>
        </x-card>

        {{-- Queue --}}
        <x-card title="Queue / Background-Jobs"
                description="Steuert ob OCR + Felder-Extraktion synchron im Upload-Request laufen oder im Hintergrund.">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="queue_connection" value="Queue-Connection" />
                    <select id="queue_connection" name="queue_connection"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="sync" @selected(($stored['queue_connection'] ?? $effective['queue_connection']) === 'sync')>sync (alles synchron, kein Worker)</option>
                        <option value="database" @selected(($stored['queue_connection'] ?? $effective['queue_connection']) === 'database')>database (DB-Tabelle, Worker via queue:work)</option>
                        <option value="redis" @selected(($stored['queue_connection'] ?? $effective['queue_connection']) === 'redis')>redis (schneller, Pflicht-Redis-Setup)</option>
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Bei != sync muss <code>php artisan queue:work</code> laufen (siehe <a href="{{ route('help.show', 'queue-worker') }}" class="text-indigo-600 hover:text-indigo-500">Anleitung</a>).
                    </p>
                </div>
                <div>
                    <x-input-label value="OCR im Hintergrund" />
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 mt-2">
                        <input type="hidden" name="queue_ocr" value="0">
                        <input type="checkbox" name="queue_ocr" value="1" @checked($stored['queue_ocr'] ?? $effective['queue_ocr']) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        OCR + Indexierung als Job dispatchen
                    </label>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Nur sinnvoll mit nicht-sync Queue. Uploads sind dann sofort fertig.
                    </p>
                </div>
            </div>
        </x-card>

        {{-- Suche --}}
        <x-card title="Volltext-Suche"
                description="Standard ist LIKE-Suche ueber MySQL/Postgres. Bei grossen Datenmengen (>50k Docs) MeiliSearch zuschalten.">
            <div class="space-y-3">
                <div>
                    <x-input-label for="search_driver" value="Such-Backend" />
                    <select id="search_driver" name="search_driver" x-model="searchDriver"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="database">database (LIKE — Default)</option>
                        <option value="meilisearch">meilisearch (externer Daemon, fuzzy + schnell)</option>
                    </select>
                </div>
                <div x-show="searchDriver === 'meilisearch'" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="meilisearch_host" value="MeiliSearch-URL" />
                        <x-text-input id="meilisearch_host" name="meilisearch_host" value="{{ $stored['meilisearch_host'] ?? $effective['meilisearch_host'] }}" placeholder="http://127.0.0.1:7700" />
                    </div>
                    <div>
                        <x-input-label for="meilisearch_key" value="Master-/API-Key" />
                        <x-text-input id="meilisearch_key" name="meilisearch_key" type="password" value="{{ $stored['meilisearch_key'] ?? $effective['meilisearch_key'] }}" placeholder="(unveraendert lassen)" autocomplete="new-password" />
                    </div>
                    <div class="sm:col-span-2 text-[11px] text-slate-500">
                        Nach dem Aktivieren einmalig <code>php artisan search:reindex</code> ausfuehren.
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Office --}}
        <x-card title="Office-Vorschau (LibreOffice)"
                description="DOCX/XLSX/PPTX-Files koennen on-the-fly nach PDF konvertiert und im Browser angezeigt werden.">
            <div class="space-y-3">
                <div class="rounded-lg border {{ $libreoffice_available ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-3 py-2 text-sm">
                    Status: <strong>{{ $libreoffice_available ? 'LibreOffice gefunden + aktiv' : 'LibreOffice nicht gefunden' }}</strong>
                    @if(! $libreoffice_available)
                        — Office-Files werden als Download ausgeliefert. Auf Debian/Ubuntu: <code>apt install libreoffice-core libreoffice-writer libreoffice-calc</code>.
                    @endif
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="libreoffice_preview" value="0">
                    <input type="checkbox" name="libreoffice_preview" value="1" @checked($stored['libreoffice_preview'] ?? $effective['libreoffice_preview']) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Office-Vorschau aktivieren (wenn Binary vorhanden ist)
                </label>
                <div>
                    <x-input-label for="libreoffice_bin" value="Pfad zum Binary (optional)" />
                    <x-text-input id="libreoffice_bin" name="libreoffice_bin" value="{{ $stored['libreoffice_bin'] ?? $effective['libreoffice_bin'] }}" placeholder="/usr/bin/libreoffice (leer = Auto-Suche)" />
                </div>
            </div>
        </x-card>

        {{-- Verbindungs-Test --}}
        <x-card title="Verbindungs-Test" description="Klick prueft S3, MeiliSearch, Queue und LibreOffice mit den oben gespeicherten Werten.">
            <button type="button" @click="runTest()" :disabled="testing"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60">
                <svg x-show="testing" x-cloak class="h-3.5 w-3.5 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-show="!testing">Verbindungen testen</span>
                <span x-show="testing" x-cloak>Pruefe &hellip;</span>
            </button>

            <div x-show="results" x-cloak class="mt-4 space-y-1.5 text-sm">
                <template x-for="(r, name) in results" :key="name">
                    <div class="flex items-start gap-2">
                        <span :class="r.ok ? 'text-emerald-600' : 'text-rose-600'" class="font-bold mt-0.5" x-text="r.ok ? '✓' : '✗'"></span>
                        <span class="font-medium uppercase text-xs text-slate-500 w-24" x-text="name"></span>
                        <span :class="r.ok ? 'text-slate-700' : 'text-rose-700'" x-text="r.message"></span>
                    </div>
                </template>
            </div>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-xs text-slate-500">
                Nicht gesetzte Felder verwenden die .env-Defaults. Damit bleibt eine
                frische Installation ohne Admin-Zugriff funktionsfaehig.
            </p>
            <x-primary-button>Speichern</x-primary-button>
        </div>
    </form>
</x-app-layout>
