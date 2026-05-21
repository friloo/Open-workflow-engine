<x-app-layout>
    <x-slot name="header">Audit-Integrität</x-slot>
    <x-slot name="subheader">Prüfung am {{ $verifiedAt->format('d.m.Y H:i:s') }} über {{ number_format($total, 0, ',', '.') }} Einträge.</x-slot>

    <x-card>
        @if($broken === null)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                <strong>Audit-Kette ist intakt.</strong>
                <p class="mt-1">Alle Hashes stimmen mit der erwarteten Verkettung überein. Keine Manipulation feststellbar.</p>
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

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.audit.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">&larr; Zurück zum Audit-Log</a>
            <a href="{{ route('admin.audit.verify_pdf') }}" class="ms-auto rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                ⬇ Audit-Zertifikat (PDF) erzeugen
            </a>
        </div>
    </x-card>

    <div class="mt-6">
        <x-card title="Datei-Integrität" description="SHA-256-Prüfung aller Attachments (PDFs etc.). Manipulationen werden erkannt.">
            <form method="POST" action="{{ route('attachments.verify_all') }}">
                @csrf
                <x-secondary-button>Datei-Integrität prüfen</x-secondary-button>
            </form>
            @if(session()->has('integrityBroken'))
                @php($broken = session('integrityBroken'))
                @if(empty($broken))
                    <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Alle Dateien intakt — SHA-256 stimmt für jeden Eintrag.</div>
                @else
                    <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                        <strong>{{ count($broken) }} auffällige Dateien:</strong>
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
