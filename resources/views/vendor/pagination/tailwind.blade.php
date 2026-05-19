@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-slate-500">
            @if($paginator->total() > 0)
                Eintraege
                <strong class="text-slate-700">{{ $paginator->firstItem() }}</strong>
                bis
                <strong class="text-slate-700">{{ $paginator->lastItem() }}</strong>
                von
                <strong class="text-slate-700">{{ $paginator->total() }}</strong>
            @endif
        </div>

        <div class="flex items-center gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs text-slate-400 cursor-default">
                    &larr; vorherige
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-700 hover:bg-slate-50">
                    &larr; vorherige
                </a>
            @endif

            {{-- Page numbers (compact: aktuelle +- 2) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex items-center px-2 py-1.5 text-xs text-slate-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-indigo-600 text-xs font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-700 hover:bg-slate-50">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-700 hover:bg-slate-50">
                    naechste &rarr;
                </a>
            @else
                <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs text-slate-400 cursor-default">
                    naechste &rarr;
                </span>
            @endif
        </div>
    </nav>
@elseif($paginator->total() > 0)
    {{-- Eine Seite reicht — wir zeigen trotzdem die Treffer-Anzahl, damit der
         User sieht, dass die Liste wirklich vollstaendig ist. --}}
    <div class="text-xs text-slate-500">
        {{ $paginator->total() }} {{ $paginator->total() === 1 ? 'Eintrag' : 'Eintraege' }}
    </div>
@endif
