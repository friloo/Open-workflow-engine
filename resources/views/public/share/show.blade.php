<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $attachment->original_name }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-slate-100">
<div class="min-h-full flex flex-col">
    <header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center gap-3">
        <div class="grid h-9 w-9 place-items-center rounded-lg text-white font-bold" style="background:{{ config('branding.primary_color', '#6366f1') }};">{{ config('branding.logo_text', 'W') }}</div>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-slate-900 truncate">{{ $attachment->original_name }}</div>
            <div class="text-xs text-slate-500">
                {{ $attachment->sizeFormatted() }} · {{ $attachment->mime_type }}
                @if($share->note) · {{ $share->note }} @endif
                @if($share->expires_at) · Gueltig bis {{ $share->expires_at->format('d.m.Y H:i') }} @endif
            </div>
        </div>
        <a href="{{ route('share.download', $share->token) }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Herunterladen</a>
    </header>

    <main class="flex-1 p-6">
        @if($attachment->isPdf())
            <iframe src="{{ route('share.preview', $share->token) }}#toolbar=1" class="w-full h-[80vh] rounded-lg border border-slate-200 bg-white shadow-sm" title="Vorschau"></iframe>
        @elseif($attachment->isImage())
            <div class="text-center"><img src="{{ route('share.preview', $share->token) }}" class="max-h-[80vh] mx-auto rounded-lg border border-slate-200 shadow-sm" alt="{{ $attachment->original_name }}"></div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white p-12 text-center">
                <p class="text-sm text-slate-500">Vorschau fuer diesen Dateityp nicht verfuegbar.</p>
                <a href="{{ route('share.download', $share->token) }}" class="mt-3 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Datei herunterladen</a>
            </div>
        @endif
    </main>

    <footer class="border-t border-slate-200 bg-white px-6 py-3 text-xs text-slate-500 text-center">
        Bereitgestellt von {{ config('app.name') }}.
        @if($share->max_downloads)
            · {{ $share->download_count }} von max. {{ $share->max_downloads }} Zugriffen
        @endif
    </footer>
</div>
</body>
</html>
