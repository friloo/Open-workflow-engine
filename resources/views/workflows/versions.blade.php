<x-app-layout>
    <x-slot name="header">Versionen: {{ $workflow->name }}</x-slot>
    <x-slot name="subheader">Jede Speicherung erzeugt eine neue, unveraenderbare Version.</x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('workflows.design', $workflow) }}" class="text-sm text-indigo-600 hover:text-indigo-500">&larr; Zurück zum Designer</a>
        @if($versions->count() >= 2)
            <a href="{{ route('workflows.versions.diff', $workflow) }}"
               class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Versionen vergleichen
            </a>
        @endif
    </div>

    <x-card>
        @if($versions->isEmpty())
            <x-empty-state title="Noch keine Versionen" description="Sobald du den Designer speicherst, wird die erste Version angelegt." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($versions as $v)
                    <li class="py-4 flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-base font-semibold text-slate-900">v{{ $v->version_number }}</span>
                                @if($workflow->current_version_id === $v->id)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">aktuell</span>
                                @endif
                            </div>
                            <div class="text-sm text-slate-500">
                                {{ $v->created_at->format('d.m.Y H:i:s') }} · {{ $v->creator?->name ?? 'System' }}
                            </div>
                            @if($v->change_summary)
                                <div class="mt-1 text-sm text-slate-700">{{ $v->change_summary }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('workflows.process_doc.version', [$workflow, $v]) }}" class="text-xs text-indigo-600 hover:text-indigo-500" title="Prozessbeschreibung dieser Version als PDF">PDF-Doku</a>
                            @if($workflow->current_version_id !== $v->id && auth()->user()->hasPermission('workflows.design'))
                                <form method="POST" action="{{ route('workflows.versions.restore', [$workflow, $v]) }}" onsubmit="return confirm('Diese Version als neuen Stand wiederherstellen?')">
                                    @csrf
                                    <x-secondary-button>Wiederherstellen</x-secondary-button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4">{{ $versions->links() }}</div>
    </x-card>
</x-app-layout>
