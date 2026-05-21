<x-app-layout>
    <x-slot name="header">Tags</x-slot>
    <x-slot name="subheader">Frei vergebbare Schlagwörter pro Dokument — z. B. "dringend", "intern", "rückfrage".</x-slot>

    <x-card title="Neuen Tag anlegen">
        <form method="POST" action="{{ route('tags.store') }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" name="name" required placeholder="z. B. dringend" />
            </div>
            <div>
                <x-input-label for="color" value="Farbe" />
                <input id="color" name="color" type="color" value="#6366f1" class="block h-9 w-12 rounded border border-slate-300">
            </div>
            <x-primary-button>Anlegen</x-primary-button>
        </form>
    </x-card>

    <x-card>
        @if($tags->isEmpty())
            <x-empty-state title="Noch keine Tags" description="Lege oben einen Tag an. Tags helfen, Dokumente quer über Archive zu gruppieren." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($tags as $tag)
                    <li class="py-2 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="inline-block h-4 w-4 rounded-full" style="background: {{ $tag->color }}"></span>
                            <span class="font-medium text-slate-900">{{ $tag->name }}</span>
                            <code class="text-xs text-slate-500">{{ $tag->slug }}</code>
                            <span class="text-xs text-slate-500">· {{ $tag->attachments_count }} Doks</span>
                        </div>
                        <form method="POST" action="{{ route('tags.destroy', $tag) }}" onsubmit="return confirm('Tag löschen?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-600 hover:text-rose-500">Löschen</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4">{{ $tags->links() }}</div>
    </x-card>
</x-app-layout>
