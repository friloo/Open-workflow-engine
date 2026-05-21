<x-app-layout>
    <x-slot name="header">System-Health</x-slot>
    <x-slot name="subheader">Statuskarten für alle wichtigen Komponenten. Aktualisiert sich bei jedem Aufruf.</x-slot>

    @php
        $colors = [
            'ok'   => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'dot' => 'bg-emerald-500', 'label' => 'OK'],
            'warn' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'text' => 'text-amber-800',   'dot' => 'bg-amber-500',   'label' => 'WARN'],
            'fail' => ['bg' => 'bg-rose-50',    'border' => 'border-rose-200',    'text' => 'text-rose-800',    'dot' => 'bg-rose-500',    'label' => 'FEHLER'],
        ];
        $byStatus = collect($checks)->groupBy('status')->map->count();
    @endphp

    <x-card>
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span> <strong>{{ $byStatus['ok'] ?? 0 }}</strong> OK</div>
            <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span> <strong>{{ $byStatus['warn'] ?? 0 }}</strong> Warnungen</div>
            <div class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-rose-500"></span> <strong>{{ $byStatus['fail'] ?? 0 }}</strong> Fehler</div>
            <div class="ms-auto text-xs text-slate-500">JSON: <a href="{{ route('admin.health.json') }}" class="font-mono text-indigo-600 hover:text-indigo-500">/admin/health.json</a></div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($checks as $check)
            @php $c = $colors[$check['status']] ?? $colors['warn']; @endphp
            <div class="rounded-lg border {{ $c['border'] }} {{ $c['bg'] }} p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-2.5 w-2.5 rounded-full {{ $c['dot'] }}"></span>
                            <span class="font-semibold {{ $c['text'] }}">{{ $check['name'] }}</span>
                        </div>
                        <div class="mt-1 text-sm {{ $c['text'] }}">{{ $check['message'] }}</div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-xs font-semibold {{ $c['text'] }} border {{ $c['border'] }}">{{ $c['label'] }}</span>
                </div>
                @if(! empty($check['details']))
                    <pre class="mt-3 max-h-32 overflow-auto rounded bg-white/60 p-2 text-xs text-slate-600 border {{ $c['border'] }}">{{ json_encode($check['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
