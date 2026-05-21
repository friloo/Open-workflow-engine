<x-app-layout>
    <x-slot name="header">DATEV-Export</x-slot>
    <x-slot name="subheader">Buchungsstapel + Belege als ZIP für DATEV / Buchhaltung.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Export erstellen" description="Erzeugt ein ZIP mit Buchungsstapel-CSV + Original-Belegen.">
            <form method="POST" action="{{ route('admin.datev.export') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="from" value="Von" />
                        <x-text-input id="from" name="from" type="date" value="{{ now()->startOfMonth()->toDateString() }}" required />
                    </div>
                    <div>
                        <x-input-label for="to" value="Bis" />
                        <x-text-input id="to" name="to" type="date" value="{{ now()->toDateString() }}" required />
                    </div>
                </div>
                <div>
                    <x-input-label for="document_type" value="Dokument-Typ" />
                    <select id="document_type" name="document_type" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($documentTypes as $t)
                            <option value="{{ $t }}" @selected($t === 'Rechnung')>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <x-input-error :messages="$errors->get('export')" />
                <x-primary-button>ZIP herunterladen</x-primary-button>
            </form>
        </x-card>

        <x-card title="DATEV-Konten + Mapping" description="Default-Konten für den Buchungsstapel und Mapping der Indexfelder auf DATEV-Spalten.">
            <form method="POST" action="{{ route('admin.datev.config.update') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <x-input-label value="Konto Lieferant" />
                        <x-text-input name="config[konto_lieferant]" value="{{ $config['konto_lieferant'] ?? '70000' }}" />
                    </div>
                    <div>
                        <x-input-label value="Gegenkonto" />
                        <x-text-input name="config[gegenkonto_aufwand]" value="{{ $config['gegenkonto_aufwand'] ?? '4400' }}" />
                    </div>
                    <div>
                        <x-input-label value="BU-Schlüssel" />
                        <x-text-input name="config[bu_schluessel]" value="{{ $config['bu_schluessel'] ?? '' }}" />
                    </div>
                </div>

                <div>
                    <div class="text-xs font-semibold text-slate-700 mb-1">Indexfeld → DATEV-Spalte</div>
                    <p class="text-[11px] text-slate-500 mb-2">
                        Welche Indexfeld-Keys aus deinem Doku-Schema für die DATEV-Spalten genutzt werden.
                        Leer = nicht ersetzen, dann gilt der Default-Name.
                    </p>
                    <div class="space-y-1.5">
                        @foreach($defaultMap as $datevField => $defaultKey)
                            <div class="grid grid-cols-2 gap-2 items-center">
                                <code class="text-xs text-slate-500">{{ $datevField }}</code>
                                <input type="text" name="field_map[{{ $datevField }}]"
                                    value="{{ $config['field_map'][$datevField] ?? $defaultKey }}"
                                    placeholder="{{ $defaultKey }}"
                                    class="rounded border-slate-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                            </div>
                        @endforeach
                    </div>
                </div>

                <x-primary-button>Konfiguration speichern</x-primary-button>
            </form>
        </x-card>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
        Mehr Details siehe <a href="{{ route('help.show', 'datev') }}" class="text-indigo-600 hover:text-indigo-500">Anleitung „DATEV-Export"</a>.
    </div>
</x-app-layout>
