<x-app-layout :full="true">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <x-breadcrumbs :items="[
            ['title' => 'Vorgaenge', 'url' => route('workflow-instances.index')],
            ['title' => $instance->workflow->name, 'url' => route('workflows.index')],
            ['title' => '#'.$instance->id],
        ]" class="mb-4" />

        <header class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Vorgang #{{ $instance->id }} · {{ $instance->workflow->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Gestartet {{ $instance->started_at?->format('d.m.Y H:i') }}
                    @if($instance->starter) von {{ $instance->starter->name }} @else (oeffentlich) @endif
                    @if($instance->completed_at) · beendet {{ $instance->completed_at->format('d.m.Y H:i') }} @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                @switch($instance->status)
                    @case('running')<span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700">laeuft</span>@break
                    @case('completed')<span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">abgeschlossen</span>@break
                    @case('cancelled')<span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-700">abgebrochen</span>@break
                    @case('failed')<span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-sm font-medium text-rose-700">fehlgeschlagen</span>@break
                @endswitch

                @if($canCancel)
                    <form method="POST" action="{{ route('workflow-instances.cancel', $instance) }}"
                          x-data="{ open: false }">
                        @csrf
                        <button type="button" @click="open = !open" class="inline-flex items-center justify-center rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm hover:bg-rose-50">Vorgang abbrechen</button>
                        <div x-show="open" x-transition class="absolute z-30 mt-2 w-80 rounded-lg bg-white p-3 shadow-lg ring-1 ring-slate-200" style="display:none;">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Grund (optional)</label>
                            <textarea name="reason" rows="2" class="mb-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                            <button type="submit" class="w-full rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-500">Abbrechen bestaetigen</button>
                        </div>
                    </form>
                @endif
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Workflow-Verlauf" description="Aktueller Schritt ist hervorgehoben.">
                    <div id="workflow-viewer" x-data="workflowViewer()" x-init="boot()"
                         class="relative h-[420px] w-full rounded-lg border border-slate-200 bg-slate-50 overflow-hidden"></div>
                    <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-emerald-500"></span>abgeschlossen</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-indigo-500"></span>aktueller Schritt</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-full bg-slate-300"></span>ausstehend</span>
                    </div>
                </x-card>

                <x-card title="Kommentare">
                    @if($instance->comments->isEmpty())
                        <x-empty-state title="Noch keine Kommentare" description="Bearbeiter koennen Notizen zu diesem Vorgang hinterlassen." />
                    @else
                        <ul class="space-y-3 mb-4">
                            @foreach($instance->comments as $c)
                                <li class="flex gap-3">
                                    <div class="grid h-8 w-8 place-items-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold shrink-0">
                                        {{ \Illuminate\Support\Str::of($c->user?->name ?? '?')->explode(' ')->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('') }}
                                    </div>
                                    <div class="flex-1 rounded-lg bg-slate-50 p-3">
                                        <div class="flex items-baseline justify-between gap-2 text-xs">
                                            <span class="font-medium text-slate-900">{{ $c->user?->name ?? 'System' }}</span>
                                            <span class="text-slate-500">{{ $c->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800">{{ $c->body }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <form method="POST" action="{{ route('workflow-instances.comment', $instance) }}" class="space-y-2">
                        @csrf
                        <textarea name="body" rows="2" required placeholder="Kommentar fuer alle Beteiligten..."
                            class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <x-input-error :messages="$errors->get('body')" />
                        <div class="flex justify-end">
                            <x-primary-button>Senden</x-primary-button>
                        </div>
                    </form>
                </x-card>

                <x-card title="Schritt-Historie">
                    @if($instance->stepExecutions->isEmpty())
                        <x-empty-state title="Noch keine Schritte ausgefuehrt" description="Sobald der erste Knoten verarbeitet wird, taucht er hier auf." />
                    @else
                        <ol class="relative ms-3 border-s border-slate-200">
                            @foreach($instance->stepExecutions->sortBy('id') as $step)
                                @php($nodeLabel = data_get($instance->version?->definition, "drawflow.Home.data.{$step->step_key}.data.label", 'Schritt'))
                                <li class="mb-6 ms-6">
                                    <span class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold text-white
                                        @switch($step->decision)
                                            @case('approved') bg-emerald-500 @break
                                            @case('rejected') bg-rose-500 @break
                                            @case('forwarded') bg-amber-500 @break
                                            @case('escalated') bg-amber-600 @break
                                            @case('cancelled') bg-slate-500 @break
                                            @default bg-indigo-500
                                        @endswitch">{{ $loop->iteration }}</span>
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <h3 class="text-sm font-semibold text-slate-900">{{ $nodeLabel }}</h3>
                                        <span class="text-xs text-slate-500">
                                            {{ $step->assigned_at?->format('d.m.Y H:i') }}
                                            @if($step->completed_at) → {{ $step->completed_at->format('d.m.Y H:i') }}@endif
                                        </span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-600">
                                        Zugewiesen an: {{ $step->assignedUser?->name ?? $step->assignedRole?->name ?? '—' }}
                                        @if($step->due_at) · Frist {{ $step->due_at->format('d.m.Y H:i') }}@endif
                                    </div>
                                    @if($step->decision)
                                        <div class="mt-2 inline-flex items-center rounded-md
                                            @switch($step->decision)
                                                @case('approved') bg-emerald-50 text-emerald-700 @break
                                                @case('rejected') bg-rose-50 text-rose-700 @break
                                                @case('forwarded') bg-amber-50 text-amber-700 @break
                                                @case('escalated') bg-amber-50 text-amber-700 @break
                                                @case('cancelled') bg-slate-100 text-slate-700 @break
                                                @default bg-indigo-50 text-indigo-700
                                            @endswitch px-2 py-0.5 text-xs font-medium">{{ $step->decision }}</div>
                                        @if($step->completedBy)
                                            <span class="ms-2 text-xs text-slate-500">durch {{ $step->completedBy->name }}</span>
                                        @endif
                                    @endif
                                    @if($step->comment)
                                        <div class="mt-2 rounded-md bg-slate-50 p-2 text-sm text-slate-700">{{ $step->comment }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>
            </div>

            <div class="space-y-6">
                @php($attachments = $instance->attachments)
            @if($attachments->isNotEmpty())
                <x-card title="Beigefuegte Dateien">
                    <ul class="divide-y divide-slate-100">
                        @foreach($attachments as $a)
                            <li class="py-2 flex items-center justify-between gap-2 text-sm">
                                <div class="min-w-0">
                                    <a href="{{ route('attachments.download', $a) }}" class="font-medium text-indigo-600 hover:text-indigo-500 truncate block" target="_blank">{{ $a->original_name }}</a>
                                    <div class="text-xs text-slate-500">{{ $a->label }}{{ $a->label ? ' · ' : '' }}{{ $a->sizeFormatted() }} · {{ $a->mime_type }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card title="Antragsdaten">
                    @php($schema = $instance->version?->form_schema ?? [])
                    @if(empty($schema) && empty($instance->data))
                        <p class="text-sm text-slate-500">Keine Antragsdaten.</p>
                    @else
                        <dl class="space-y-3 text-sm">
                            @foreach($schema as $field)
                                @php($v = $instance->data[$field['key']] ?? null)
                                <div>
                                    <dt class="text-xs font-medium text-slate-500">{{ $field['label'] ?? $field['key'] }}</dt>
                                    <dd class="text-slate-900">
                                        @if(is_bool($v) || ($field['type'] ?? '')==='checkbox')
                                            {{ $v ? 'Ja' : 'Nein' }}
                                        @elseif(is_array($v))
                                            {{ implode(', ', $v) }}
                                        @else
                                            {{ $v ?? '—' }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                            @foreach($instance->data ?? [] as $key => $val)
                                @if(! collect($schema)->pluck('key')->contains($key))
                                    <div>
                                        <dt class="text-xs font-medium text-slate-500"><code class="bg-slate-100 rounded px-1">{{ $key }}</code></dt>
                                        <dd class="text-slate-900">{{ is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @endif
                </x-card>

                <x-card title="Details">
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-xs text-slate-500">Workflow-Version</dt><dd>v{{ $instance->version?->version_number ?? '?' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Workflow-ID</dt><dd>#{{ $instance->workflow_id }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Aktueller Schritt</dt><dd>{{ $instance->current_step_key ?? '—' }}</dd></div>
                    </dl>
                </x-card>
            </div>
        </div>
    </div>

    <script type="application/json" id="viewer-payload">
        {!! json_encode($viewerPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @vite('resources/js/viewer/index.js')
</x-app-layout>
