<x-app-layout>
    <x-slot name="header">Formulare</x-slot>
    <x-slot name="subheader">Stand-Alone-Formulare zum Sammeln von Daten oder Anstossen von Workflows.</x-slot>

    <div class="mb-4 flex justify-end">
        @if(auth()->user()->hasPermission('forms.manage'))
            <a href="{{ route('forms.create') }}"><x-primary-button type="button">Neues Formular</x-primary-button></a>
        @endif
    </div>

    <x-card>
        @if($forms->isEmpty())
            <x-empty-state icon="form"
                title="Noch keine Formulare"
                description="Lege ein Stand-Alone-Formular an — fuer reine Datensammlung oder als Trigger fuer einen Workflow.">
                @if(auth()->user()->hasPermission('forms.manage'))
                    <a href="{{ route('forms.create') }}"><x-primary-button type="button">Neues Formular</x-primary-button></a>
                    <a href="{{ route('help.show', 'forms') }}" class="text-sm text-slate-600 hover:text-slate-900">Anleitung lesen</a>
                @endif
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                            <th class="py-2 pr-4">Name</th>
                            <th class="py-2 pr-4">Workflow</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Eingaenge</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($forms as $f)
                            <tr>
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-slate-900">{{ $f->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $f->description }}</div>
                                </td>
                                <td class="py-3 pr-4 text-slate-700">
                                    @if($f->workflow)
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $f->workflow->name }}</span>
                                    @else
                                        <span class="text-slate-400 text-xs">nur Datenerfassung</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    @if($f->is_public)
                                        <a href="{{ route('forms.public.show', $f->public_slug) }}" target="_blank" class="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 hover:bg-sky-100">oeffentlich</a>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">intern</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-slate-700">{{ $f->submissions_count }}</td>
                                <td class="py-3 text-right space-x-3">
                                    <a href="{{ route('forms.submissions.index', $f) }}" class="text-sm text-slate-600 hover:text-slate-900">Eingaenge</a>
                                    @if(auth()->user()->hasPermission('forms.manage'))
                                        <a href="{{ route('forms.edit', $f) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Bearbeiten</a>
                                        <form method="POST" action="{{ route('forms.destroy', $f) }}" class="inline" onsubmit="return confirm('Formular wirklich loeschen?')">
                                            @csrf @method('DELETE')
                                            <button class="text-sm text-rose-600 hover:text-rose-500">Loeschen</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $forms->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
