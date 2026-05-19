<x-app-layout>
    <x-slot name="header">{{ $case->name }}</x-slot>
    <x-slot name="subheader">
        @if($case->reference)<code class="text-xs">{{ $case->reference }}</code> · @endif
        {{ $case->attachments->count() }} Dokument(e)
        @if($case->closed_at) · <span class="text-slate-500">geschlossen {{ $case->closed_at->format('d.m.Y') }}</span>@endif
    </x-slot>

    <div class="mb-4 flex items-center justify-end gap-3">
        <a href="{{ route('cases.edit', $case) }}" class="text-sm text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
        <form method="POST" action="{{ route('cases.close', $case) }}" class="inline">
            @csrf
            <button class="text-sm text-amber-700 hover:text-amber-800">{{ $case->closed_at ? 'Wieder oeffnen' : 'Akte schliessen' }}</button>
        </form>
        <form method="POST" action="{{ route('cases.destroy', $case) }}" class="inline" onsubmit="return confirm('Akte loeschen? Die Dokumente bleiben erhalten, nur die Zuordnung wird geloest.')">
            @csrf @method('DELETE')
            <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
        </form>
    </div>

    @if($case->description)
        <x-card>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $case->description }}</p>
        </x-card>
    @endif

    <x-card title="Dokumente">
        @if($case->attachments->isEmpty())
            <x-empty-state icon="document"
                title="Noch keine Dokumente in dieser Akte"
                description='In der Dokumenten-Liste mehrere Dokumente auswaehlen und ueber Bulk-Aktion → "Zu Akte hinzufuegen" dieser Akte zuordnen.' />
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
</x-app-layout>
