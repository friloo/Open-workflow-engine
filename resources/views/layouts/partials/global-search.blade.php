{{-- Cmd+K / Strg+K Schnellsuche-Modal. State (globalSearchOpen) lebt im root x-data des app-layouts. --}}
<div x-show="globalSearchOpen"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-start justify-center pt-[10vh] px-4"
     style="display:none;"
     @keydown.escape.window="globalSearchOpen = false">
    <div class="absolute inset-0 bg-slate-900/40" @click="globalSearchOpen = false"></div>

    <div class="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden"
         x-data="{
            q: '',
            groups: [],
            loading: false,
            flatIndex: 0,
            timer: null,
            flatItems() {
                return this.groups.flatMap(g => g.items);
            },
            search() {
                clearTimeout(this.timer);
                if (this.q.trim().length < 2) { this.groups = []; this.flatIndex = 0; return; }
                this.loading = true;
                this.timer = setTimeout(() => {
                    fetch('{{ route('search.global') }}?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } })
                        .then(r => r.json())
                        .then(j => { this.groups = j.groups || []; this.flatIndex = 0; })
                        .finally(() => { this.loading = false; });
                }, 150);
            },
            move(delta) {
                const items = this.flatItems();
                if (items.length === 0) return;
                this.flatIndex = (this.flatIndex + delta + items.length) % items.length;
            },
            activate() {
                const items = this.flatItems();
                if (items[this.flatIndex]) {
                    window.location.href = items[this.flatIndex].url;
                }
            },
         }"
         x-init="$watch('globalSearchOpen', v => { if (v) { $nextTick(() => $refs.input.focus()); q = ''; groups = []; flatIndex = 0; } })">
        <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
            <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            <input type="text" x-ref="input" x-model="q" @input="search()"
                   @keydown.arrow-down.prevent="move(1)"
                   @keydown.arrow-up.prevent="move(-1)"
                   @keydown.enter.prevent="activate()"
                   placeholder="Suchen in Dokumenten, Workflows, Aufgaben, Benutzern …"
                   class="flex-1 border-0 p-0 text-base text-slate-900 focus:ring-0 placeholder:text-slate-400">
            <kbd class="text-[10px] rounded border border-slate-200 px-1.5 py-0.5 font-mono text-slate-500">ESC</kbd>
        </div>

        <div class="max-h-[60vh] overflow-y-auto">
            <template x-if="loading">
                <div class="px-4 py-6 text-sm text-slate-500">Suche &hellip;</div>
            </template>
            <template x-if="!loading && q.trim().length < 2">
                <div class="px-4 py-6 text-sm text-slate-500">Tippe mindestens 2 Zeichen.</div>
            </template>
            <template x-if="!loading && q.trim().length >= 2 && groups.length === 0">
                <div class="px-4 py-6 text-sm text-slate-500">Keine Treffer für „<span x-text="q"></span>".</div>
            </template>

            <template x-for="(group, gi) in groups" :key="gi">
                <div>
                    <div class="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 bg-slate-50">
                        <span x-text="group.label"></span>
                    </div>
                    <template x-for="(item, ii) in group.items" :key="gi + '-' + ii">
                        <a :href="item.url"
                           :class="(groups.slice(0, gi).reduce((s,g)=>s+g.items.length,0) + ii) === flatIndex ? 'bg-indigo-50' : 'hover:bg-slate-50'"
                           class="block px-4 py-2.5 border-b border-slate-50">
                            <div class="text-sm font-medium text-slate-900 truncate" x-text="item.title"></div>
                            <div class="text-xs text-slate-500 truncate" x-text="item.subtitle"></div>
                        </a>
                    </template>
                </div>
            </template>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-2 flex items-center justify-between text-[11px] text-slate-500">
            <div class="flex items-center gap-3">
                <span><kbd class="rounded border border-slate-300 bg-white px-1 font-mono">&uarr; &darr;</kbd> navigieren</span>
                <span><kbd class="rounded border border-slate-300 bg-white px-1 font-mono">Enter</kbd> öffnen</span>
            </div>
            <span>Strg+K / &#8984;K</span>
        </div>
    </div>
</div>
