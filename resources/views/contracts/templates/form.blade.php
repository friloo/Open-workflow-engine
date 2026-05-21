<x-app-layout>
    <x-slot name="header">{{ $template->exists ? 'Vorlage bearbeiten' : 'Neue Vertrags-Vorlage' }}</x-slot>
    <x-slot name="subheader">{{ $template->name ?? 'HTML mit Mustache-Platzhaltern' }}</x-slot>

    <x-breadcrumbs :items="[
        ['title' => 'Verträge', 'url' => route('contracts.index')],
        ['title' => 'Vorlagen', 'url' => route('contract-templates.index')],
        ['title' => $template->exists ? $template->name : 'Neu'],
    ]" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                <form method="POST"
                      action="{{ $template->exists ? route('contract-templates.update', $template) : route('contract-templates.store') }}"
                      class="space-y-4">
                    @csrf
                    @if($template->exists) @method('PUT') @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Name der Vorlage" />
                            <x-text-input id="name" name="name" value="{{ old('name', $template->name) }}" required maxlength="128" />
                        </div>
                        <div>
                            <x-input-label for="contract_type_id" value="Standard-Vertragsart (optional)" />
                            <select id="contract_type_id" name="contract_type_id"
                                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— alle Arten —</option>
                                @foreach($types as $t)
                                    <option value="{{ $t->id }}" @selected(old('contract_type_id', $template->contract_type_id) == $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="description" value="Beschreibung (intern)" />
                            <x-text-input id="description" name="description" value="{{ old('description', $template->description) }}" maxlength="255" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="body_html" value="Vorlagen-Inhalt (HTML mit Mustache-Platzhaltern)" />
                            <textarea id="body_html" name="body_html" rows="14" required
                                      class="block w-full rounded-lg border-slate-300 text-xs font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body_html', $template->body_html) }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">HTML erlaubt (h1/h2/p/strong/em/table/...). Platzhalter siehe rechts.</p>
                            <x-input-error :messages="$errors->get('body_html')" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('contract-templates.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</a>
                        <x-primary-button>Speichern</x-primary-button>
                    </div>
                </form>
            </x-card>
        </div>

        <div>
            <x-card title="Platzhalter" description="Werden beim Erzeugen aus dem Vertrag ausgefüllt.">
                <ul class="space-y-1 text-xs">
                    @foreach($placeholders as $key => $sample)
                        <li class="flex items-baseline justify-between gap-2 border-b border-slate-100 pb-1">
                            <code class="bg-slate-100 px-1 rounded">{{ '{{ '.$key.' }}' }}</code>
                            <span class="text-slate-500 text-right">Beispiel: <em>{{ \Illuminate\Support\Str::limit($sample, 25) ?: '—' }}</em></span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
