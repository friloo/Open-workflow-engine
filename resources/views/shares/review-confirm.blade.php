<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Freigabe bestätigen — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-gradient-to-br from-emerald-50 via-white to-slate-100">
<div class="min-h-full flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-8">
            <h1 class="text-lg font-semibold text-slate-900">Freigabe weiter behalten</h1>
            <p class="mt-1 text-sm text-slate-500">Bitte gib einen kurzen Grund an, warum die Freigabe weiter aktiv bleiben soll.</p>

            <dl class="mt-4 text-xs text-slate-600 space-y-1">
                <div><dt class="inline text-slate-500">Datei:</dt> <dd class="inline">{{ $share->attachment?->original_name ?? '—' }}</dd></div>
                @if($share->note)<div><dt class="inline text-slate-500">Notiz:</dt> <dd class="inline">{{ $share->note }}</dd></div>@endif
                @if($share->expires_at)<div><dt class="inline text-slate-500">Laeuft ab:</dt> <dd class="inline">{{ $share->expires_at->format('d.m.Y H:i') }}</dd></div>@endif
            </dl>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ url()->signedRoute('shares.review.confirm.submit', ['share' => $share->id]) }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="signature" value="{{ request('signature') }}">
                <input type="hidden" name="expires" value="{{ request('expires') }}">
                <div>
                    <label for="reason" class="block text-sm font-medium text-slate-700 mb-1">Grund</label>
                    <textarea id="reason" name="reason" rows="4" required placeholder="z. B. laufende Prüfung mit Anwalt bis Ende Juni"
                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                </div>
                <button type="submit" class="w-full inline-flex justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">Freigabe behalten</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
