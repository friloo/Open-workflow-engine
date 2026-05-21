<div class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-4 border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
    <button type="button" @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-slate-700 lg:hidden">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
    </button>

    <div class="flex flex-1 justify-end items-center gap-x-3">
        @auth
            <button type="button" @click="globalSearchOpen = true" title="Schnellsuche (Strg+K)"
                class="hidden sm:flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <span class="hidden md:inline">Schnellsuche &hellip;</span>
                <kbd class="hidden md:inline-flex items-center rounded border border-slate-300 bg-white px-1.5 text-[10px] font-mono text-slate-500">Strg K</kbd>
            </button>
            <button type="button" @click="globalSearchOpen = true" title="Schnellsuche"
                class="sm:hidden grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            </button>

            {{-- Theme-Toggle: auto/light/dark, Persistenz im localStorage --}}
            <div x-data="{
                    theme: (localStorage.getItem('owe-theme') || 'auto'),
                    apply() {
                        const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                        const dark = this.theme === 'dark' || (this.theme === 'auto' && sysDark);
                        document.documentElement.classList.toggle('dark', dark);
                    },
                    cycle() {
                        this.theme = this.theme === 'auto' ? 'light' : this.theme === 'light' ? 'dark' : 'auto';
                        localStorage.setItem('owe-theme', this.theme);
                        this.apply();
                    }
                }" x-init="apply(); window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => apply())">
                <button type="button" @click="cycle()" :title="'Theme: ' + theme + ' (klicken zum Wechseln)'"
                        class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition">
                    <svg x-show="theme === 'auto'" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
                    <svg x-show="theme === 'light'" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><circle cx="12" cy="12" r="4"/></svg>
                    <svg x-show="theme === 'dark'" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
                </button>
            </div>

            <a href="{{ route('help.index') }}" title="Anleitung öffnen"
                class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
            </a>

            @if((bool) \App\Support\Settings::get('support.enabled', false))
                @php($supportLabel = (string) (\App\Support\Settings::get('support.sidebar_label', '') ?: 'IT-Support'))
                <button type="button" @click="supportOpen = true" title="{{ $supportLabel }}"
                    class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.712 4.33a9.027 9.027 0 0 1 1.652 1.306c.51.51.944 1.064 1.306 1.652M16.712 4.33l-3.448 4.138m3.448-4.138a9.014 9.014 0 0 0-9.424 0M19.67 7.288l-4.138 3.448m4.138-3.448a9.014 9.014 0 0 1 0 9.424m-4.138-5.976a3.736 3.736 0 0 0-.88-1.388 3.737 3.737 0 0 0-1.388-.88m2.268 2.268a3.765 3.765 0 0 1 0 2.528m-2.268-4.796a3.765 3.765 0 0 0-2.528 0m4.796 4.796c-.181.506-.475.982-.88 1.388a3.736 3.736 0 0 1-1.388.88m2.268-2.268 4.138 3.448m0 0a9.027 9.027 0 0 1-1.306 1.652c-.51.51-1.064.944-1.652 1.306m0 0-3.448-4.138m3.448 4.138a9.014 9.014 0 0 1-9.424 0m5.976-4.138a3.765 3.765 0 0 1-2.528 0m0 0a3.736 3.736 0 0 1-1.388-.88 3.737 3.737 0 0 1-.88-1.388m2.268 2.268L7.288 19.67m0 0a9.024 9.024 0 0 1-1.652-1.306 9.027 9.027 0 0 1-1.306-1.653m0 0 4.138-3.448M4.33 16.712a9.014 9.014 0 0 1 0-9.424m4.138 5.976a3.765 3.765 0 0 1 0-2.528m0 0c.181-.506.475-.982.88-1.388a3.736 3.736 0 0 1 1.388-.88m-2.268 2.268L4.33 7.288m6.406 1.18L7.288 4.33m0 0a9.024 9.024 0 0 0-1.652 1.306A9.025 9.025 0 0 0 4.33 7.288"/></svg>
                </button>
            @endif

            {{-- Inbox-Badge: Anzahl offener (nicht-snoozed) Aufgaben für aktuellen User --}}
            @php($openTaskCount = \App\Support\InboxCounter::openCount())
            <a href="{{ route('tasks.index') }}" title="Mein Eingang"
               class="relative grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                </svg>
                @if($openTaskCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-indigo-500 px-1 text-[10px] font-semibold text-white">{{ $openTaskCount > 99 ? '99+' : $openTaskCount }}</span>
                @endif
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
