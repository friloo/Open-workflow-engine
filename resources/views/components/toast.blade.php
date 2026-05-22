@php
    $sessionStatus = session('status');
    $firstError = $errors && $errors->any() ? $errors->first() : null;
@endphp

<div x-data="toastQueue()"
     x-init="@if($sessionStatus) push('success', @js($sessionStatus)); @endif @if($firstError) push('error', @js($firstError)); @endif"
     @toast.window="push($event.detail?.type || 'info', $event.detail?.message || $event.detail || '', $event.detail?.title || null)"
     class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 items-end max-w-md pointer-events-none">
    <template x-for="t in toasts" :key="t.id">
        <div x-show="t.open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-2 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg min-w-[18rem]"
             :class="{
                 'border-emerald-200 bg-emerald-50 text-emerald-800': t.type === 'success',
                 'border-rose-200 bg-rose-50 text-rose-800': t.type === 'error',
                 'border-amber-200 bg-amber-50 text-amber-800': t.type === 'warning',
                 'border-slate-200 bg-white text-slate-800': t.type === 'info',
             }">
            <svg class="h-5 w-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                 :class="{
                     'text-emerald-600': t.type === 'success',
                     'text-rose-600': t.type === 'error',
                     'text-amber-600': t.type === 'warning',
                     'text-indigo-600': t.type === 'info',
                 }">
                <template x-if="t.type === 'success'"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></template>
                <template x-if="t.type === 'error'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></template>
                <template x-if="t.type === 'warning'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></template>
                <template x-if="t.type === 'info'"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></template>
            </svg>
            <div class="flex-1 min-w-0">
                <div x-show="t.title" class="font-semibold" x-text="t.title"></div>
                <div x-text="t.message" class="break-words"></div>
            </div>
            <button type="button" @click="dismiss(t.id)" class="opacity-60 hover:opacity-100">✕</button>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            window.toastQueue = function () {
                return {
                    toasts: [],
                    _next: 1,
                    push(type, message, title = null, ttl = 6000) {
                        const id = this._next++;
                        this.toasts.push({ id, type, message, title, open: true });
                        if (ttl > 0) setTimeout(() => this.dismiss(id), ttl);
                    },
                    dismiss(id) {
                        const t = this.toasts.find(x => x.id === id);
                        if (! t) return;
                        t.open = false;
                        setTimeout(() => { this.toasts = this.toasts.filter(x => x.id !== id); }, 200);
                    },
                };
            };

            // Globaler programmatischer Trigger fuer beliebige Stellen im JS.
            // Nutzung:
            //   window.toast('Datei gespeichert');                       // type=success
            //   window.toast('Bitte pruefen', 'warning');
            //   window.toast({ type: 'error', title: 'Fehler', message: 'XYZ' });
            window.toast = function (a, b = 'success', title = null) {
                let detail;
                if (typeof a === 'object' && a !== null) {
                    detail = { type: a.type || 'info', message: a.message || '', title: a.title || null };
                } else {
                    detail = { type: b, message: String(a), title };
                }
                window.dispatchEvent(new CustomEvent('toast', { detail }));
            };
        </script>
    @endpush
@endonce
