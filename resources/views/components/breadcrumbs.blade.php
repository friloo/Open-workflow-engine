@props(['items'])
{{-- $items = [['title' => 'Dokumente', 'url' => '/dokumente'], ['title' => 'Rechnung-X.pdf']]
     Letzter Eintrag wird als aktiv gerendert (kein Link). --}}

<nav aria-label="Breadcrumb" class="text-sm">
    <ol class="flex flex-wrap items-center gap-1 text-slate-500">
        @foreach($items as $i => $item)
            @php($isLast = $i === count($items) - 1)
            <li class="flex items-center gap-1">
                @if($i > 0)
                    <svg class="h-3.5 w-3.5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                @endif
                @if($isLast || empty($item['url']))
                    <span class="font-medium text-slate-700 truncate max-w-[40ch]">{{ $item['title'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" class="hover:text-slate-700 truncate max-w-[40ch]">{{ $item['title'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
