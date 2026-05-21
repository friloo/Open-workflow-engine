<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vielen Dank — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-gradient-to-br from-indigo-50 via-white to-slate-100">
<div class="min-h-full flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md text-center">
        <div class="grid h-16 w-16 mx-auto place-items-center rounded-full bg-emerald-100 text-emerald-700 text-3xl">✓</div>
        <h1 class="mt-6 text-2xl font-semibold text-slate-900">Vielen Dank!</h1>
        <p class="mt-2 text-sm text-slate-600">
            Dein Antrag „{{ $workflow->name }}" wurde übermittelt. Der Workflow läuft jetzt automatisch weiter — sobald du auf eine Rückmeldung warten musst, bekommst du eine E-Mail.
        </p>
        <a href="{{ route('public.form.show', $workflow->public_slug) }}" class="mt-6 inline-flex text-sm text-indigo-600 hover:text-indigo-500">Neuen Antrag stellen</a>
    </div>
</div>
</body>
</html>
