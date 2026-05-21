<x-app-layout>
    <x-slot name="header">Benutzer-Import (CSV)</x-slot>
    <x-slot name="subheader">M365-Import folgt in Phase 2.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="CSV hochladen" description="Trennzeichen Semikolon, UTF-8. Erste Zeile = Spaltennamen.">
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.users.import.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="csv" value="CSV-Datei" />
                        <input id="csv" name="csv" type="file" accept=".csv,text/csv" required
                            class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                        <x-input-error :messages="$errors->get('csv')" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="delimiter" value="Trennzeichen" />
                            <x-text-input id="delimiter" name="delimiter" value=";" maxlength="1" />
                        </div>
                        <div>
                            <x-input-label for="default_role" value="Standardrolle (falls keine in CSV)" />
                            <select id="default_role" name="default_role" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($roles as $slug)
                                    <option value="{{ $slug }}" @selected($slug === 'employee')>{{ $slug }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <x-primary-button>Import starten</x-primary-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Letzte Importe">
                @if($recent->isEmpty())
                    <x-empty-state title="Noch keine Importe" description="Lade oben eine CSV hoch, um neue Benutzer anzulegen." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach($recent as $imp)
                            <li class="py-3 flex justify-between text-sm">
                                <div>
                                    <div class="font-medium text-slate-900">{{ $imp->original_filename }}</div>
                                    <div class="text-slate-500">{{ $imp->created_at->format('d.m.Y H:i') }} · {{ $imp->user?->name }}</div>
                                </div>
                                <div class="text-right text-xs text-slate-600">
                                    {{ $imp->rows_imported }} neu · {{ $imp->rows_skipped }} aktualisiert · {{ $imp->rows_failed }} Fehler
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <x-card title="Erwartete Spalten">
            <ul class="space-y-1 text-sm text-slate-700 list-disc ps-4">
                @foreach($columns as $c)
                    <li><code class="bg-slate-100 rounded px-1">{{ $c }}</code></li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-slate-500">
                <code>role_slugs</code> akzeptiert mehrere Werte, getrennt durch Komma. <code>supervisor_email</code> verweist auf einen existierenden Benutzer. <code>is_active</code> / <code>email_notifications_enabled</code> akzeptieren <code>1</code>, <code>0</code>, <code>ja</code>, <code>nein</code>.
            </p>
        </x-card>
    </div>
</x-app-layout>
