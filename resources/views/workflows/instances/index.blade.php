<x-app-layout>
    <x-slot name="header">{{ isset($workflow) ? 'Instanzen: '.$workflow->name : 'Workflow-Vorgänge' }}</x-slot>
    <x-slot name="subheader">{{ $canSeeAll ? 'Alle' : 'Deine' }} laufenden und abgeschlossenen Workflow-Instanzen.</x-slot>

    <div class="mb-4">
        @if(isset($workflow))
            <a href="{{ route('workflows.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Workflows</a>
        @endif
    </div>

    <form method="GET" class="mb-4 grid grid-cols-1 sm:grid-cols-5 gap-2">
        @if(! isset($workflow))
            <input type="text" name="q" value="{{ $search }}" placeholder="Workflow-Name..." class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <select name="workflow_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Alle Workflows</option>
                @foreach($workflows as $wf)
                    <option value="{{ $wf->id }}" @selected($workflowId == $wf->id)>{{ $wf->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Alle Status</option>
            <option value="running" @selected($status==='running')>Laufend</option>
            <option value="completed" @selected($status==='completed')>Abgeschlossen</option>
            <option value="cancelled" @selected($status==='cancelled')>Abgebrochen</option>
            <option value="failed" @selected($status==='failed')>Fehlgeschlagen</option>
        </select>
        <x-secondary-button type="submit">Filtern</x-secondary-button>
    </form>

    <x-card>
        <form method="POST" action="{{ route('workflow-instances.bulk_cancel') }}" x-data="{ selected: [] }">
        @csrf
        @if(auth()->user()->hasAnyPermission(['workflows.design']))
            <div class="mb-3 flex items-center gap-2 text-sm" x-show="selected.length" style="display:none;">
                <span x-text="`${selected.length} ausgewählt`" class="text-slate-700"></span>
                <input type="text" name="reason" placeholder="Grund (optional)" class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500">
                <button type="submit" onclick="return confirm('Ausgewählte Instanzen abbrechen?')"
                    class="inline-flex items-center justify-center rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Abbrechen</button>
            </div>
        @endif
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="py-2 pr-2"></th>
                        <th class="py-2 pr-4">#</th>
                        @if(! isset($workflow))<th class="py-2 pr-4">Workflow</th>@endif
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Aktueller Schritt</th>
                        <th class="py-2 pr-4">Antragsteller</th>
                        <th class="py-2 pr-4">Gestartet</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($instances as $i)
                        @php($nodeLabel = data_get($i->workflow?->currentVersion?->definition, "drawflow.Home.data.{$i->current_step_key}.data.label"))
                        <tr>
                            <td class="py-3 pr-2">
                                @if($i->status==='running' && auth()->user()->hasAnyPermission(['workflows.design']))
                                    <input type="checkbox" name="ids[]" value="{{ $i->id }}" x-model="selected" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-700">#{{ $i->id }}</td>
                            @if(! isset($workflow))<td class="py-3 pr-4 font-medium text-slate-900">{{ $i->workflow->name }}</td>@endif
                            <td class="py-3 pr-4">
                                @switch($i->status)
                                    @case('running')<span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">läuft</span>@break
                                    @case('completed')<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">abgeschlossen</span>@break
                                    @case('cancelled')<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">abgebrochen</span>@break
                                    @case('failed')<span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">fehlgeschlagen</span>@break
                                @endswitch
                            </td>
                            <td class="py-3 pr-4 text-slate-700">{{ $nodeLabel ?? '—' }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ $i->starter?->name ?? 'öffentlich' }}</td>
                            <td class="py-3 pr-4 text-xs text-slate-500"><x-fmt-date :value="$i->started_at" format="relative" /></td>
                            <td class="py-3 text-right">
                                <a href="{{ route('workflow-instances.show', $i) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ isset($workflow) ? 7 : 8 }}" class="py-6 text-center text-slate-500">Keine Instanzen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $instances->links() }}</div>
        </form>
    </x-card>
</x-app-layout>
