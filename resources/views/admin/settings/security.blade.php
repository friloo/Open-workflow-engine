<x-app-layout>
    <x-slot name="header">Systemeinstellungen · Sicherheit</x-slot>
    <x-slot name="subheader">Passwort-Policy für alle Benutzer.</x-slot>

    @include('admin.settings._tabs', ['sections' => $sections, 'current' => 'security'])

    <x-card title="Passwort-Policy"
            description="Greift bei Registrierung, Passwort-Reset, Self-Service-Änderung und Admin-Anlegt-User. Login-Throttle ist projektweit auf 5 Fehlversuche/60 s konfiguriert.">
        <form method="POST" action="{{ route('admin.settings.security.update') }}" class="space-y-4 max-w-xl">
            @csrf
            <div>
                <x-input-label for="min_length" value="Mindestlänge" />
                <x-text-input id="min_length" name="min_length" type="number" min="6" max="128"
                              value="{{ $policy['min_length'] }}" />
                <p class="mt-1 text-xs text-slate-500">Empfehlung: ≥ 12 für externe Benutzer, ≥ 8 intern.</p>
            </div>

            <div class="rounded-lg border border-slate-200 p-3 space-y-2">
                <div class="text-sm font-semibold text-slate-900">Komplexitäts-Anforderungen</div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="require_uppercase" value="0">
                    <input type="checkbox" name="require_uppercase" value="1"
                           class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           @checked($policy['require_uppercase'])>
                    <span>
                        <span class="block text-sm text-slate-900">Groß- und Kleinbuchstaben</span>
                        <span class="block text-xs text-slate-500">Mindestens je ein A-Z und ein a-z.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="require_number" value="0">
                    <input type="checkbox" name="require_number" value="1"
                           class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           @checked($policy['require_number'])>
                    <span>
                        <span class="block text-sm text-slate-900">Zahl</span>
                        <span class="block text-xs text-slate-500">Mindestens ein 0-9.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="require_symbol" value="0">
                    <input type="checkbox" name="require_symbol" value="1"
                           class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           @checked($policy['require_symbol'])>
                    <span>
                        <span class="block text-sm text-slate-900">Sonderzeichen</span>
                        <span class="block text-xs text-slate-500">Mindestens ein Nicht-Buchstabe-Nicht-Zahl.</span>
                    </span>
                </label>
            </div>

            <div>
                <x-input-label for="max_age_days" value="Maximales Alter in Tagen (optional)" />
                <x-text-input id="max_age_days" name="max_age_days" type="number" min="1" max="3650"
                              value="{{ $policy['max_age_days'] }}" placeholder="leer = kein Ablauf" />
                <p class="mt-1 text-xs text-slate-500">
                    Empfehlung BSI: nicht erzwingen, außer auf Verdacht — Zwangsänderungen führen zu schwächeren Passwörtern.
                    Nur einschalten, wenn Compliance-Vorschrift es verlangt.
                </p>
            </div>

            <x-primary-button>Speichern</x-primary-button>
        </form>
    </x-card>

    <x-card title="Aktuelle Anforderungen (Vorschau)">
        <ul class="list-disc ps-5 text-sm text-slate-700 space-y-0.5">
            @foreach(\App\Support\PasswordPolicy::describeLines() as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </x-card>
</x-app-layout>
