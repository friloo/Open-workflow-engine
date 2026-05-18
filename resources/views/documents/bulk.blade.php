<x-app-layout>
    <x-slot name="header">Bulk-Upload</x-slot>
    <x-slot name="subheader">Mehrere Dokumente auf einmal hochladen und gemeinsam klassifizieren.</x-slot>

    <div class="mb-4"><a href="{{ route('documents.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Dokumente</a></div>

    <x-card>
        <form method="POST" enctype="multipart/form-data" action="{{ route('documents.bulk.store') }}"
              x-data="{ files: [], handle(e) { this.files = Array.from(e.target.files || []) } }">
            @csrf
            <div class="rounded-lg border-2 border-dashed border-slate-300 p-6 text-center hover:border-indigo-400 transition"
                 @click="$refs.f.click()"
                 @dragover.prevent="$el.classList.add('border-indigo-500','bg-indigo-50')"
                 @dragleave.prevent="$el.classList.remove('border-indigo-500','bg-indigo-50')"
                 @drop.prevent="$el.classList.remove('border-indigo-500','bg-indigo-50'); files = Array.from($event.dataTransfer.files || []); $refs.f.files = $event.dataTransfer.files">
                <input type="file" name="files[]" multiple x-ref="f" @change="handle" class="hidden"
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.doc,.docx,.xls,.xlsx,.txt,.csv">
                <p class="text-sm text-slate-700">Dateien hier ablegen oder klicken zum Auswaehlen.</p>
                <p class="mt-1 text-xs text-slate-500">Max. 50 Dateien, je max. 15 MB. PDF, Bild, Word, Excel, Text.</p>
                <ul class="mt-3 text-xs text-slate-700" x-show="files.length" style="display:none;">
                    <template x-for="f in files" :key="f.name">
                        <li class="py-0.5" x-text="`${f.name} (${(f.size/1024/1024).toFixed(2)} MB)`"></li>
                    </template>
                </ul>
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="document_type" value="Dokumenttyp (auf alle anwenden)" />
                    @if(! empty($types))
                        <select id="document_type" name="document_type" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— ohne —</option>
                            @foreach($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                        </select>
                    @else
                        <x-text-input id="document_type" name="document_type" placeholder="z. B. Vertrag, Rechnung" />
                    @endif
                </div>
                <div>
                    <x-input-label for="label" value="Beschriftung (optional, fuer alle)" />
                    <x-text-input id="label" name="label" placeholder="z. B. Eingang Juni 2026" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('documents.index') }}"><x-secondary-button type="button">Abbrechen</x-secondary-button></a>
                <x-primary-button x-bind:disabled="!files.length">Hochladen</x-primary-button>
            </div>
        </form>
        @if(session('uploadErrors'))
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                <strong>Fehler beim letzten Upload:</strong>
                <ul class="list-disc ps-4 mt-1">
                    @foreach(session('uploadErrors') as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif
    </x-card>
</x-app-layout>
