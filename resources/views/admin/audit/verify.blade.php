<x-app-layout>
    <x-slot name="header">Audit-Integritaet</x-slot>
    <x-slot name="subheader">Pruefung am {{ $verifiedAt->format('d.m.Y H:i:s') }} ueber {{ number_format($total, 0, ',', '.') }} Eintraege.</x-slot>

    <x-card>
        @if($broken === null)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                <strong>Audit-Kette ist intakt.</strong>
                <p class="mt-1">Alle Hashes stimmen mit der erwarteten Verkettung ueberein. Keine Manipulation feststellbar.</p>
            </div>
        @else
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <strong>Kette gebrochen ab Eintrag #{{ $broken['broken_at_id'] }}.</strong>
                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                    <div><dt class="font-semibold">Erwarteter prev_hash</dt><dd class="font-mono break-all">{{ $broken['expected_prev'] ?? '—' }}</dd></div>
                    <div><dt class="font-semibold">Gespeicherter prev_hash</dt><dd class="font-mono break-all">{{ $broken['stored_prev'] ?? '—' }}</dd></div>
                    <div><dt class="font-semibold">Erwarteter Hash</dt><dd class="font-mono break-all">{{ $broken['expected_hash'] }}</dd></div>
                    <div><dt class="font-semibold">Gespeicherter Hash</dt><dd class="font-mono break-all">{{ $broken['stored_hash'] }}</dd></div>
                </dl>
            </div>
        @endif

        <a href="{{ route('admin.audit.index') }}" class="mt-4 inline-flex text-sm text-indigo-600 hover:text-indigo-500">&larr; Zurueck zum Audit-Log</a>
    </x-card>

    <div class="mt-6">
        <x-card title="Datei-Integritaet" description="SHA-256-Pruefung aller Attachments (PDFs etc.). Manipulationen werden erkannt.">
            <form method="POST" action="{{ route('attachments.verify_all') }}">
                @csrf
                <x-secondary-button>Datei-Integritaet pruefen</x-secondary-button>
            </form>
            @if(session()->has('integrityBroken'))
                @php($broken = session('integrityBroken'))
                @if(empty($broken))
                    <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Alle Dateien intakt — SHA-256 stimmt fuer jeden Eintrag.</div>
                @else
                    <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                        <strong>{{ count($broken) }} auffaellige Dateien:</strong>
                        <ul class="mt-2 list-disc ps-5 text-xs">
                            @foreach($broken as $b)
                                <li>#{{ $b['id'] }} — {{ $b['name'] }}: {{ $b['reason'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
