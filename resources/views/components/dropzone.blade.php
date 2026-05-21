@props([
    'uploadUrl',           // POST-Ziel (z. B. route('attachments.store', ['type'=>..., 'id'=>...]))
    'label' => 'Datei zum Hochladen hier ablegen',
])

{{-- Wickelt einen beliebigen Container in eine Drop-Zone. Bei Drag-Over
     erscheint ein gestrichelter Overlay; bei Drop wird die Datei via fetch
     POSTet und die Seite neu geladen. Duplikate / Fehler kommen als Alert.

     Nutzung:
       <x-dropzone uploadUrl="{{ route('attachments.store', ['type'=>'instance','id'=>$instance->id]) }}">
           ... bestehender Inhalt ...
       </x-dropzone>
--}}
<div x-data="dropzone(@js($uploadUrl))"
     @dragover.prevent="onDragOver($event)"
     @dragleave="onDragLeave($event)"
     @drop.prevent="onDrop($event)"
     class="relative">
    {{ $slot }}

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

    <div x-show="busy" x-cloak class="pointer-events-none absolute inset-0 z-30 grid place-items-center rounded-xl bg-white/80">
        <div class="flex items-center gap-2 text-sm text-slate-700">
            <svg class="h-4 w-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="progress"></span>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.dropzone = function (uploadUrl) {
                return {
                    active: false,
                    busy: false,
                    progress: 'Lade hoch ...',
                    _dragDepth: 0,
                    onDragOver(e) {
                        if (!e.dataTransfer || ![...(e.dataTransfer.types || [])].includes('Files')) return;
                        this.active = true;
                    },
                    onDragLeave() {
                        // dragleave feuert beim Wechsel zwischen Kindern — kurze Verzögerung damit's nicht flackert
                        setTimeout(() => { if (!this._dragDepth) this.active = false; }, 50);
                    },
                    async onDrop(e) {
                        this.active = false;
                        const files = [...(e.dataTransfer?.files || [])];
                        if (files.length === 0) return;
                        this.busy = true;
                        let ok = 0; let fails = [];
                        for (const file of files) {
                            this.progress = `${ok + 1} / ${files.length}: ${file.name}`;
                            try {
                                await this.upload(file);
                                ok++;
                            } catch (err) {
                                fails.push(`${file.name}: ${err.message || err}`);
                            }
                        }
                        this.busy = false;
                        if (fails.length) {
                            alert(`Hochgeladen: ${ok}\n\nFehlgeschlagen:\n` + fails.join('\n'));
                        }
                        // Nach erfolgreichen Uploads die Seite neu laden, damit die
                        // neue Datei in der bestehenden Liste auftaucht.
                        if (ok > 0) window.location.reload();
                    },
                    async upload(file) {
                        const fd = new FormData();
                        fd.append('file', file);
                        const csrf = document.querySelector('meta[name=csrf-token]')?.content;
                        const r = await fetch(uploadUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json, text/html' },
                            body: fd,
                            redirect: 'follow',
                        });
                        if (!r.ok && r.status !== 302) {
                            // Validierungs- oder Duplikat-Fehler — wir lesen den Body für Detail.
                            let msg = `HTTP ${r.status}`;
                            try {
                                const j = await r.clone().json();
                                msg = j.message || j.errors?.file?.[0] || msg;
                            } catch (_) {}
                            throw new Error(msg);
                        }
                    },
                };
            };
        </script>
    @endpush
@endonce
