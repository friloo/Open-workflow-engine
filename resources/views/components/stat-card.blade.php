@props(['label', 'value', 'tone' => 'slate', 'hint' => null])
@php
    $tones = [
        'slate'   => ['ring' => 'ring-slate-200',   'text' => 'text-slate-900',   'label' => 'text-slate-500'],
        'indigo'  => ['ring' => 'ring-indigo-200',  'text' => 'text-indigo-700',  'label' => 'text-indigo-700/70'],
        'emerald' => ['ring' => 'ring-emerald-200', 'text' => 'text-emerald-700', 'label' => 'text-emerald-700/70'],
        'amber'   => ['ring' => 'ring-amber-200',   'text' => 'text-amber-700',   'label' => 'text-amber-700/70'],
        'rose'    => ['ring' => 'ring-rose-200',    'text' => 'text-rose-700',    'label' => 'text-rose-700/70'],
    ];
    $c = $tones[$tone] ?? $tones['slate'];
@endphp
<div class="rounded-lg bg-white p-4 shadow-sm ring-1 {{ $c['ring'] }}">
    <div class="text-xs font-semibold uppercase tracking-wide {{ $c['label'] }}">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold {{ $c['text'] }}">{{ $value }}</div>
    @if($hint)<div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>@endif
</div>
