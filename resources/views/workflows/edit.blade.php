<x-app-layout>
    <x-slot name="header">Workflow-Metadaten: {{ $workflow->name }}</x-slot>

    @php($publicForms = \App\Models\Form::where('workflow_id', $workflow->id)->where('is_public', true)->get())
    @if($publicForms->isNotEmpty())
        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
            <strong>Verknuepfte oeffentliche Formulare:</strong>
            <ul class="mt-1 list-disc ps-5">
                @foreach($publicForms as $pf)
                    <li><a href="{{ route('forms.public.show', $pf->public_slug) }}" target="_blank" class="underline">{{ url('/formular/'.$pf->public_slug) }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-card>
        <form method="POST" action="{{ route('workflows.update', $workflow) }}">
            @method('PUT')
            @include('workflows._form')
            <div class="mt-8 flex justify-between gap-3">
                <div class="flex gap-2">
                    @if($workflow->status !== 'active' && auth()->user()->hasPermission('workflows.design'))
                        <form method="POST" action="{{ route('workflows.activate', $workflow) }}">
                            @csrf
                            <x-secondary-button>Aktivieren</x-secondary-button>
                        </form>
                    @endif
                    @if($workflow->status === 'active' && auth()->user()->hasPermission('workflows.design'))
                        <form method="POST" action="{{ route('workflows.archive', $workflow) }}">
                            @csrf
                            <x-secondary-button>Archivieren</x-secondary-button>
                        </form>
                    @endif
                    @if($workflow->current_version_id)
                        <a href="{{ route('workflows.simulate.show', $workflow) }}"
                           class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Trockenlauf
                        </a>
                        <a href="{{ route('workflows.templates.export', $workflow) }}"
                           class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Als Vorlage exportieren
                        </a>
                    @endif
                    <form method="POST" action="{{ route('workflows.destroy', $workflow) }}" onsubmit="return confirm('Workflow wirklich loeschen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm hover:bg-rose-50">Loeschen</button>
                    </form>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('workflows.design', $workflow) }}"><x-secondary-button type="button">Zum Designer</x-secondary-button></a>
                    <x-primary-button>Speichern</x-primary-button>
                </div>
            </div>
        </form>
    </x-card>
</x-app-layout>
