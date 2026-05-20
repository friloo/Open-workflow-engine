<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name', 'Open Workflow Engine') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-slate-50 text-slate-800">
<div class="min-h-full"
     x-data="{
         sidebarOpen: false,
         globalSearchOpen: false,
         shortcutsOpen: false,
         supportOpen: false,
         goSeq: false,
         goSeqTimer: null,
         navigateTo(url) { window.location.href = url; this.goSeq = false; }
     }"
     @keydown.window.ctrl.k.prevent="globalSearchOpen = true"
     @keydown.window.meta.k.prevent="globalSearchOpen = true"
     @keydown.window="
        if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)) return;
        if ($event.key === '?') { shortcutsOpen = true; return; }
        if (goSeq) {
            // Zweiter Tastendruck einer g-Sequenz
            clearTimeout(goSeqTimer);
            goSeq = false;
            const map = {
                't': '{{ route('tasks.index') }}',
                'd': '{{ auth()->user()?->hasPermission('documents.search') ? route('documents.index') : '' }}',
                'w': '{{ auth()->user()?->hasPermission('workflows.view') || auth()->user()?->hasPermission('workflows.design') ? route('workflows.index') : '' }}',
                'i': '{{ route('inbox') }}',
                'h': '{{ route('help.index') }}',
            };
            if (map[$event.key]) navigateTo(map[$event.key]);
            return;
        }
        if ($event.key === 'g') {
            goSeq = true;
            clearTimeout(goSeqTimer);
            goSeqTimer = setTimeout(() => goSeq = false, 1500);
        }
     ">
    @auth
        @include('layouts.partials.global-search')
        @include('layouts.partials.shortcuts-help')
        @if((bool) \App\Support\Settings::get('support.enabled', false))
            @include('layouts.partials.support-modal')
        @endif
    @endauth
    @include('layouts.sidebar')

    <div class="lg:pl-64">
        @include('layouts.topbar')

        <main class="{{ ($full ?? false) ? '' : 'py-8' }}">
            <div class="{{ ($full ?? false) ? '' : 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8' }}">

                @isset($header)
                    <header class="mb-6">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $header }}</h1>
                        @isset($subheader)
                            <p class="mt-1 text-sm text-slate-500">{{ $subheader }}</p>
                        @endisset
                    </header>
                @endisset

                {{-- space-y-6 sorgt fuer Abstand zwischen direkten Top-Level-Kindern wie aufeinanderfolgenden <x-card>-Bloecken --}}
                <div class="space-y-6">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>
    <x-toast />
</div>
@stack('scripts')
</body>
</html>
