@php($isNew = ! $form->exists)
<x-app-layout>
    <x-slot name="header">{{ $isNew ? 'Neues Formular' : $form->name }}</x-slot>

    <form method="POST" action="{{ $isNew ? route('forms.store') : route('forms.update', $form) }}"
        x-data='@json([
            "fields" => collect($form->schema ?? [])->map(fn ($f) => array_merge($f, ["_optionsText" => is_array($f["options"] ?? null) ? implode("\n", $f["options"]) : ""]))->all()
        ])'>
        @csrf
        @if(! $isNew) @method('PUT') @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Stammdaten">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" value="{{ old('name', $form->name) }}" required />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="description" value="Beschreibung" />
                            <textarea id="description" name="description" rows="2" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $form->description) }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="workflow_id" value="Zugeordneter Workflow" />
                            <select id="workflow_id" name="workflow_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— nur Datenerfassung —</option>
                                @foreach($workflows as $wf)
                                    <option value="{{ $wf->id }}" @selected(old('workflow_id', $form->workflow_id) == $wf->id)>{{ $wf->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Bei Abgabe wird der gewaehlte aktive Workflow gestartet.</p>
                        </div>
                        <div>
                            <x-input-label for="public_slug" value="Oeffentlicher Pfad (URL-Slug)" />
                            <x-text-input id="public_slug" name="public_slug" value="{{ old('public_slug', $form->public_slug) }}" placeholder="z. B. urlaubsantrag" />
                            <x-input-error :messages="$errors->get('public_slug')" />
                            @if($form->is_public && $form->public_slug)
                                <p class="mt-1 text-xs text-slate-500">{{ url('/formular/'.$form->public_slug) }}</p>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="hidden" name="is_public" value="0">
                                <input type="checkbox" name="is_public" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_public', $form->is_public))>
                                Oeffentlich erreichbar (kein Login noetig)
                            </label>
                        </div>
                    </div>
                </x-card>

                <x-card title="Formularfelder" description="Reihenfolge entspricht der Anzeige im Formular.">
                    <div class="space-y-3">
                        <template x-for="(field, idx) in fields" :key="idx">
                            <div class="rounded-lg border border-slate-200 p-3">
                                <input type="hidden" :name="`schema[${idx}][key]`" x-model="field.key">
                                <input type="hidden" :name="`schema[${idx}][label]`" x-model="field.label">
                                <input type="hidden" :name="`schema[${idx}][type]`" x-model="field.type">
                                <input type="hidden" :name="`schema[${idx}][required]`" :value="field.required ? '1' : '0'">
                                <template x-for="(opt, oi) in (field.options || [])" :key="oi">
                                    <input type="hidden" :name="`schema[${idx}][options][${oi}]`" :value="opt">
                                </template>

                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-700">Feld <span x-text="idx+1"></span></span>
                                    <button type="button" @click="fields.splice(idx,1)" class="text-xs text-rose-600 hover:text-rose-500">entfernen</button>
                                </div>
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Bezeichnung</label>
                                        <input type="text" x-model="field.label" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Key (intern)</label>
                                        <input type="text" x-model="field.key"
                                            @input="field.key = field.key.toString().toLowerCase().replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'')"
                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600">Typ</label>
                                        <select x-model="field.type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="text">Text</option>
                                            <option value="textarea">Mehrzeiliger Text</option>
                                            <option value="number">Zahl</option>
                                            <option value="date">Datum</option>
                                            <option value="select">Dropdown</option>
                                            <option value="radio">Radio</option>
                                            <option value="checkbox">Checkbox (ja/nein)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center gap-4">
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                        <input type="checkbox" x-model="field.required" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        Pflichtfeld
                                    </label>
                                </div>
                                <template x-if="['select','radio'].includes(field.type)">
                                    <div class="mt-2">
                                        <label class="block text-xs font-medium text-slate-600">Optionen (eine pro Zeile)</label>
                                        <textarea x-model="field._optionsText" @input="field.options = field._optionsText.split('\n').map(s => s.trim()).filter(Boolean)" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <button type="button" @click="fields.push({label: 'Feld ' + (fields.length+1), key: 'feld_' + (fields.length+1), type: 'text', required: false, options: [], _optionsText: ''})"
                            class="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">+ Feld hinzufuegen</button>
                    </div>
                </x-card>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('forms.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                    <x-primary-button>{{ $isNew ? 'Anlegen' : 'Speichern' }}</x-primary-button>
                </div>
            </div>

            @if(! $isNew)
                <div class="space-y-6">
                    <x-card title="Vorschau">
                        <div class="space-y-3 text-sm">
                            <template x-for="(f, i) in fields" :key="i">
                                <div>
                                    <div class="text-xs font-medium text-slate-600" x-text="f.label + (f.required ? ' *' : '')"></div>
                                    <template x-if="f.type==='textarea'"><textarea disabled rows="3" class="block w-full rounded-lg border-slate-200 bg-slate-50 text-sm"></textarea></template>
                                    <template x-if="['text','number','date'].includes(f.type)"><input :type="f.type" disabled class="block w-full rounded-lg border-slate-200 bg-slate-50 text-sm"></template>
                                    <template x-if="f.type==='select'">
                                        <select disabled class="block w-full rounded-lg border-slate-200 bg-slate-50 text-sm">
                                            <option>— bitte waehlen —</option>
                                            <template x-for="o in (f.options||[])"><option x-text="o"></option></template>
                                        </select>
                                    </template>
                                    <template x-if="f.type==='radio'">
                                        <div>
                                            <template x-for="o in (f.options||[])"><label class="block text-xs"><input type="radio" disabled> <span x-text="o"></span></label></template>
                                        </div>
                                    </template>
                                    <template x-if="f.type==='checkbox'"><label class="block text-xs"><input type="checkbox" disabled> <span x-text="f.label"></span></label></template>
                                </div>
                            </template>
                        </div>
                    </x-card>
                </div>
            @endif
        </div>
    </form>
</x-app-layout>
