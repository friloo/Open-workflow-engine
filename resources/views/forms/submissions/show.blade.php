<x-app-layout>
    <x-slot name="header">Eingang #{{ $submission->id }}</x-slot>
    <x-slot name="subheader">{{ $form->name }} · {{ $submission->created_at->format('d.m.Y H:i:s') }}</x-slot>

    <div class="mb-4">
        <a href="{{ route('forms.submissions.index', $form) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Alle Eingaenge</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card title="Eingegebene Daten">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    @foreach($form->schema ?? [] as $f)
                        @php($v = $submission->data[$f['key']] ?? null)
                        <div>
                            <dt class="text-xs font-medium text-slate-500">{{ $f['label'] ?? $f['key'] }}</dt>
                            <dd class="text-slate-900">
                                @if(is_bool($v) || ($f['type'] ?? '')==='checkbox'){{ $v ? 'Ja' : 'Nein' }}
                                @elseif(is_array($v)){{ implode(', ', $v) }}
                                @else{{ $v ?? '—' }}@endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        </div>

        <x-card title="Metadaten">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-xs text-slate-500">Eingegangen</dt><dd>{{ $submission->created_at->format('d.m.Y H:i:s') }}</dd></div>
                <div><dt class="text-xs text-slate-500">Eingereicht von</dt><dd>{{ $submission->submittedBy?->name ?? 'oeffentlich' }}</dd></div>
                @if($submission->instance)
                    <div><dt class="text-xs text-slate-500">Workflow-Vorgang</dt><dd><a href="{{ route('workflow-instances.show', $submission->instance) }}" class="text-indigo-600 hover:text-indigo-500">#{{ $submission->instance->id }} ({{ $submission->instance->workflow->name }})</a></dd></div>
                @endif
                <div><dt class="text-xs text-slate-500">IP-Adresse</dt><dd class="font-mono text-xs">{{ $submission->ip_address ?? '—' }}</dd></div>
            </dl>
        </x-card>
    </div>
</x-app-layout>
