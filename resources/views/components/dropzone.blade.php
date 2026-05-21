@props([
    'uploadUrl',           // POST-Ziel (z. B. route('attachments.store', ['type'=>..., 'id'=>...]))
    'label' => 'Datei zum Hochladen hier ablegen',
    'browse' => true,      // Browser-Picker zusätzlich zum Drag-and-Drop anbieten
    'documentType' => null,// Pre-Fill für document_type
])

{{-- Wickelt einen beliebigen Container in eine Drop-Zone. Bei Drag-Over
     erscheint ein gestrichelter Overlay; bei Drop wird die Datei via XHR
     POSTet. Multifile mit per-Datei-Progress.

     Nutzung:
       <x-dropzone uploadUrl="{{ route('attachments.store', ['type'=>'instance','id'=>$instance->id]) }}">
           ... bestehender Inhalt ...
       </x-dropzone>
--}}
<div x-data="dropzone(@js($uploadUrl), {{ $browse ? 'true' : 'false' }}, @js($documentType))"
     @dragover.prevent="onDragOver($event)"
     @dragleave="onDragLeave($event)"
     @drop.prevent="onDrop($event)"
     class="relative">

    @if($browse)
        {{-- Optional: Click-To-Pick-Button oben, ohne den Slot zu blockieren --}}
        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <button type="button" @click="$refs.fileInput.click()"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Dateien wählen
            </button>
            <span>oder hierher ziehen — beliebig viele Dateien gleichzeitig</span>
            <input type="file" multiple x-ref="fileInput" class="sr-only"
                   @change="onPickFiles($event)">
        </div>
    @endif

    {{ $slot }}

    {{-- Drag-Overlay --}}
    <div x-show="active"
         x-transition.opacity
         class="pointer-events-none absolute inset-0 z-30 grid place-items-center rounded-xl border-2 border-dashed border-indigo-500 bg-indigo-50/85"
         style="display:none;">
        <div class="text-center px-6">
            <svg class="mx-auto h-10 w-10 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/></svg>
            <p class="mt-2 text-sm font-semibold text-indigo-900">{{ $label }}</p>
            <p class="mt-1 text-xs text-indigo-700">Loslassen um hochzuladen.</p>
        </div>
    </div>

    {{-- Pro-Datei-Status-Liste — bleibt sichtbar bis Reload --}}
    <template x-if="queue.length > 0">
        <div class="mt-3 rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between px-3 py-2 border-b border-slate-100 text-xs text-slate-500">
                <span><strong x-text="queue.length"></strong> Datei(en) · <strong class="text-emerald-700" x-text="doneCount()"></strong> fertig · <strong class="text-rose-700" x-text="errorCount()"></strong> Fehler</span>
                <button type="button" @click="queue = []" x-show="! anyUploading()" class="text-xs text-slate-500 hover:text-slate-900">aufräumen</button>
            </div>
            <ul class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                <template x-for="item in queue" :key="item.id">
                    <li class="px-3 py-2 flex items-center gap-3 text-sm">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-900 truncate" x-text="item.name"></span>
                                <span class="text-xs text-slate-500" x-text="formatBytes(item.size)"></span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full transition-all"
                                     :class="{
                                         'bg-indigo-500': item.status === 'uploading',
                                         'bg-emerald-500': item.status === 'done',
                                         'bg-rose-500': item.status === 'error',
                                         'bg-slate-300': item.status === 'queued',
                                     }"
                                     :style="`width: ${item.progress}%`"></div>
                            </div>
                            <div class="mt-0.5 text-xs"
                                 :class="{
                                     'text-slate-500': item.status === 'uploading' || item.status === 'queued',
                                     'text-emerald-700': item.status === 'done',
                                     'text-rose-700': item.status === 'error',
                                 }"
                                 x-text="item.message"></div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            window.dropzone = function (uploadUrl, browseMode, docType) {
                return {
                    uploadUrl,
                    browseMode,
                    docType,
                    active: false,
                    queue: [],
                    _nextId: 1,
                    _dragDepth: 0,

                    onDragOver(e) {
                        if (!e.dataTransfer || ![...(e.dataTransfer.types || [])].includes('Files')) return;
                        this.active = true;
                    },
                    onDragLeave() {
                        setTimeout(() => { if (!this._dragDepth) this.active = false; }, 50);
                    },
                    onDrop(e) {
                        this.active = false;
                        const files = [...(e.dataTransfer?.files || [])];
                        this.enqueue(files);
                    },
                    onPickFiles(e) {
                        const files = [...(e.target.files || [])];
                        e.target.value = '';
                        this.enqueue(files);
                    },

                    enqueue(files) {
                        if (!files.length) return;
                        const wasIdle = !this.anyUploading();
                        files.forEach(f => {
                            this.queue.push({
                                id: this._nextId++,
                                name: f.name,
                                size: f.size,
                                file: f,
                                status: 'queued',
                                progress: 0,
                                message: 'wartend',
                            });
                        });
                        if (wasIdle) this.processQueue();
                    },

                    async processQueue() {
                        const next = this.queue.find(i => i.status === 'queued');
                        if (!next) {
                            // Alles fertig — wenn mind. eine Datei OK war, Seite neu laden,
                            // damit die neuen Anhänge in der bestehenden Liste auftauchen.
                            if (this.queue.some(i => i.status === 'done')) {
                                setTimeout(() => window.location.reload(), 800);
                            }
                            return;
                        }
                        next.status = 'uploading';
                        next.message = 'hochladen…';
                        await this.upload(next);
                        this.processQueue();
                    },

                    upload(item) {
                        return new Promise((resolve) => {
                            const fd = new FormData();
                            fd.append('file', item.file);
                            if (this.docType) fd.append('document_type', this.docType);
                            const csrf = document.querySelector('meta[name=csrf-token]')?.content;

                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', this.uploadUrl);
                            xhr.setRequestHeader('Accept', 'application/json');
                            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                            xhr.upload.onprogress = e => {
                                if (e.lengthComputable) {
                                    item.progress = Math.round((e.loaded / e.total) * 100);
                                }
                            };
                            xhr.onload = () => {
                                let data = {};
                                try { data = JSON.parse(xhr.responseText || '{}'); } catch (_) {}
                                if (xhr.status >= 200 && xhr.status < 300 && data.ok) {
                                    item.status = 'done';
                                    item.progress = 100;
                                    item.message = 'hochgeladen';
                                } else {
                                    item.status = 'error';
                                    item.message = data.error || `Fehler (HTTP ${xhr.status})`;
                                }
                                resolve();
                            };
                            xhr.onerror = () => {
                                item.status = 'error';
                                item.message = 'Netzwerkfehler';
                                resolve();
                            };
                            xhr.send(fd);
                        });
                    },

                    anyUploading() {
                        return this.queue.some(i => i.status === 'uploading' || i.status === 'queued');
                    },
                    doneCount() { return this.queue.filter(i => i.status === 'done').length; },
                    errorCount() { return this.queue.filter(i => i.status === 'error').length; },

                    formatBytes(b) {
                        if (b < 1024) return b + ' B';
                        if (b < 1024 * 1024) return (b / 1024).toFixed(0) + ' KB';
                        return (b / (1024 * 1024)).toFixed(1) + ' MB';
                    },
                };
            };
        </script>
    @endpush
@endonce
