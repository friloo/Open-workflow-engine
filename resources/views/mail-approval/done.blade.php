<x-guest-layout>
    @php
        $isApprove = $decision === 'approved';
        $color = $isApprove ? 'emerald' : 'rose';
    @endphp
    <div class="text-center">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 mb-3">
            @if($isApprove)
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            @else
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            @endif
        </div>
        <h1 class="text-xl font-semibold text-slate-900">{{ $isApprove ? 'Genehmigt' : 'Abgelehnt' }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $workflowName }} — Entscheidung von {{ $user->name }} gespeichert.</p>
        <p class="mt-6 text-xs text-slate-500">Dieses Fenster kannst du schliessen.</p>
    </div>
</x-guest-layout>
