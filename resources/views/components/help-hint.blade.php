@props([
    'topic' => null,        // Help-Topic-Slug (z. B. 'sso', 'workflows')
    'anchor' => null,       // Optional: #anker innerhalb des Topics
    'text' => null,         // Inline-Tooltip-Text (alternativ zu topic)
    'label' => 'Hilfe',     // sr-only Bezeichnung
])

@php
    $href = null;
    if ($topic) {
        $href = route('help.show', $topic) . ($anchor ? '#'.$anchor : '');
    }
    $title = $text ?: ($topic ? 'Anleitung oeffnen' : 'Hilfe');
@endphp

<span class="inline-flex items-center" x-data="{ open: false }">
    @if($href)
        <a href="{{ $href }}" target="_blank" rel="noopener"
           title="{{ $title }}"
           @if($text) @mouseenter="open=true" @mouseleave="open=false" @endif
           class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-500 hover:bg-indigo-100 hover:text-indigo-700">
            <span aria-hidden="true">?</span>
            <span class="sr-only">{{ $label }}</span>
        </a>
    @else
        <button type="button"
                @click.away="open=false" @click="open=!open"
                title="{{ $title }}"
                class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-500 hover:bg-indigo-100 hover:text-indigo-700">
            <span aria-hidden="true">?</span>
            <span class="sr-only">{{ $label }}</span>
        </button>
    @endif

    @if($text)
        <span x-show="open" x-cloak x-transition
              class="absolute z-10 mt-6 max-w-xs rounded-md bg-slate-900 px-3 py-1.5 text-xs text-white shadow-lg">
            {{ $text }}
        </span>
    @endif
</span>
