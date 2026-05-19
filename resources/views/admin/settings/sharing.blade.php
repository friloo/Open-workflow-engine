<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Sharing</x-slot>
    <x-slot name="subheader">Caps und Pruefungs-Intervalle fuer externe Freigabe-Links.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'sharing'])

    <x-card title="Sharing-Links" description="Cap fuer Ablauf-Datum und Pruefungs-Intervall.">
        <form method="POST" action="{{ route('admin.settings.shares.update') }}" class="space-y-3 max-w-2xl">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <x-input-label for="max_expiry_days" value="Max. Ablauf (Tage)" />
                    <x-text-input id="max_expiry_days" name="max_expiry_days" type="number" min="1" max="3650" value="{{ $shares['max_expiry_days'] }}" />
                    <p class="mt-1 text-xs text-slate-500">Kann von individuellen Links nie ueberschritten werden.</p>
                </div>
                <div>
                    <x-input-label for="default_expiry_days" value="Default-Ablauf (Tage)" />
                    <x-text-input id="default_expiry_days" name="default_expiry_days" type="number" min="1" max="3650" value="{{ $shares['default_expiry_days'] }}" />
                </div>
                <div>
                    <x-input-label for="review_interval_days" value="Pruefungs-Intervall (Tage)" />
                    <x-text-input id="review_interval_days" name="review_interval_days" type="number" min="1" max="365" value="{{ $shares['review_interval_days'] }}" />
                    <p class="mt-1 text-xs text-slate-500">Mail an den Ersteller alle X Tage.</p>
                </div>
                <div>
                    <x-input-label for="review_grace_days" value="Reaktions-Frist (Tage)" />
                    <x-text-input id="review_grace_days" name="review_grace_days" type="number" min="1" max="90" value="{{ $shares['review_grace_days'] }}" />
                    <p class="mt-1 text-xs text-slate-500">Wird ueberschritten -> automatischer Widerruf.</p>
                </div>
            </div>
            <x-primary-button>Speichern</x-primary-button>
        </form>
    </x-card>
</x-app-layout>
