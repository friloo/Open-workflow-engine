@props([
    'value' => null,        // Carbon|string|null
    'format' => 'd.m.Y',    // 'd.m.Y' (Default), 'd.m.Y H:i', 'relative', 'iso'
    'fallback' => '—',
])

@php
    $cv = null;
    if ($value instanceof \DateTimeInterface) {
        $cv = \Illuminate\Support\Carbon::instance($value);
    } elseif (is_string($value) && $value !== '') {
        try { $cv = \Illuminate\Support\Carbon::parse($value); } catch (\Throwable) { $cv = null; }
    } elseif (is_numeric($value)) {
        $cv = \Illuminate\Support\Carbon::createFromTimestamp((int) $value);
    }
@endphp

@if($cv)
    @if($format === 'relative')
        <time datetime="{{ $cv->toIso8601String() }}" title="{{ $cv->format('d.m.Y H:i') }}">{{ $cv->diffForHumans() }}</time>
    @elseif($format === 'iso')
        <time datetime="{{ $cv->toIso8601String() }}">{{ $cv->toIso8601String() }}</time>
    @else
        <time datetime="{{ $cv->toIso8601String() }}">{{ $cv->format($format) }}</time>
    @endif
@else
    <span class="text-slate-400">{{ $fallback }}</span>
@endif
