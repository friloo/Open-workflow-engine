<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OWE Installer — Backup eingespielt</title>
    @include('install._styles')
</head>
<body>
<div class="wrap">
    @include('install._header', ['step' => 4])

    <div class="alert-ok">
        <strong>Backup erfolgreich eingespielt.</strong> Alle Benutzer, Workflows, Dokumente und Anhänge aus dem Backup sind jetzt verfügbar.
    </div>

    <h2>Nächste Schritte</h2>
    <ol style="font-size:14px; line-height:1.7; color:#334155;">
        <li><a class="link" href="{{ route('login') }}">Anmelden</a> mit deinen <strong>bisherigen</strong> Zugangsdaten — der Admin-Account aus dem Backup ist aktiv.</li>
        <li>Pruefe unter <em>Verwaltung → Systemeinstellungen → Mail</em>, ob die SMTP-Daten zum neuen Host passen.</li>
        <li>Pruefe unter <em>Verwaltung → E-Mail-Postfächer</em>, ob die IMAP-Verbindungen vom neuen Host aus klappen (Test-Button).</li>
        <li>Cron-Eintrag setzen, falls noch nicht geschehen:
            <code style="display:block; margin-top:4px;">* * * * * cd {{ base_path() }} && php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code>
        </li>
        <li>System-Health unter <em>Verwaltung → System-Health</em> einmal prüfen.</li>
    </ol>

    <div style="margin-top:20px; text-align: right;">
        <a href="{{ route('login') }}"><button type="button" class="primary">Zum Login →</button></a>
    </div>
</div></div>
</body>
</html>
