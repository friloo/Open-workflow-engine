<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OWE Installer — System-Pruefung</title>
    @include('install._styles')
</head>
<body>
<div class="wrap">
    @include('install._header', ['step' => 1])
    <h2>System-Pruefung</h2>
    <p class="sub">Diese Voraussetzungen muessen erfuellt sein, damit OWE laeuft.</p>
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
        <div class="alert-info" style="margin-top:16px;">
            System sieht gut aus. Weiter zur Datenbank-Konfiguration.
        </div>
    @endif

    <div style="margin-top:20px; text-align: right;">
        <a class="link" href="{{ url('/install') }}">Neu pruefen</a>
        &nbsp;&nbsp;
        @if($canProceed)
            <a href="{{ route('install.database') }}"><button type="button" class="primary">Weiter zur Datenbank →</button></a>
        @else
            <button type="button" class="primary" disabled>Weiter zur Datenbank →</button>
        @endif
    </div>
</div></div>
</body>
</html>
