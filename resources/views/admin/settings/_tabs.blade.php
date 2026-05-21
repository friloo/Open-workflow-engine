@props(['sections', 'current'])

<div class="border-b border-slate-200 mb-6">
    <nav class="-mb-px flex flex-wrap gap-1 overflow-x-auto" aria-label="Tabs">
        @foreach($sections as $section)
            @php($active = $section['slug'] === $current)
            <a href="{{ route($section['route']) }}"
               class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium
                      {{ $active
                          ? 'border-indigo-500 text-indigo-700'
                          : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                {{ $section['label'] }}
            </a>
        @endforeach
    </nav>
</div>
