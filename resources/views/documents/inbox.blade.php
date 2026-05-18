<x-app-layout>
    <x-slot name="header">Postkorb</x-slot>
    <x-slot name="subheader">Dokumente ohne Workflow-Zuordnung — z. B. via E-Mail eingegangen. Erkannte Felder helfen bei der weiteren Verarbeitung.</x-slot>

    <x-card>
        @if($documents->isEmpty())
            <x-empty-state icon="document" title="Postkorb leer"
                description="Hier landen Dokumente, die noch keinem Workflow zugeordnet wurden — etwa Anhaenge aus IMAP-Postfaechern ohne Workflow-Auto-Start." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($documents as $d)
                    <li class="py-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('documents.show', $d) }}" class="font-medium text-slate-900 hover:text-indigo-600 truncate">{{ $d->original_name }}</a>
                                    @if($d->document_type)
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $d->document_type }}</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $d->sizeFormatted() }} · eingegangen {{ $d->created_at->diffForHumans() }}
                                    @if($d->label) · <em>{{ $d->label }}</em>@endif
                                </div>
                                @if(! empty($d->indexed_fields))
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach($d->indexed_fields as $k => $v)
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs"><span class="font-mono text-slate-500">{{ $k }}:</span>&nbsp;<span class="font-medium text-slate-800">{{ \Illuminate\Support\Str::limit((string) $v, 40) }}</span></span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mt-2 text-xs text-slate-400">Keine Felder erkannt.</div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('documents.start_workflow', $d) }}" class="shrink-0 flex items-center gap-2">
                                @csrf
                                <select name="workflow_id" required class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Workflow waehlen…</option>
                                    @foreach($workflows as $w)
                                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                <x-primary-button>Starten</x-primary-button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $documents->links() }}</div>
        @endif
    </x-card>

    <div class="text-xs text-slate-500 mt-3">
        Tipp: erkannte Felder stehen im Workflow als <code>@{{ doc.indexed_fields.&lt;feld&gt; }}</code> zur Verfuegung — auch in Bedingungs-Knoten und im Lookup-Empfaenger.
    </div>
</x-app-layout>
