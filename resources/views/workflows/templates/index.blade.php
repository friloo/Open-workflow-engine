<x-app-layout>
    <x-slot name="header">Workflow-Vorlagen</x-slot>
    <x-slot name="subheader">Vorgefertigte Workflows zum Importieren — du kannst sie danach im Designer anpassen.</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('workflows.templates.import.show') }}"><x-primary-button type="button">Eigene Vorlage importieren</x-primary-button></a>
    </div>

    <x-card>
        @if(empty($templates))
            <p class="text-sm text-slate-500">Keine Vorlagen vorhanden.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($templates as $t)
                    <li class="py-4 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-900">{{ $t['name'] }}</span>
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $t['trigger_type'] }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600 whitespace-pre-line">{{ $t['description'] }}</p>
                        </div>
                        <form method="POST" action="{{ route('workflows.templates.import.store') }}" class="shrink-0">
                            @csrf
                            <input type="hidden" name="source" value="builtin">
                            <input type="hidden" name="builtin" value="{{ $t['slug'] }}">
                            <x-primary-button>Importieren</x-primary-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <p class="mt-4 text-xs text-slate-500">
        Nach dem Import landest du im Designer. Der Workflow ist im Status <em>Entwurf</em> — pruefe Empfaenger (Rollen/Listen) und aktiviere ihn dann.
    </p>
</x-app-layout>
