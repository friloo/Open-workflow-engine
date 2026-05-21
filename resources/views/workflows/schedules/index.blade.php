<x-app-layout>
    <x-slot name="header">Wiedervorlagen: {{ $workflow->name }}</x-slot>
    <x-slot name="subheader">Wiederkehrende Auswertungen, z. B. „Fuehrerschein pruefen alle 6 Monate".</x-slot>

    <div class="mb-4">
        <a href="{{ route('workflows.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Workflows</a>
    </div>

    <x-card title="Neue Wiedervorlage anlegen">
        <form method="POST" action="{{ route('workflows.schedules.store', $workflow) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3">
            @csrf
            <div class="sm:col-span-2">
                <x-input-label for="subject_user_id" value="Bezugs-Person (optional)" />
                <select id="subject_user_id" name="subject_user_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— allgemein —</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="subject_label" value="Bezeichnung" />
                <x-text-input id="subject_label" name="subject_label" placeholder="z. B. Fuehrerschein Klasse B" />
            </div>
            <div>
                <x-input-label for="interval_value" value="Intervall" />
                <x-text-input id="interval_value" name="interval_value" type="number" min="1" value="6" required />
            </div>
            <div>
                <x-input-label for="interval_unit" value="Einheit" />
                <select id="interval_unit" name="interval_unit" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <option value="days">Tage</option>
                    <option value="weeks">Wochen</option>
                    <option value="months" selected>Monate</option>
                    <option value="years">Jahre</option>
                </select>
            </div>
            <div class="sm:col-span-3">
                <x-input-label for="next_run_at" value="Naechste Ausfuehrung" />
                <x-text-input id="next_run_at" name="next_run_at" type="datetime-local" value="{{ now()->addMonths(6)->format('Y-m-d\TH:i') }}" required />
            </div>
            <div class="sm:col-span-3 flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Aktiv
                </label>
            </div>
            <div class="sm:col-span-6 flex justify-end">
                <x-primary-button>Anlegen</x-primary-button>
            </div>
        </form>
    </x-card>

    <div class="mt-6">
        <x-card title="Eingeplante Wiedervorlagen">
            @if($schedules->isEmpty())
                <x-empty-state title="Noch keine Wiedervorlagen" description="Plane oben einen wiederkehrenden Trigger fuer einen Workflow." />
            @else
                <div class="overflow-x-auto">
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
<table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                                <th class="py-2 pr-4">Person</th>
                                <th class="py-2 pr-4">Bezeichnung</th>
                                <th class="py-2 pr-4">Intervall</th>
                                <th class="py-2 pr-4">Naechste Ausf.</th>
                                <th class="py-2 pr-4">Zuletzt</th>
                                <th class="py-2 pr-4">Aktiv</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($schedules as $s)
                                <tr>
                                    <td class="py-3 pr-4 text-slate-900">{{ $s->subjectUser?->name ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $s->subject_label ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-slate-700">alle {{ $s->interval_value }} {{ ['days'=>'Tage','weeks'=>'Wochen','months'=>'Monate','years'=>'Jahre'][$s->interval_unit] }}</td>
                                    <td class="py-3 pr-4 text-slate-700 text-xs">{{ $s->next_run_at?->format('d.m.Y H:i') }}</td>
                                    <td class="py-3 pr-4 text-slate-500 text-xs">{{ $s->last_run_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="py-3 pr-4">
                                        @if($s->is_active)<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktiv</span>
                                        @else<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">pausiert</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right">
                                        <form method="POST" action="{{ route('workflows.schedules.destroy', [$workflow, $s]) }}" class="inline" onsubmit="return confirm('Wiedervorlage loeschen?')">
                                            @csrf @method('DELETE')
                                            <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
</div>
                </div>
                <div class="mt-4">{{ $schedules->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
