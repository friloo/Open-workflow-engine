<div class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-4 border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
    <button type="button" @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-slate-700 lg:hidden">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
    </button>

    <div class="flex flex-1 justify-end items-center gap-x-3">
        @auth
            <a href="{{ route('help.index') }}" title="Anleitung oeffnen"
                class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
            </a>

            <div class="relative" x-data="{ open: false, items: [], unread: 0, loaded: false,
                load() {
                    fetch('{{ route('notifications.dropdown') }}', { headers: { 'Accept':'application/json' } })
                        .then(r => r.json()).then(j => { this.items = j.items; this.unread = j.unread; this.loaded = true; });
                }
            }" x-init="load(); setInterval(() => load(), 60000)" @click.outside="open = false">
                <button @click="open = !open; if(!loaded) load()" class="relative grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition" title="Benachrichtigungen">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                    <span x-show="unread > 0" x-text="unread" class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"></span>
                </button>
                <div x-show="open" x-transition class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-slate-200 max-h-96 overflow-y-auto" style="display:none;">
                    <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                        <span class="text-xs font-semibold uppercase text-slate-500">Benachrichtigungen</span>
                        <a href="{{ route('notifications.index') }}" class="text-xs text-indigo-600 hover:text-indigo-500">Alle</a>
                    </div>
                    <template x-if="items.length === 0">
                        <div class="px-3 py-4 text-sm text-slate-500">Keine ungelesenen Benachrichtigungen.</div>
                    </template>
                    <template x-for="item in items" :key="item.id">
                        <a :href="item.url ? '{{ url('/notifications') }}/' + item.id + '/read' : '{{ route('notifications.index') }}'"
                           class="block border-b border-slate-50 px-3 py-2 hover:bg-slate-50">
                            <div class="flex items-start gap-2">
                                <span :class="item.unread ? 'bg-indigo-500' : 'bg-slate-300'" class="mt-1.5 inline-block h-2 w-2 rounded-full"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-slate-900 truncate" x-text="item.title"></div>
                                    <div class="text-xs text-slate-500 truncate" x-text="item.body || ''"></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5" x-text="item.created_at"></div>
                                </div>
                            </div>
                        </a>
                    </template>
                </div>
            </div>

            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open" class="flex items-center gap-x-2 rounded-full p-1 pr-3 hover:bg-slate-100 transition">
                    <div class="grid h-8 w-8 place-items-center rounded-full bg-indigo-100 text-indigo-700 text-sm font-semibold">
                        {{ Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => Str::substr($p, 0, 1))->take(2)->implode('') }}
                    </div>
                    <div class="hidden sm:block text-left">
                        <div class="text-sm font-medium text-slate-900 leading-4">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-slate-500">{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'Kein Rollenzugriff' }}</div>
                    </div>
                </button>
                <div x-show="open" x-transition class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-slate-200" style="display:none;">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Mein Profil</a>
                    <a href="{{ route('two-factor.show') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Zwei-Faktor-Anmeldung</a>
                    <a href="{{ route('tokens.index') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">API-Tokens</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Abmelden</button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</div>
