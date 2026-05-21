@props([
    'value' => null,        // int|null Bytes
    'fallback' => '—',
])

@php
    $b = is_numeric($value) ? (int) $value : null;
    $out = null;
    if ($b !== null) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $f = (float) $b;
        while ($f >= 1024 && $i < count($units) - 1) { $f /= 1024; $i++; }
        $out = number_format($f, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }
@endphp

@if($out !== null)
    <span class="tabular-nums whitespace-nowrap" {{ $attributes }}>{{ $out }}</span>
@else
    <span class="text-slate-400">{{ $fallback }}</span>
@endif
