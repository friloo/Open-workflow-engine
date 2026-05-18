<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $workflow->name }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-gradient-to-br from-indigo-50 via-white to-slate-100">
<div class="min-h-full flex items-start justify-center px-4 py-12">
    <div class="w-full max-w-2xl">
        <div class="flex items-center gap-2 justify-center mb-6">
            <div class="grid h-10 w-10 place-items-center rounded-lg bg-indigo-600 text-white font-bold">W</div>
            <span class="text-lg font-semibold text-slate-900">{{ config('app.name') }}</span>
        </div>

        <div class="rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-8">
            <h1 class="text-xl font-semibold text-slate-900">{{ $workflow->name }}</h1>
            @if($workflow->description)
                <p class="mt-1 text-sm text-slate-500">{{ $workflow->description }}</p>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-disc ps-5 space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('public.form.submit', $workflow->public_slug) }}" class="mt-6 space-y-4">
                @csrf
                @include('workflows._form_fields', ['schema' => $schema])
                <div class="pt-2">
                    <button type="submit" class="w-full inline-flex justify-center items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Absenden
                    </button>
                </div>
            </form>
        </div>
        <p class="mt-6 text-center text-xs text-slate-400">Betrieben mit Open Workflow Engine</p>
    </div>
</div>
</body>
</html>
