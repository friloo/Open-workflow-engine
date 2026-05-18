<x-app-layout>
    <x-slot name="header">{{ $attachment->original_name }}</x-slot>
    <x-slot name="subheader">{{ $attachment->document_type ?: 'Ohne Dokumenttyp' }} · {{ $attachment->sizeFormatted() }} · {{ $attachment->created_at->format('d.m.Y H:i') }}</x-slot>

    <div class="mb-4"><a href="{{ route('documents.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Dokumente</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card title="Extrahierter Text" description="Maschinenlesbar gemachte Inhalte fuer Volltextsuche.">
                @if($attachment->ocr_text)
                    <pre class="max-h-[60vh] overflow-auto rounded-lg bg-slate-50 p-4 text-xs text-slate-800 whitespace-pre-wrap">{{ $attachment->ocr_text }}</pre>
                @else
                    <p class="text-sm text-slate-500">Kein Text extrahiert. Status: <strong>{{ $attachment->ocr_status }}</strong></p>
                @endif
                @if(in_array($attachment->ocr_status, ['pending','failed','skipped']))
                    <form method="POST" action="{{ route('documents.reindex', $attachment) }}" class="mt-4">
                        @csrf
                        <x-secondary-button>OCR erneut versuchen</x-secondary-button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Datei">
                <a href="{{ route('attachments.download', $attachment) }}" target="_blank" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Datei oeffnen</a>
                <dl class="mt-4 space-y-2 text-xs">
                    <div><dt class="text-slate-500">Original-Name</dt><dd class="text-slate-900">{{ $attachment->original_name }}</dd></div>
                    <div><dt class="text-slate-500">Mime-Type</dt><dd class="text-slate-900">{{ $attachment->mime_type }}</dd></div>
                    <div><dt class="text-slate-500">Groesse</dt><dd class="text-slate-900">{{ $attachment->sizeFormatted() }}</dd></div>
                    <div><dt class="text-slate-500">Hochgeladen</dt><dd class="text-slate-900">{{ $attachment->created_at->format('d.m.Y H:i:s') }} von {{ $attachment->uploader?->name ?? 'System' }}</dd></div>
                    <div><dt class="text-slate-500">OCR</dt><dd class="text-slate-900">{{ $attachment->ocr_status }} @if($attachment->ocr_tool)· {{ $attachment->ocr_tool }} @endif @if($attachment->ocr_extracted_at)· {{ $attachment->ocr_extracted_at->format('d.m.Y H:i') }}@endif</dd></div>
                    <div><dt class="text-slate-500">SHA-256</dt><dd class="text-slate-900 font-mono text-xs break-all">{{ $attachment->content_hash }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Kontext">
                @if($attachment->attachable_type)
                    <p class="text-sm text-slate-700">Gehoert zu <code class="bg-slate-100 rounded px-1">{{ class_basename($attachment->attachable_type) }}#{{ $attachment->attachable_id }}</code></p>
                    @php($att = $attachment->attachable)
                    @if($att instanceof \App\Models\Asset)
                        <a href="{{ route('assets.edit', $att) }}" class="mt-2 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Asset oeffnen: {{ $att->name }}</a>
                    @elseif($att instanceof \App\Models\WorkflowInstance)
                        <a href="{{ route('workflow-instances.show', $att) }}" class="mt-2 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Vorgang #{{ $att->id }}</a>
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
