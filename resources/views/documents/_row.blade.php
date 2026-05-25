@php
    $badge = $d->isPdf() ? ['PDF', 'bg-rose-100 text-rose-700']
        : ($d->isImage() ? ['IMG', 'bg-sky-100 text-sky-700']
        : ['DOC', 'bg-slate-100 text-slate-700']);
@endphp
<div class="flex items-center gap-2 min-w-0">
    <span class="grid h-5 w-7 shrink-0 place-items-center rounded text-[10px] font-bold {{ $badge[1] }}">{{ $badge[0] }}</span>
    <div class="min-w-0 flex-1">
        <div class="text-sm font-medium text-slate-900 truncate">{{ $d->original_name }}</div>
        <div class="text-[11px] text-slate-500 truncate">
            {{ $d->sizeFormatted() }}
            · <x-fmt-date :value="$d->created_at" format="d.m.Y" />
            @if($d->document_type) · <span class="text-indigo-600">{{ $d->document_type }}</span> @endif
        </div>
    </div>
</div>
