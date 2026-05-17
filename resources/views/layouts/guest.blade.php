<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Open Workflow Engine') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-gradient-to-br from-indigo-50 via-white to-slate-100">
<div class="min-h-full flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="flex items-center gap-2 justify-center mb-6">
            <div class="grid h-10 w-10 place-items-center rounded-lg bg-indigo-600 text-white font-bold">W</div>
            <span class="text-lg font-semibold text-slate-900">Open Workflow Engine</span>
        </div>
        <div class="rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-8">
            {{ $slot }}
        </div>
        <p class="mt-6 text-center text-xs text-slate-400">© {{ date('Y') }} Open Workflow Engine</p>
    </div>
</div>
</body>
</html>
