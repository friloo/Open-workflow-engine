<x-app-layout>
    <x-slot name="header">
        {{ $case->name }}
        <x-help-hint topic="cases" label="Anleitung Akten" />
    </x-slot>
    <x-slot name="subheader">
        @if($case->reference)<code class="text-xs">{{ $case->reference }}</code> · @endif
        Aktendeckel
        @if($case->closed_at) · <span class="text-amber-700">geschlossen {{ $case->closed_at->format('d.m.Y') }}</span>@endif
    </x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Akten', 'url' => route('cases.index')],
        ['title' => $case->name],
    ]" />

    <div class="mb-4 flex items-center justify-end gap-3">
        <a href="{{ route('cases.edit', $case) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
        <form method="POST" action="{{ route('cases.close', $case) }}" class="inline">
            @csrf
            <button class="text-sm text-amber-700 hover:text-amber-800">{{ $case->closed_at ? 'Wieder öffnen' : 'Akte schliessen' }}</button>
        </form>
        <form method="POST" action="{{ route('cases.destroy', $case) }}" class="inline"
              onsubmit="return confirm('Akte löschen? Inhalte (Dokumente, Vorgänge, Verträge) bleiben erhalten, nur die Zuordnung wird gelöst.')">
            @csrf @method('DELETE')
            <button class="text-sm text-rose-600 hover:text-rose-500">Löschen</button>
        </form>
    </div>

    @if($case->description)
        <x-card>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $case->description }}</p>
        </x-card>
    @endif

    {{-- Counts-Karten --}}
    <div class="my-6 grid grid-cols-2 md:grid-cols-4 gap-3">
        <x-card><div class="text-xs uppercase text-slate-500">Dokumente</div><div class="text-2xl font-semibold">{{ $case->attachments->count() }}</div></x-card>
        <x-card><div class="text-xs uppercase text-slate-500">Vorgänge</div><div class="text-2xl font-semibold">{{ $case->workflowInstances->count() }}</div></x-card>
        <x-card><div class="text-xs uppercase text-slate-500">Verträge</div><div class="text-2xl font-semibold">{{ $case->contracts->count() }}</div></x-card>
        <x-card><div class="text-xs uppercase text-slate-500">Notizen</div><div class="text-2xl font-semibold">{{ $case->notes->count() }}</div></x-card>
    </div>

    <x-card title="Dokumente">
        @if($case->attachments->isEmpty())
            <x-empty-state icon="document" title="Noch keine Dokumente"
                description='In der Dokumenten-Liste mehrere Dokumente auswählen und über Bulk-Aktion zu dieser Akte hinzufügen.' />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($case->attachments as $d)
                    <li class="py-2 flex items-center justify-between">
                        <div>
                            <a href="{{ route('documents.show', $d) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $d->original_name }}</a>
                            <div class="text-xs text-slate-500">
                                {{ $d->document_type ?: '—' }} · {{ $d->sizeFormatted() }} ·
                                {{ $d->created_at->format('d.m.Y') }}
                            </div>
                        </div>
                        <a href="{{ route('attachments.download', $d) }}" class="text-xs text-indigo-600 hover:text-indigo-500">Download</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card title="Workflow-Vorgänge" description="Vorgänge die dieser Akte zugeordnet sind.">
        <form method="POST" action="{{ route('cases.workflows.attach', $case) }}" class="mb-3 flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <x-input-label for="workflow_instance_id" value="Vorgangs-ID" />
                <x-text-input id="workflow_instance_id" name="workflow_instance_id" type="number" min="1" required placeholder="z. B. 42" />
                <p class="mt-1 text-xs text-slate-500">Die Nummer findest du in der Vorgangs-URL.</p>
            </div>
            <x-secondary-button type="submit">Anhängen</x-secondary-button>
        </form>
        @if($case->workflowInstances->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Vorgänge zugeordnet.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($case->workflowInstances as $i)
                    <li class="py-2 flex items-center justify-between">
                        <div>
                            <a href="{{ route('workflow-instances.show', $i) }}" class="font-medium text-slate-900 hover:text-indigo-600">#{{ $i->id }} · {{ $i->workflow?->name ?? '—' }}</a>
                            <div class="text-xs text-slate-500">
                                Status: <span class="font-medium">{{ $i->status }}</span> ·
                                gestartet {{ $i->started_at?->diffForHumans() }}
                                @if($i->starter) von {{ $i->starter->name }} @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('cases.workflows.detach', [$case, $i->id]) }}"
                              onsubmit="return confirm('Vorgang aus Akte entfernen?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-600 hover:text-rose-500">Entfernen</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if(auth()->user()->hasAnyPermission(['contracts.view','contracts.manage']))
    <x-card title="Verträge" description="Verträge die dieser Akte zugeordnet sind.">
        <form method="POST" action="{{ route('cases.contracts.attach', $case) }}" class="mb-3 flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <x-input-label for="contract_id" value="Vertrags-ID" />
                <x-text-input id="contract_id" name="contract_id" type="number" min="1" required placeholder="z. B. 7" />
                <p class="mt-1 text-xs text-slate-500">
                    Die Nummer findest du in der Vertrags-URL oder in der
                    <a href="{{ route('contracts.index') }}" class="text-indigo-600 hover:text-indigo-500">Vertragsliste</a>.
                </p>
            </div>
            <x-secondary-button type="submit">Anhängen</x-secondary-button>
        </form>
        @if($case->contracts->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Verträge zugeordnet.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($case->contracts as $c)
                    <li class="py-2 flex items-center justify-between">
                        <div>
                            <a href="{{ route('contracts.show', $c) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $c->name }}</a>
                            <div class="text-xs text-slate-500">
                                {{ $c->party ?? '—' }} ·
                                Status: <span class="font-medium">{{ $c->status }}</span> ·
                                Ende: {{ $c->end_date?->format('d.m.Y') ?? '—' }}
                            </div>
                        </div>
                        <form method="POST" action="{{ route('cases.contracts.detach', [$case, $c->id]) }}"
                              onsubmit="return confirm('Vertrag aus Akte entfernen?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-600 hover:text-rose-500">Entfernen</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
    @endif

    <x-card title="Notizen" description="Akten-interne Anmerkungen (chronologisch, neueste oben).">
        <form method="POST" action="{{ route('cases.notes.add', $case) }}" class="mb-4 space-y-2">
            @csrf
            <textarea name="body" rows="3" required placeholder="Notiz zur Akte ..."
                      class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <div class="flex justify-end">
                <x-secondary-button type="submit">Notiz speichern</x-secondary-button>
            </div>
        </form>

        @if($case->notes->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Notizen.</p>
        @else
            <ul class="space-y-3">
                @foreach($case->notes as $n)
                    <li class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <div class="text-xs text-slate-500">
                                <strong class="text-slate-700">{{ $n->user?->name ?? 'System' }}</strong>
                                · {{ $n->created_at->format('d.m.Y H:i') }}
                            </div>
                            @if(auth()->id() === $n->user_id || auth()->user()->hasRole('admin'))
                                <form method="POST" action="{{ route('cases.notes.delete', [$case, $n->id]) }}"
                                      onsubmit="return confirm('Notiz löschen?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 hover:text-rose-500">Löschen</button>
                                </form>
                            @endif
                        </div>
                        <p class="mt-2 text-sm text-slate-800 whitespace-pre-wrap">{{ $n->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
