<x-app-layout>
    <x-slot name="header">Backups</x-slot>
    <x-slot name="subheader">Taegliche ZIP-Sicherungen (DB + Anhaenge). Restore via Konsole: <code>php artisan backup:restore &lt;datei&gt;</code></x-slot>

    <x-card title="Retention" description="Backups aelter als X Tage werden bei jedem Lauf automatisch geloescht.">
        <form method="POST" action="{{ route('admin.backups.retention') }}" class="flex items-end gap-3">
            @csrf
            <div>
                <x-input-label for="retention_days" value="Tage" />
                <x-text-input id="retention_days" name="retention_days" type="number" min="1" max="365" :value="$retentionDays" />
            </div>
            <x-primary-button>Speichern</x-primary-button>
        </form>
    </x-card>

    <x-card title="Vorhandene Backups" description="Lokal unter storage/app/backups/.">
        <div class="mb-3 flex items-center justify-between">
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf
                <x-primary-button onclick="return confirm('Backup jetzt erstellen?')">Backup jetzt erstellen</x-primary-button>
            </form>
            <p class="text-xs text-slate-500">{{ count($backups) }} Datei(en)</p>
        </div>
        @if(empty($backups))
            <x-empty-state title="Noch keine Backups" description="Wird taeglich um 01:30 erstellt — oder per Klick auf 'Backup jetzt'." />
        @else
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                    <th class="py-2 pr-4">Datei</th>
                    <th class="py-2 pr-4 text-right">Groesse</th>
                    <th class="py-2 pr-4">Erstellt</th>
                    <th class="py-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($backups as $b)
                        <tr>
                            <td class="py-2 pr-4 font-mono text-xs">{{ $b['file'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($b['size'] / 1024 / 1024, 2) }} MB</td>
                            <td class="py-2 pr-4 text-xs">{{ \Carbon\Carbon::createFromTimestamp($b['created_at'])->format('d.m.Y H:i') }}</td>
                            <td class="py-2 text-right space-x-3">
                                <a href="{{ route('admin.backups.download', $b['file']) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Download</a>
                                <form method="POST" action="{{ route('admin.backups.destroy', $b['file']) }}" class="inline" onsubmit="return confirm('Backup wirklich loeschen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <strong>Restore</strong> erfolgt aus Sicherheitsgruenden nur per CLI:
        <pre class="mt-2 rounded bg-white p-2 text-xs">php artisan backup:restore owe-2026-05-30_013000.zip</pre>
        Waehrend des Restore wird die App in den Wartungsmodus versetzt. Anschliessend Empfehlung: <code>php artisan migrate --force</code> (falls Schema neuer als Backup).
    </div>
</x-app-layout>
