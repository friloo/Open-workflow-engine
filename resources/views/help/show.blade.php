<x-app-layout>
    <x-slot name="header">Anleitung</x-slot>
    <x-slot name="subheader">Bedienung und Konzepte der Open Workflow Engine.</x-slot>

    <div class="grid grid-cols-12 gap-6">
        {{-- Linke Sidebar: Gruppen --}}
        <aside class="col-span-12 lg:col-span-3">
            <div class="lg:sticky lg:top-20">
                <x-card>
                    <nav class="space-y-5 text-sm">
                        @foreach($sections as $sectionLabel => $items)
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                    {{ $sectionLabel }}
                                </div>
                                <ul class="space-y-0.5">
                                    @foreach($items as $slug => $label)
                                        <li>
                                            <a href="{{ route('help.show', $slug) }}"
                                                class="block rounded-md px-2.5 py-1 text-[13px] leading-snug {{ $topic === $slug ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700 hover:bg-slate-50' }}">
                                                {{ $label }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </nav>
                </x-card>
            </div>
        </aside>

        {{-- Hauptinhalt --}}
        <div class="col-span-12 lg:col-span-{{ count($toc) > 1 ? '6' : '9' }}">
            <x-card>
                <article class="prose prose-slate max-w-none
                                prose-headings:text-slate-900 prose-headings:scroll-mt-24
                                prose-h1:text-2xl prose-h1:font-semibold prose-h1:mb-4
                                prose-h2:text-xl prose-h2:mt-8 prose-h2:pb-1 prose-h2:border-b prose-h2:border-slate-100
                                prose-h3:text-base prose-h3:mt-6
                                prose-a:text-indigo-600 prose-a:no-underline hover:prose-a:underline
                                prose-code:bg-slate-100 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-[0.85em] prose-code:font-normal prose-code:before:content-none prose-code:after:content-none
                                prose-pre:bg-slate-900 prose-pre:text-slate-100
                                prose-li:my-1 prose-ul:my-3 prose-ol:my-3
                                prose-img:rounded-lg prose-img:border prose-img:border-slate-200">
                    {!! $html !!}
                </article>
            </x-card>
        </div>

        {{-- Rechte Mini-TOC nur wenn 2+ Headings --}}
        @if(count($toc) > 1)
            <aside class="col-span-12 lg:col-span-3 hidden lg:block">
                <div class="lg:sticky lg:top-20">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">
                            Auf dieser Seite
                        </div>
                        <nav class="space-y-1 text-xs">
                            @foreach($toc as $item)
                                <a href="#{{ $item['id'] }}"
                                    class="block text-slate-600 hover:text-indigo-600 truncate
                                            {{ $item['level'] === 3 ? 'pl-3 text-slate-500' : '' }}">
                                    {{ $item['title'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </div>
            </aside>
        @endif
    </div>
</x-app-layout>
