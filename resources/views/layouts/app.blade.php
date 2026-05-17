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
<div class="min-h-full" x-data="{ sidebarOpen: false }">
    @include('layouts.sidebar')

    <div class="lg:pl-64">
        @include('layouts.topbar')

        <main class="{{ ($full ?? false) ? '' : 'py-8' }}">
            <div class="{{ ($full ?? false) ? '' : 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8' }}">
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any() && ! request()->routeIs('login'))
                    <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <ul class="list-disc ps-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @isset($header)
                    <header class="mb-6">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $header }}</h1>
                        @isset($subheader)
                            <p class="mt-1 text-sm text-slate-500">{{ $subheader }}</p>
                        @endisset
                    </header>
                @endisset

                {{ $slot }}
            </div>
        </main>
    </div>
</div>
</body>
</html>
