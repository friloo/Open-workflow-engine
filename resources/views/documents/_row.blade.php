@php
    $badge = $d->isPdf() ? ['PDF', 'bg-rose-100 text-rose-700']
        : ($d->isImage() ? ['IMG', 'bg-sky-100 text-sky-700']
        : ['DOC', 'bg-slate-100 text-slate-700']);
    $statusLabel = [
        'done' => ['indiziert', 'bg-emerald-50 text-emerald-700'],
        'pending' => ['pending', 'bg-amber-50 text-amber-700'],
        'failed' => ['OCR-Fehler', 'bg-rose-50 text-rose-700'],
        'skipped' => ['ohne Text', 'bg-slate-100 text-slate-600'],
    ][$d->ocr_status] ?? ['—', 'bg-slate-100 text-slate-600'];

    $snippet = null;
    if (! empty($q) && $d->ocr_text) {
        $pos = mb_stripos((string) $d->ocr_text, $q);
        if ($pos !== false) {
            $snippet = '...' . mb_substr($d->ocr_text, max(0, $pos - 80), 240) . '...';
        }
    }
@endphp
<li class="py-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="grid h-6 w-9 place-items-center rounded text-xs font-bold {{ $badge[1] }}">{{ $badge[0] }}</span>
                <a href="{{ route('documents.show', $d) }}" class="font-medium text-slate-900 hover:text-indigo-600 truncate">{{ $d->original_name }}</a>
                @if($d->document_type)
                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $d->document_type }}</span>
                @endif
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
            </div>
            <div class="mt-1 text-xs text-slate-500">
                {{ $d->sizeFormatted() }} · {{ $d->mime_type }} · {{ $d->created_at->format('d.m.Y H:i') }}
                @if($d->uploader) · von {{ $d->uploader->name }} @endif
                @if($d->attachable_type)
                    · gehoert zu <code class="text-xs bg-slate-100 rounded px-1">{{ class_basename($d->attachable_type) }}#{{ $d->attachable_id }}</code>
                @endif
            </div>
            @if($snippet)
                <p class="mt-2 text-xs text-slate-700 bg-yellow-50 px-2 py-1 rounded">{!! str_ireplace($q, '<strong>'.$q.'</strong>', e($snippet)) !!}</p>
            @endif
            @if(! empty($d->indexed_fields))
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach($d->indexed_fields as $k => $v)
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs"><span class="font-mono text-slate-500">{{ $k }}:</span>&nbsp;<span class="font-medium text-slate-800">{{ \Illuminate\Support\Str::limit((string) $v, 30) }}</span></span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="shrink-0 text-right space-x-3">
            <a href="{{ route('attachments.download', $d) }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-500">Oeffnen</a>
        </div>
    </div>
</li>
