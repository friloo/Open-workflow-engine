<x-app-layout>
    <x-slot name="header">Workflow-Metadaten: {{ $workflow->name }}</x-slot>

    <x-card>
        <form method="POST" action="{{ route('workflows.update', $workflow) }}">
            @method('PUT')
            @include('workflows._form')
            <div class="mt-8 flex justify-between gap-3">
                <div class="flex gap-2">
                    @if($workflow->status !== 'active' && auth()->user()->hasPermission('workflows.publish'))
                        <form method="POST" action="{{ route('workflows.activate', $workflow) }}">
                            @csrf
                            <x-secondary-button>Aktivieren</x-secondary-button>
                        </form>
                    @endif
                    @if($workflow->status === 'active' && auth()->user()->hasPermission('workflows.publish'))
                        <form method="POST" action="{{ route('workflows.archive', $workflow) }}">
                            @csrf
                            <x-secondary-button>Archivieren</x-secondary-button>
                        </form>
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
