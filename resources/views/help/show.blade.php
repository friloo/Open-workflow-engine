<x-app-layout>
    <x-slot name="header">Anleitung</x-slot>
    <x-slot name="subheader">Bedienung und Konzepte der Open Workflow Engine.</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <aside class="lg:col-span-1">
            <x-card>
                <nav class="space-y-1 text-sm">
                    @foreach($toc as $slug => $label)
                        <a href="{{ route('help.show', $slug) }}"
                            class="block rounded-md px-3 py-2 {{ $topic===$slug ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </x-card>
        </aside>
        <div class="lg:col-span-3">
            <x-card>
                <article class="prose prose-slate max-w-none prose-headings:text-slate-900 prose-h1:text-2xl prose-h2:text-xl prose-h3:text-lg prose-code:bg-slate-100 prose-code:px-1 prose-code:rounded prose-pre:bg-slate-900 prose-pre:text-slate-100">
                    {!! $html !!}
                </article>
            </x-card>
        </div>
    </div>
</x-app-layout>
