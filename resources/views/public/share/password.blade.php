<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Passwort eingeben — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-gradient-to-br from-indigo-50 via-white to-slate-100">
<div class="min-h-full flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-8">
            <h1 class="text-lg font-semibold text-slate-900">Passwortgeschuetzte Freigabe</h1>
            <p class="mt-1 text-sm text-slate-500">Bitte gib das Passwort ein, das du erhalten hast.</p>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('share.unlock', $share->token) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="pw" class="block text-sm font-medium text-slate-700 mb-1">Passwort</label>
                    <input id="pw" name="password" type="password" required autofocus
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <button type="submit" class="w-full inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Entsperren</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
