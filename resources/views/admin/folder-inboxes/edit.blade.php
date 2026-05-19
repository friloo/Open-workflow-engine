<x-app-layout>
    <x-slot name="header">{{ $inbox->exists ? 'Folder-Inbox bearbeiten' : 'Neue Folder-Inbox' }}</x-slot>

    <form method="POST" action="{{ $inbox->exists ? route('admin.folder-inboxes.update', $inbox) : route('admin.folder-inboxes.store') }}">
        @csrf
        @if($inbox->exists) @method('PUT') @endif

        <x-card title="Allgemein">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" :value="old('name', $inbox->name)" required />
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $inbox->is_active)) class="rounded border-slate-300 text-indigo-600">
                        Aktiv
                    </label>
                </div>
            </div>
        </x-card>

        <x-card title="Pfad">
            <div class="space-y-3">
                <div>
                    <x-input-label for="path" value="Ordner-Pfad" />
                    <x-text-input id="path" name="path" :value="old('path', $inbox->path)" required class="font-mono" placeholder="/var/scans/eingang  oder  scanner-inbox" />
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="use_storage_disk" value="1" @checked(old('use_storage_disk', $inbox->use_storage_disk)) class="rounded border-slate-300 text-indigo-600">
                    <span>Pfad relativ zu <code>storage/app/</code> (sicherer fuer Shared Hosting)</span>
                </label>
            </div>
        </x-card>

        <x-card title="Verarbeitung">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="document_type" value="Dokumenttyp" />
                    <select id="document_type" name="document_type" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— ohne —</option>
                        @foreach($documentTypes as $t)
                            <option value="{{ $t }}" @selected(old('document_type', $inbox->document_type) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="workflow_id" value="Workflow starten (optional)" />
                    <select id="workflow_id" name="workflow_id" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— keiner —</option>
                        @foreach($workflows as $wf)
                            <option value="{{ $wf->id }}" @selected((int) old('workflow_id', $inbox->workflow_id) === $wf->id)>{{ $wf->name }} ({{ $wf->status }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="after_import" value="Nach erfolgreichem Import" />
                    <select id="after_import" name="after_import" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="delete" @selected(old('after_import', $inbox->after_import) === 'delete')>Datei loeschen</option>
                        <option value="move" @selected(old('after_import', $inbox->after_import) === 'move')>In Unterordner verschieben</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="processed_subfolder" value="Zielordner (bei Verschieben)" />
                    <x-text-input id="processed_subfolder" name="processed_subfolder" :value="old('processed_subfolder', $inbox->processed_subfolder ?: 'verarbeitet')" class="font-mono" />
                </div>
                <div class="md:col-span-2">
                    <x-input-label for="extensions_raw" value="Erlaubte Endungen (kommagetrennt, leer = pdf+gaengige Bilder)" />
                    <x-text-input id="extensions_raw" name="extensions_raw" :value="old('extensions_raw', $inbox->extensions ? implode(', ', $inbox->extensions) : '')" class="font-mono" placeholder="pdf, png, jpg" />
                </div>
            </div>
        </x-card>

        <div class="flex justify-between">
            <a href="{{ route('admin.folder-inboxes.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Abbrechen</a>
            <x-primary-button>Speichern</x-primary-button>
        </div>
    </form>

    @if($inbox->exists)
        <form method="POST" action="{{ route('admin.folder-inboxes.destroy', $inbox) }}" class="mt-6" onsubmit="return confirm('Folder-Inbox loeschen? (Dateien im Ordner bleiben unangetastet)')">
            @csrf @method('DELETE')
            <button class="text-sm text-rose-600 hover:text-rose-500">Folder loeschen</button>
        </form>
    @endif
</x-app-layout>
