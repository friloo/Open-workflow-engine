<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OWE Installer — System-Prüfung</title>
    @include('install._styles')
</head>
<body>
<div class="wrap">
    @include('install._header', ['step' => 1])
    <h2>System-Prüfung</h2>
    <p class="sub">Diese Voraussetzungen müssen erfüllt sein, damit OWE läuft.</p>
    <table>
        @foreach($checks as $c)
            <tr>
                <td>{{ $c['name'] }}</td>
                <td><span class="badge {{ $c['status'] }}">{{ strtoupper($c['status']) }}</span></td>
                <td style="color:#475569; font-size:13px;">{{ $c['message'] }}</td>
            </tr>
        @endforeach
    </table>

    @if(! $canProceed)
        <div class="alert-error" style="margin-top:16px;">
            Bitte die mit <strong>FAIL</strong> markierten Punkte beheben und Seite neu laden.
        </div>
    @else
        <h2 style="margin-top: 28px;">Wie weiter?</h2>
        <p class="sub">Frische Installation oder Migration aus einem Backup?</p>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:12px;">
            <a href="{{ route('install.database') }}" style="display:block; padding:16px; border:1px solid #c7d2fe; border-radius:10px; background:#eef2ff; text-decoration:none; color:#1e1b4b;">
                <strong>Frische Installation</strong>
                <div style="font-size:13px; color:#475569; margin-top:4px;">DB neu anlegen, erstes Admin-Konto erstellen.</div>
            </a>
            <a href="{{ route('install.restore') }}" style="display:block; padding:16px; border:1px solid #fed7aa; border-radius:10px; background:#fff7ed; text-decoration:none; color:#7c2d12;">
                <strong>Aus Backup wiederherstellen</strong>
                <div style="font-size:13px; color:#475569; margin-top:4px;">Backup-ZIP hochladen, DB und Anhänge einspielen — z. B. für Umzug auf neuen Host.</div>
            </a>
        </div>
    @endif

    <div style="margin-top:20px; text-align: right;">
        <a class="link" href="{{ url('/install') }}">Neu prüfen</a>
    </div>
</div></div>
</body>
</html>
