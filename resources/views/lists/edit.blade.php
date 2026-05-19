@php
    $isNew = ! $list->exists;
    $columnsJson = json_encode($list->columns ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
@endphp
<x-app-layout>
    <x-slot name="header">{{ $isNew ? 'Neue Liste' : $list->name }}</x-slot>
    <x-slot name="subheader">Spalten definieren und Eintraege per CSV importieren.</x-slot>

    <div class="mb-4">
        <a href="{{ route('lists.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Listen</a>
    </div>

    <form method="POST" action="{{ $isNew ? route('lists.store') : route('lists.update', $list) }}"
        x-data="{ columns: {{ $columnsJson }} }">
        @csrf
        @if(! $isNew)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Stammdaten">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" value="{{ old('name', $list->name) }}" required />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>
                        <div>
                            <x-input-label for="slug" value="Slug (intern, optional)" />
                            <x-text-input id="slug" name="slug" value="{{ old('slug', $list->slug) }}" placeholder="auto" />
                            <x-input-error :messages="$errors->get('slug')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="description" value="Beschreibung" />
                            <textarea id="description" name="description" rows="2"
                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $list->description) }}</textarea>
                        </div>
                    </div>
                </x-card>

                <x-card title="Spaltenstruktur" description="Eine Spalte muss als Schluessel markiert sein. Verantwortlich/Eskalation werden im Workflow als Empfaenger genutzt.">
                    <div class="space-y-3"
                         x-sort:config="{ animation: 150, handle: '.drag-handle' }"
                         x-sort="columns.splice($event.newIndex, 0, columns.splice($event.oldIndex, 1)[0])">
                        <template x-for="(c, idx) in columns" :key="idx">
                            <div class="rounded-lg border border-slate-200 p-3 bg-white" x-sort:item="idx">
                                <input type="hidden" :name="`columns[${idx}][key]`" x-model="c.key">
                                <input type="hidden" :name="`columns[${idx}][label]`" x-model="c.label">
                                <input type="hidden" :name="`columns[${idx}][type]`" x-model="c.type">
                                <input type="hidden" :name="`columns[${idx}][role]`" x-model="c.role">

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="drag-handle cursor-grab select-none text-slate-400 hover:text-slate-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M6 2.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm7-11a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm0 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
                                        </span>
                                        <span class="text-xs font-semibold text-slate-700">Spalte <span x-text="idx+1"></span></span>
                                    </div>
                                    <button type="button" @click="columns.splice(idx,1)" class="text-xs text-rose-600 hover:text-rose-500">entfernen</button>
                                </div>
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-4 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Bezeichnung</label>
                                        <input type="text" x-model="c.label" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Key (intern)</label>
                                        <input type="text" x-model="c.key" @input="c.key = c.key.toString().toLowerCase().replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'')" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Typ</label>
                                        <select x-model="c.type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="text">Text</option>
                                            <option value="email">E-Mail</option>
                                            <option value="number">Zahl</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Rolle</label>
                                        <select x-model="c.role" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="key">Schluessel</option>
                                            <option value="responsible">Verantwortlich</option>
                                            <option value="escalation">Eskalation</option>
                                            <option value="other">Sonstige</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <button type="button"
                            @click="columns.push({key: 'spalte_' + (columns.length+1), label: 'Spalte ' + (columns.length+1), type: 'text', role: 'other'})"
                            class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Spalte hinzufuegen</button>
                    </div>
                    <x-input-error :messages="$errors->get('columns')" />
                </x-card>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('lists.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                    <x-primary-button>{{ $isNew ? 'Anlegen' : 'Speichern' }}</x-primary-button>
                </div>

                <x-card title="Zugriff pro Rolle" description="Wenn KEINE Rolle markiert ist, sehen alle mit lists.view die Liste. Sobald mindestens eine Rolle gesetzt ist, sehen nur diese sie.">
                    @php($pivot = isset($list) && $list->exists ? $list->roles->keyBy('id') : collect())
                    <table class="min-w-full text-sm">
                        <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500">
                            <th class="py-1 pr-3">Rolle</th>
                            <th class="py-1 pr-3">Kein Zugriff</th>
                            <th class="py-1 pr-3">Nur lesen</th>
                            <th class="py-1 pr-3">Lesen + Bearbeiten</th>
                        </tr></thead>
                        <tbody>
                            @foreach(($allRoles ?? []) as $role)
                                @php($current = $pivot->get($role->id))
                                @php($access = $current ? ($current->pivot->can_edit ? 'edit' : 'view') : 'none')
                                <tr>
                                    <td class="py-1 pr-3 text-sm">{{ $role->name }} <code class="text-xs text-slate-500">{{ $role->slug }}</code></td>
                                    @foreach(['none', 'view', 'edit'] as $val)
                                        <td class="py-1 pr-3">
                                            <input type="radio" name="role_perms[{{ $role->id }}][access]" value="{{ $val }}" @checked($access === $val)>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            </div>

            <div class="space-y-6">
                @if(! $isNew)
                    <x-card title="CSV-Import" description="Spaltennamen muessen den Keys oben entsprechen.">
                        <p class="mb-3 text-xs text-slate-500">Tipp: Erst speichern, dann importieren.</p>
                        <a href="{{ route('lists.entries.export', $list) }}" class="inline-flex text-sm text-slate-600 hover:text-slate-900">CSV-Vorlage exportieren &darr;</a>
                    </x-card>
                @else
                    <x-card title="Hinweis">
                        <p class="text-xs text-slate-500">Nach dem Anlegen koennen Eintraege manuell oder per CSV erfasst werden.</p>
                    </x-card>
                @endif
            </div>
        </div>
    </form>

    @if(! $isNew)
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <x-card title="Eintraege" description="{{ isset($entries) ? $entries->total() : 0 }} insgesamt.">
                    @include('lists._entries_table', ['list' => $list, 'entries' => $entries ?? collect()])
                </x-card>
            </div>
            <x-card title="CSV-Upload">
                <form method="POST" enctype="multipart/form-data" action="{{ route('lists.entries.import', $list) }}" class="space-y-3">
                    @csrf
                    <input type="file" name="csv" accept=".csv" required
                        class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <label class="block text-xs font-medium text-slate-600">Trennzeichen</label>
                    <input type="text" name="delimiter" value=";" maxlength="1" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Importieren</button>
                </form>
            </x-card>
        </div>
    @endif
</x-app-layout>
