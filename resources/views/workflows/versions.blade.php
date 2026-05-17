<x-app-layout>
    <x-slot name="header">Versionen: {{ $workflow->name }}</x-slot>
    <x-slot name="subheader">Jede Speicherung erzeugt eine neue, unveraenderbare Version.</x-slot>

    <div class="mb-4">
        <a href="{{ route('workflows.design', $workflow) }}" class="text-sm text-indigo-600 hover:text-indigo-500">&larr; Zurueck zum Designer</a>
    </div>

    <x-card>
        @if($versions->isEmpty())
            <p class="text-sm text-slate-500">Noch keine Versionen vorhanden.</p>
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
                        @if($workflow->current_version_id !== $v->id && auth()->user()->hasPermission('workflows.design'))
                            <form method="POST" action="{{ route('workflows.versions.restore', [$workflow, $v]) }}" onsubmit="return confirm('Diese Version als neuen Stand wiederherstellen?')">
                                @csrf
                                <x-secondary-button>Wiederherstellen</x-secondary-button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4">{{ $versions->links() }}</div>
    </x-card>
</x-app-layout>
