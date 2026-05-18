<x-app-layout>
    <x-slot name="header">Meine Aufgaben</x-slot>
    <x-slot name="subheader">Offene Workflow-Schritte, die auf deine Entscheidung warten.</x-slot>

    <x-card>
        @if($open->isEmpty())
            <p class="text-sm text-slate-500">Keine offenen Aufgaben. 🎉</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                            <th class="py-2 pr-4">Workflow</th>
                            <th class="py-2 pr-4">Schritt</th>
                            <th class="py-2 pr-4">Antragsteller</th>
                            <th class="py-2 pr-4">Eingegangen</th>
                            <th class="py-2 pr-4">Frist</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($open as $step)
                            @php($nodeLabel = data_get($step->instance->version?->definition, "drawflow.Home.data.{$step->step_key}.data.label", 'Aufgabe'))
                            <tr>
                                <td class="py-3 pr-4 font-medium text-slate-900">{{ $step->instance->workflow->name }}</td>
                                <td class="py-3 pr-4 text-slate-700">{{ $nodeLabel }}</td>
                                <td class="py-3 pr-4 text-slate-700">{{ $step->instance->starter?->name ?? '—' }}</td>
                                <td class="py-3 pr-4 text-xs text-slate-500">{{ $step->assigned_at?->diffForHumans() }}</td>
                                <td class="py-3 pr-4 text-xs">
                                    @if($step->due_at)
                                        <span class="{{ $step->due_at->isPast() ? 'text-rose-600 font-medium' : 'text-slate-600' }}">
                                            {{ $step->due_at->format('d.m.Y H:i') }}
                                            @if($step->due_at->isPast()) <span class="ms-1">(ueberfaellig)</span> @endif
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('tasks.show', $step) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Oeffnen</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $open->links() }}</div>
        @endif
    </x-card>

    @if($myRecent->isNotEmpty())
        <div class="mt-6">
            <x-card title="Zuletzt von mir bearbeitet">
                <ul class="divide-y divide-slate-100">
                    @foreach($myRecent as $step)
                        <li class="py-3 flex items-start justify-between gap-4 text-sm">
                            <div>
                                <div class="text-slate-900">{{ $step->instance->workflow->name }}</div>
                                <div class="text-xs text-slate-500">{{ $step->completed_at?->diffForHumans() }} — {{ $step->decision }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                @switch($step->decision)
                                    @case('approved') bg-emerald-50 text-emerald-700 @break
                                    @case('rejected') bg-rose-50 text-rose-700 @break
                                    @case('forwarded') bg-amber-50 text-amber-700 @break
                                    @default bg-slate-100 text-slate-700
                                @endswitch">{{ $step->decision }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    @endif
</x-app-layout>
