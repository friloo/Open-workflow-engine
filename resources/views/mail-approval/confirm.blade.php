<x-guest-layout>
    @php
        $instance = $step->instance;
        $workflow = $instance->workflow;
        $nodeLabel = data_get($node, 'data.label', 'Aufgabe');
        $isApprove = $decision === 'approved';
        $color = $isApprove ? 'emerald' : 'rose';
        $title = $isApprove ? 'Genehmigung bestaetigen' : 'Ablehnung bestaetigen';
    @endphp

    <h1 class="text-xl font-semibold text-slate-900 mb-1">{{ $title }}</h1>
    <p class="text-sm text-slate-500 mb-6">Pruefe die Angaben und bestaetige die Entscheidung — ohne Login.</p>

    <div class="rounded-lg border border-slate-200 bg-white p-4 mb-4 text-sm">
        <div class="grid grid-cols-3 gap-2">
            <div class="text-slate-500">Workflow</div>
            <div class="col-span-2 font-medium text-slate-900">{{ $workflow?->name ?? '—' }}</div>
            <div class="text-slate-500">Schritt</div>
            <div class="col-span-2 font-medium text-slate-900">{{ $nodeLabel }}</div>
            <div class="text-slate-500">Antragsteller</div>
            <div class="col-span-2 text-slate-700">{{ $instance->starter?->name ?? '—' }} <span class="text-slate-500">({{ $instance->starter?->email ?? '—' }})</span></div>
            @if($step->due_at)
                <div class="text-slate-500">Frist</div>
                <div class="col-span-2 text-slate-700">{{ $step->due_at->format('d.m.Y H:i') }}</div>
            @endif
            <div class="text-slate-500">Empfaenger</div>
            <div class="col-span-2 text-slate-700">{{ $user->name }} <span class="text-slate-500">({{ $user->email }})</span></div>
        </div>
    </div>

    @if(! empty($instance->data))
        <details class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <summary class="cursor-pointer text-sm font-medium text-slate-700">Antragsdaten anzeigen</summary>
            <dl class="mt-2 grid grid-cols-3 gap-2 text-xs">
                @foreach($instance->data as $k => $v)
                    @if(is_scalar($v) && ! str_starts_with((string) $k, '_'))
                        <dt class="text-slate-500 font-mono">{{ $k }}</dt>
                        <dd class="col-span-2 text-slate-700">{{ \Illuminate\Support\Str::limit((string) $v, 200) }}</dd>
                    @endif
                @endforeach
            </dl>
        </details>
    @endif

    @php
        $reqApprove = (bool) data_get($node, 'data.require_comment_on_approval', false);
        $reqReject = (bool) data_get($node, 'data.require_comment_on_rejection', false);
        $commentRequired = ($isApprove && $reqApprove) || (! $isApprove && $reqReject);
    @endphp

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc ps-5 space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $fullUrl }}" class="space-y-4">
        @csrf
        <div>
            <label for="comment" class="block text-sm font-medium text-slate-700">
                {{ $isApprove ? 'Kommentar' : 'Begruendung' }}
                @if($commentRequired)<span class="text-rose-600">*</span>@else (optional)@endif
            </label>
            <textarea id="comment" name="comment" rows="3" @if($commentRequired) required @endif
                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-{{ $color }}-500 focus:ring-{{ $color }}-500"></textarea>
        </div>
        <button type="submit"
                class="inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90
                       {{ $isApprove ? 'bg-emerald-600' : 'bg-rose-600' }}">
            {{ $isApprove ? 'Genehmigen' : 'Ablehnen' }}
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-500">
        Dieser Link ist nur fuer dich gueltig und laeuft automatisch ab. Wenn du den Link nicht erwartet hast, ignoriere die Mail.
    </p>
</x-guest-layout>
