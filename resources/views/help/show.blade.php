<x-app-layout :full="true">

    <div class="flex h-[calc(100vh-4rem)]">

        {{-- Linke Sidebar: Navigation --}}
        <aside class="w-56 flex-none border-r border-slate-200 bg-white overflow-y-auto">
            <div class="px-3 pt-4 pb-2">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 px-2">Anleitung</h2>
            </div>
            <nav class="px-3 pb-6 space-y-4 text-[13px]">
                @foreach($sections as $sectionLabel => $items)
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 px-2 mb-1">
                            {{ $sectionLabel }}
                        </div>
                        <ul>
                            @foreach($items as $slug => $label)
                                <li>
                                    <a href="{{ route('help.show', $slug) }}"
                                       class="block rounded px-2 py-0.5 leading-snug truncate
                                              {{ $topic === $slug
                                                  ? 'bg-indigo-50 text-indigo-700 font-medium'
                                                  : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>

        {{-- Hauptinhalt --}}
        <div class="flex-1 min-w-0 overflow-y-auto">
            <div class="mx-auto max-w-3xl px-8 py-8">
                <article class="prose prose-slate max-w-none
                                prose-headings:text-slate-900 prose-headings:scroll-mt-24
                                prose-h1:text-2xl prose-h1:font-semibold prose-h1:mb-4
                                prose-h2:text-xl prose-h2:mt-8 prose-h2:pb-1 prose-h2:border-b prose-h2:border-slate-100
                                prose-h3:text-base prose-h3:mt-6
                                prose-a:text-indigo-600 prose-a:no-underline hover:prose-a:underline
                                prose-code:bg-slate-100 prose-code:text-slate-900 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-[0.85em] prose-code:font-normal prose-code:before:content-none prose-code:after:content-none
                                prose-pre:bg-slate-900 prose-pre:text-slate-100 prose-pre:p-4 prose-pre:overflow-x-auto
                                [&_pre_code]:bg-transparent [&_pre_code]:text-slate-100 [&_pre_code]:p-0 [&_pre_code]:rounded-none [&_pre_code]:text-sm
                                prose-li:my-1 prose-ul:my-3 prose-ol:my-3
                                prose-img:rounded-lg prose-img:border prose-img:border-slate-200">
                    {!! $html !!}
                </article>
            </div>
        </div>

        {{-- Rechte Mini-TOC --}}
        @if(count($toc) > 1)
            <aside class="w-48 flex-none border-l border-slate-200 bg-white overflow-y-auto hidden xl:block">
                <div class="px-4 pt-6 pb-4 sticky top-0">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">
                        Auf dieser Seite
                    </div>
                    <nav class="space-y-0.5 text-xs">
                        @foreach($toc as $item)
                            <a href="#{{ $item['id'] }}"
                               class="block py-0.5 text-slate-500 hover:text-indigo-600 truncate
                                      {{ $item['level'] === 3 ? 'pl-3' : '' }}">
                                {{ $item['title'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>
        @endif

    </div>
</x-app-layout>
