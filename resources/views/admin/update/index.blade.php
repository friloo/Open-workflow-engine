<x-app-layout>
    <x-slot name="header">System-Update</x-slot>
    <x-slot name="subheader">Holt die neueste Version aus dem konfigurierten Channel und installiert sie. Währenddessen ist die App im Wartungsmodus.</x-slot>

    @php($channel = $check['channel'] ?? 'stable')

    <x-card title="Channel" description="Bestimmt das Update-Repository. Bitte hier nicht aus Versehen development wählen.">
        <form method="POST" action="{{ route('admin.update.channel') }}" class="space-y-3">
            @csrf
            <div class="flex items-center gap-3">
                <select name="channel" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach($channels as $slug => $ch)
                        <option value="{{ $slug }}" @selected($channel === $slug)>{{ $ch->label }}</option>
                    @endforeach
                </select>
                <x-primary-button>Speichern</x-primary-button>
            </div>
        </form>
    </x-card>

    <x-card title="Status" x-data="{ poll: null, checking: false,
        load(force = false) {
            if (force) this.checking = true;
            fetch('{{ route('admin.update.status') }}').then(r => r.json()).then(j => {
                this.check = j.check; this.progress = j.progress; this.maintenance = j.maintenance;
            }).finally(() => { this.checking = false; });
        },
        check: @js($check),
        progress: @js($progress),
        maintenance: {{ $maintenance ? 'true' : 'false' }}
    }" x-init="poll = setInterval(() => load(), 5000)">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <div class="text-xs text-slate-500">Kanal</div>
                <div class="text-sm font-medium" x-text="check.label"></div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Installierte Version</div>
                <div class="font-mono text-xs break-all" x-text="check.current || '— unbekannt —'"></div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Verfügbare Version</div>
                <div class="font-mono text-xs break-all" x-text="check.latest || '—'"></div>
            </div>
        </div>

        <div class="mt-3">
            <button type="button" @click="load(true)" :disabled="checking"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span x-show="!checking">Jetzt prüfen</span>
                <span x-show="checking">Pruefe &hellip;</span>
            </button>
        </div>

        <template x-if="check.error">
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                Prüfung fehlgeschlagen: <span x-text="check.error"></span>
            </div>
        </template>

        <template x-if="!check.error && check.has_update">
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Update verfügbar.
            </div>
        </template>

        <template x-if="!check.error && !check.has_update && check.latest">
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                Schon aktuell.
            </div>
        </template>

        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="font-medium">Fortschritt:</span>
                <span x-text="progress.stage || 'idle'"></span>
            </div>
            <div class="mt-1 text-slate-600" x-text="progress.message"></div>
            <div class="mt-1 text-slate-400" x-text="progress.updated_at ? 'zuletzt: '+progress.updated_at : ''"></div>
            <template x-if="maintenance">
                <div class="mt-2 text-rose-700">Wartungsmodus aktiv.</div>
            </template>
        </div>

        <div class="mt-4">
            <form method="POST" action="{{ route('admin.update.run') }}"
                  x-data="{ busy: false }"
                  @submit="if (!confirm('Jetzt updaten? Die App ist während des Updates kurz nicht erreichbar.')) { $event.preventDefault(); return; } busy = true">
                @csrf
                <button type="submit" :disabled="!check.has_update || maintenance || busy"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-show="!busy">Update jetzt installieren</span>
                    <span x-show="busy" x-cloak>Installiere &hellip; (App kurz weg)</span>
                </button>
            </form>
        </div>
    </x-card>

    <x-card title="Manueller ZIP-Upload" description="Plan B wenn der Update-Proxy nicht antwortet oder du gezielt eine bestimmte Version einspielen willst. Lade hier ein Release-ZIP hoch (mit oder ohne vendor/ — der Updater versucht composer install nachzuziehen).">
        @if ($errors->any())
            <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif
        <form method="POST" action="{{ route('admin.update.upload') }}" enctype="multipart/form-data" class="space-y-3" onsubmit="return confirm('ZIP wird angewendet. Die App ist kurz nicht erreichbar. Fortfahren?')">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Release-ZIP</label>
                <input type="file" name="zip" accept=".zip,application/zip" required
                       class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                <p class="mt-1 text-xs text-slate-500">Max. {{ ini_get('upload_max_filesize') }} (PHP <code>upload_max_filesize</code>). PHP <code>post_max_size</code>: {{ ini_get('post_max_size') }}.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Version-Label (optional)</label>
                <input type="text" name="version" placeholder="z. B. 40-stellige SHA, sonst manual-Zeitstempel"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-500">Wenn leer oder kein gültiger SHA: <code>manual-YYYYMMDDHHMMSS</code> wird in <code>.version</code> geschrieben.</p>
            </div>
            <div>
                <x-primary-button>ZIP anwenden</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
