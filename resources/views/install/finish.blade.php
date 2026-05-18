<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OWE Installer — Fertig</title>
    @include('install._styles')
</head>
<body>
<div class="wrap">
    @include('install._header', ['step' => 4])

    <div class="alert-ok">
        <strong>Installation abgeschlossen.</strong> Der Installer ist ab sofort gesperrt.
    </div>

    <h2>Naechste Schritte</h2>
    <ol style="font-size:14px; line-height:1.7; color:#334155;">
        <li><a class="link" href="{{ route('login') }}">Anmelden</a> mit dem eben erstellten Admin-Account.</li>
        <li>SMTP-Daten unter <em>Verwaltung → Systemeinstellungen → Mail</em> hinterlegen + Test-Mail.</li>
        <li>(Optional) Microsoft 365 SSO einrichten.</li>
        <li>(Optional) KI fuer Workflow-Designer-Assistenz.</li>
        <li>Cron-Eintrag setzen — ein einziger reicht:
            <code style="display:block; margin-top:4px;">* * * * * cd {{ base_path() }} && php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code>
        </li>
        <li>Erste Workflow-Vorlage importieren: <em>Workflows → Vorlagen</em>.</li>
    </ol>

    <div class="alert-info">
        Tiefer einsteigen: <em>Cookbook: Rechnungseingang</em> in der Online-Hilfe zeigt das volle Setup
        (Liste, Schema, IMAP, Workflow) in 30 Minuten.
    </div>

    <div style="margin-top:20px; text-align: right;">
        <a href="{{ route('login') }}"><button type="button" class="primary">Zum Login →</button></a>
    </div>
</div></div>
</body>
</html>
