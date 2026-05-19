{{-- Tastatur-Shortcuts-Modal. Trigger: '?' im Layout-Root. State (shortcutsOpen)
     lebt im app-layout x-data. --}}
<div x-show="shortcutsOpen"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none;"
     @keydown.escape.window="shortcutsOpen = false">
    <div class="absolute inset-0 bg-slate-900/40" @click="shortcutsOpen = false"></div>

    <div class="relative w-full max-w-md rounded-xl bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Tastatur-Shortcuts</h2>
            <button @click="shortcutsOpen = false" class="text-slate-400 hover:text-slate-600" aria-label="Schliessen">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-4 py-4 space-y-4 text-sm">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Global</div>
                <ul class="space-y-2">
                    <li class="flex items-center justify-between">
                        <span class="text-slate-700">Schnellsuche oeffnen</span>
                        <span class="flex items-center gap-1">
                            <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 text-[11px] font-mono text-slate-600">Strg</kbd>
                            <span class="text-slate-400">+</span>
                            <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 text-[11px] font-mono text-slate-600">K</kbd>
                            <span class="text-xs text-slate-400">/ &#8984;K</span>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-700">Diese Hilfe</span>
                        <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 text-[11px] font-mono text-slate-600">?</kbd>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-700">Modal schliessen</span>
                        <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 text-[11px] font-mono text-slate-600">Esc</kbd>
                    </li>
                </ul>
            </div>

            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">In der Schnellsuche</div>
                <ul class="space-y-2">
                    <li class="flex items-center justify-between">
                        <span class="text-slate-700">Treffer durchblaettern</span>
                        <span class="flex items-center gap-1">
                            <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 text-[11px] font-mono text-slate-600">&uarr;</kbd>
                            <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 text-[11px] font-mono text-slate-600">&darr;</kbd>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-slate-700">Treffer oeffnen</span>
                        <kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 text-[11px] font-mono text-slate-600">Enter</kbd>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-2 text-[11px] text-slate-500 text-center">
            Tipp: <kbd class="rounded border border-slate-300 bg-white px-1 font-mono">?</kbd> in einem Eingabefeld funktioniert nicht — erst Klick neben das Feld.
        </div>
    </div>
</div>
