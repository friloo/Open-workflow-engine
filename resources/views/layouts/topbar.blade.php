<div class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-4 border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
    <button type="button" @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-slate-700 lg:hidden">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
    </button>

    <div class="flex flex-1 justify-end items-center gap-x-4">
        @auth
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
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Abmelden</button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</div>
