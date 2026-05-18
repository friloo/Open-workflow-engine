<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erledigt — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-gradient-to-br from-emerald-50 via-white to-slate-100">
<div class="min-h-full flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md text-center">
        @if($mode === 'confirmed')
            <div class="grid h-16 w-16 mx-auto place-items-center rounded-full bg-emerald-100 text-emerald-700 text-3xl">✓</div>
            <h1 class="mt-6 text-xl font-semibold text-slate-900">Vielen Dank — Freigabe bleibt aktiv</h1>
            <p class="mt-2 text-sm text-slate-600">Wir melden uns in einigen Tagen erneut zur Pruefung.</p>
        @else
            <div class="grid h-16 w-16 mx-auto place-items-center rounded-full bg-rose-100 text-rose-700 text-3xl">✕</div>
            <h1 class="mt-6 text-xl font-semibold text-slate-900">Freigabe widerrufen</h1>
            <p class="mt-2 text-sm text-slate-600">Der Link funktioniert ab sofort nicht mehr.</p>
        @endif
    </div>
</div>
</body>
</html>
