@props([
    'value' => null,        // float|int|string|null
    'currency' => '€',     // Symbol oder ISO-Code
    'decimals' => 2,
    'fallback' => '—',
])

@php
    $n = null;
    if (is_numeric($value)) {
        $n = (float) $value;
    } elseif (is_string($value) && trim($value) !== '') {
        // toleriert "1.234,56" oder "1,234.56"
        $clean = preg_replace('/[^\d,.\-]/', '', $value);
        $hasComma = str_contains($clean, ',');
        $hasDot = str_contains($clean, '.');
        if ($hasComma && $hasDot) {
            // letztes Trennzeichen ist Dezimal
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');
            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasComma) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }
        $n = is_numeric($clean) ? (float) $clean : null;
    }
@endphp

@if($n !== null)
    <span class="tabular-nums whitespace-nowrap" {{ $attributes }}>
        {{ number_format($n, $decimals, ',', '.') }}&nbsp;{{ $currency }}
    </span>
@else
    <span class="text-slate-400">{{ $fallback }}</span>
@endif
