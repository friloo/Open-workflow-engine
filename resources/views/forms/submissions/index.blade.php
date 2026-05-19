<x-app-layout>
    <x-slot name="header">Eingaenge: {{ $form->name }}</x-slot>
    <x-slot name="subheader">{{ $form->submissions()->count() }} Eingaenge insgesamt.</x-slot>

    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('forms.edit', $form) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Formular bearbeiten</a>
        <a href="{{ route('forms.submissions.export', $form) }}">
            <x-secondary-button type="button">CSV exportieren</x-secondary-button>
        </a>
    </div>

    <x-card>
        @if($submissions->isEmpty())
            <x-empty-state icon="form" title="Noch keine Eingaben" description="Sobald jemand das oeffentliche Formular ausfuellt, taucht es hier auf." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Eingegangen</th>
                            <th class="py-2 pr-4">Von</th>
                            @foreach(array_slice($form->schema ?? [], 0, 3) as $f)
                                <th class="py-2 pr-4">{{ $f['label'] ?? $f['key'] }}</th>
                            @endforeach
                            <th class="py-2 pr-4">Workflow</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($submissions as $s)
                            <tr>
                                <td class="py-3 pr-4 text-slate-700">#{{ $s->id }}</td>
                                <td class="py-3 pr-4 text-xs text-slate-600">{{ $s->created_at->format('d.m.Y H:i') }}</td>
                                <td class="py-3 pr-4 text-slate-700">{{ $s->submittedBy?->name ?? 'oeffentlich' }}</td>
                                @foreach(array_slice($form->schema ?? [], 0, 3) as $f)
                                    @php($v = $s->data[$f['key']] ?? null)
                                    <td class="py-3 pr-4 text-slate-700 max-w-[180px] truncate">
                                        @if(is_bool($v) || ($f['type'] ?? '')==='checkbox'){{ $v ? 'Ja' : 'Nein' }}
                                        @elseif(is_array($v)){{ implode(', ', $v) }}
                                        @else{{ $v ?? '—' }}@endif
                                    </td>
                                @endforeach
                                <td class="py-3 pr-4 text-xs">
                                    @if($s->workflow_instance_id)
                                        <a href="{{ route('workflow-instances.show', $s->workflow_instance_id) }}" class="text-indigo-600 hover:text-indigo-500">#{{ $s->workflow_instance_id }}</a>
                                    @else —
                                    @endif
                                </td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('forms.submissions.show', [$form, $s]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Details</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $submissions->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
