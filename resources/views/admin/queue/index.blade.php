<x-app-layout>
    <x-slot name="header">Queue-Worker</x-slot>
    <x-slot name="subheader">Hintergrund-Jobs fuer OCR und schwere Verarbeitung.</x-slot>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-stat-card label="Pending"
            :value="$pending"
            tone="{{ $pending > 100 ? 'rose' : ($pending > 10 ? 'amber' : 'slate') }}"
            hint="Jobs in der Warteschlange" />
        <x-stat-card label="Fehlgeschlagen"
            :value="$failed"
            tone="{{ $failed > 0 ? 'rose' : 'slate' }}"
            hint="failed_jobs-Tabelle" />
        <x-stat-card label="Verbindung"
            :value="$connection"
            tone="{{ $is_sync ? 'amber' : 'emerald' }}"
            hint="QUEUE_CONNECTION" />
    </div>

    @if($is_sync)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <strong>QUEUE_CONNECTION=sync</strong> — Jobs laufen synchron im selben Request,
            es gibt keinen Worker. Wer Background-Verarbeitung will:
            <code>QUEUE_CONNECTION=database</code> in der <code>.env</code> setzen und
            <code>php artisan queue:work</code> dauerhaft laufen lassen
            (z. B. via systemd / supervisor).
        </div>
    @endif

    <x-card title="OCR-Hintergrund-Verarbeitung">
        <div class="text-sm space-y-2">
            <div class="flex items-center gap-3">
                <span class="text-slate-600">OCR-Jobs:</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                    {{ $queue_ocr ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $queue_ocr ? 'in Background-Queue' : 'synchron im Upload-Request' }}
                </span>
            </div>
            <p class="text-xs text-slate-500">
                Steuerung via <code>QUEUE_OCR=true|false</code> in der <code>.env</code>.
                Bei <em>true</em> + laufendem <code>queue:work</code> werden Uploads sofort
                fertig, OCR + Indexfeld-Extraktion passieren im Hintergrund.
            </p>
            <p class="text-xs text-slate-500">
                Pendet ein OCR-Job zu lange (z. B. weil kein Worker laeuft), kann er per
                <code>php artisan ocr:run-pending</code> manuell nachgeholt werden.
            </p>
        </div>
    </x-card>

    @if($recent_failed->isNotEmpty())
        <x-card title="Letzte Fehlschlaege" description="Aus failed_jobs. Per CLI nachschauen mit 'php artisan queue:failed'.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                            <th class="py-2 pr-4">ID</th>
                            <th class="py-2 pr-4">Job</th>
                            <th class="py-2 pr-4">Queue</th>
                            <th class="py-2 pr-4">Fehler</th>
                            <th class="py-2">Zeit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($recent_failed as $f)
                            <tr>
                                <td class="py-2 pr-4 text-xs font-mono text-slate-500">{{ $f['id'] }}</td>
                                <td class="py-2 pr-4 text-slate-700">{{ class_basename($f['job']) }}</td>
                                <td class="py-2 pr-4 text-xs text-slate-500">{{ $f['queue'] }}</td>
                                <td class="py-2 pr-4 text-xs text-rose-700 truncate max-w-[40ch]">{{ $f['first_line'] }}</td>
                                <td class="py-2 text-xs text-slate-500 whitespace-nowrap">{{ $f['failed_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                Retry per CLI: <code>php artisan queue:retry all</code> (alle), oder
                <code>php artisan queue:retry &lt;id&gt;</code> (einzeln).
                Loeschen: <code>php artisan queue:forget &lt;id&gt;</code>.
            </p>
        </x-card>
    @endif

    <x-card title="Worker einrichten">
        <p class="text-sm text-slate-700">
            Empfohlene Variante fuer Produktion: <code>systemd</code>-Unit, die den Worker
            ueberwacht und automatisch neu startet:
        </p>
        <pre class="mt-3 rounded-lg bg-slate-900 text-slate-100 p-3 text-xs overflow-x-auto"># /etc/systemd/system/owe-queue.service
[Unit]
Description=OWE Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/srv/owe
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=2 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target</pre>
        <p class="mt-3 text-xs text-slate-500">
            Aktivieren: <code>sudo systemctl enable --now owe-queue</code>.
            Logs: <code>journalctl -u owe-queue -f</code>.
        </p>
    </x-card>
</x-app-layout>
