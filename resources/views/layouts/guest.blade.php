<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('branding.app_name', config('app.name', 'Open Workflow Engine')) }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $bgImage   = trim((string) config('branding.login_bg_image', ''));
        $bgFrom    = config('branding.login_bg_from', '#eef2ff');
        $bgTo      = config('branding.login_bg_to',   '#f1f5f9');
        $primary   = config('branding.primary_color', '#6366f1');
        $logoText  = config('branding.logo_text', 'W');
        $appName   = config('branding.app_name', config('app.name', 'Open Workflow Engine'));
        $subtitle  = config('branding.login_subtitle');
        $bodyStyle = $bgImage !== ''
            ? "background-image: url('".e($bgImage)."'); background-size: cover; background-position: center;"
            : "background-image: linear-gradient(135deg, {$bgFrom}, {$bgTo});";
    @endphp
</head>
<body class="h-full font-sans antialiased" style="{{ $bodyStyle }}">
<div class="min-h-full flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="flex items-center gap-2 justify-center mb-6">
            <div class="grid h-10 w-10 place-items-center rounded-lg text-white font-bold"
                 style="background-color: {{ $primary }};">{{ $logoText }}</div>
            <span class="text-lg font-semibold text-slate-900">{{ $appName }}</span>
        </div>
        @if(! empty($subtitle))
            <p class="text-center text-sm text-slate-600 mb-4">{{ $subtitle }}</p>
        @endif
        <div class="rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-8">
            {{ $slot }}
        </div>
        <div class="mt-6 text-center space-y-1">
            <p class="text-xs text-slate-500">
                © {{ date('Y') }} {{ $appName }} ·
                entwickelt von <a href="https://loheide.eu" target="_blank" rel="noopener" class="font-medium hover:underline" style="color: {{ $primary }};">Friederich Loheide</a>
            </p>
            <p class="text-[10px] text-slate-400 max-w-sm mx-auto">
                Teilweise mit KI generiert. Nutzung auf eigene Gefahr — keine Gewaehr fuer Fehlerfreiheit,
                Datensicherheit oder regulatorische Konformitaet.
            </p>
        </div>
    </div>
</div>
</body>
</html>
