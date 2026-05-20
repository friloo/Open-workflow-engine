@php
    $tabs = [
        ['key' => 'tasks',    'label' => 'Aufgaben',     'count' => $counts['tasks'],    'route' => route('tasks.index'),                            'show' => true],
        ['key' => 'postkorb', 'label' => 'Posteingang',  'count' => $counts['postkorb'], 'route' => route('documents.inbox'),                        'show' => $showPostkorb],
        ['key' => 'snoozed',  'label' => 'Wiedervorlage','count' => $counts['snoozed'],  'route' => route('tasks.index', ['filter' => 'snoozed']),   'show' => true],
    ];
@endphp

<div class="mb-4 border-b border-slate-200">
    <nav class="-mb-px flex flex-wrap gap-1 overflow-x-auto" aria-label="Eingang">
        @foreach($tabs as $tab)
            @continue(! $tab['show'])
            @php($active = $current === $tab['key'])
            <a href="{{ $tab['route'] }}"
               class="whitespace-nowrap inline-flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium
                      {{ $active
                          ? 'border-indigo-500 text-indigo-700'
                          : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                <span>{{ $tab['label'] }}</span>
                @if($tab['count'] > 0)
                    <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold
                                 {{ $active ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $tab['count'] > 99 ? '99+' : $tab['count'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
