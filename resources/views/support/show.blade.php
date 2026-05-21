<x-app-layout>
    <x-slot name="header">{{ $cfg['sidebar_label'] ?? 'IT-Support' }}</x-slot>
    <x-slot name="subheader">Brauchst du Hilfe? Schick uns eine kurze Nachricht — wir melden uns.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                <form method="POST" action="{{ route('support.send') }}" class="space-y-4"
                      x-data="{ busy: false }" @submit="busy = true">
                    @csrf

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        Du sendest als <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).
                        Diese Adresse wird als Referenz mitgeschickt.
                    </div>

                    <div>
                        <x-input-label for="subject" value="Betreff" />
                        <x-text-input id="subject" name="subject" value="{{ old('subject') }}" required autofocus
                            placeholder="Kurze Zusammenfassung des Problems" />
                        <x-input-error :messages="$errors->get('subject')" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Was ist passiert?" />
                        <textarea id="description" name="description" rows="8" required
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Beschreib so genau wie möglich, was du gemacht hast und was nicht funktioniert. Hilfreich: Schritte zum Reproduzieren, Fehlermeldungen, Screenshots-Pfade.">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" />
                    </div>

                    <x-input-error :messages="$errors->get('support')" />

                    <div class="flex items-center justify-end gap-3">
                        <button type="submit" :disabled="busy"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 disabled:cursor-wait">
                            <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-show="!busy">Anfrage senden</span>
                            <span x-show="busy" x-cloak>Sende &hellip;</span>
                        </button>
                    </div>
                </form>
            </x-card>
        </div>

        <div>
            <x-card title="Was passiert mit meiner Anfrage?">
                @if(($cfg['mode'] ?? 'mail') === 'mail')
                    <p class="text-sm text-slate-700">Wir bekommen eine E-Mail mit deiner Anfrage und melden uns zurück.</p>
                @elseif(($cfg['mode'] ?? 'mail') === 'api')
                    <p class="text-sm text-slate-700">Wir legen automatisch ein Ticket im IT-Support-System an. Du bekommst eine Antwort sobald jemand sich darum kümmert.</p>
                @else
                    <p class="text-sm text-slate-700">Wir legen ein Ticket im Support-System an und schicken parallel eine Mail an unser Team. Du bekommst die Antwort auf deine Mail-Adresse.</p>
                @endif

                <p class="mt-3 text-xs text-slate-500">
                    Tipp: Wenn du einen konkreten Workflow oder ein bestimmtes Dokument meinst, kopier den Link aus der Adresszeile in die Beschreibung — dann findet die IT die Stelle direkt.
                </p>
            </x-card>
        </div>
    </div>
</x-app-layout>
