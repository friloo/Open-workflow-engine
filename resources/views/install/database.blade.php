<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OWE Installer — Datenbank</title>
    @include('install._styles')
</head>
<body>
<div class="wrap">
    @include('install._header', ['step' => 2])

    @if($error)
        <div class="alert-error">{{ $error }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-error">
            <ul style="margin: 4px 0 0 18px; padding: 0;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('install.database') }}">
        @csrf

        <h2>App-Konfiguration</h2>
        <div class="row">
            <div>
                <label for="app_name">App-Name</label>
                <input id="app_name" name="app_name" value="{{ old('app_name', $defaults['app_name'] ?? 'OWE') }}" required>
            </div>
            <div>
                <label for="app_url">App-URL</label>
                <input id="app_url" name="app_url" type="url" value="{{ old('app_url', $defaults['app_url'] ?? url('/')) }}" required>
            </div>
        </div>

        <h2>Datenbank</h2>
        <label for="driver">Datenbank-Treiber</label>
        <select id="driver" name="driver" onchange="document.getElementById('mysql').style.display = this.value === 'mysql' ? 'block' : 'none';">
            <option value="sqlite" @selected(old('driver', $defaults['driver']) === 'sqlite')>SQLite (Datei) — empfohlen fuer Shared Hosting</option>
            <option value="mysql" @selected(old('driver', $defaults['driver']) === 'mysql')>MySQL / MariaDB</option>
        </select>

        <div id="mysql" style="display: {{ old('driver', $defaults['driver']) === 'mysql' ? 'block' : 'none' }};">
            <div class="row">
                <div>
                    <label for="host">Host</label>
                    <input id="host" name="host" value="{{ old('host', $defaults['host']) }}">
                </div>
                <div>
                    <label for="port">Port</label>
                    <input id="port" name="port" type="number" value="{{ old('port', $defaults['port']) }}">
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="database">Datenbank-Name</label>
                    <input id="database" name="database" value="{{ old('database', $defaults['database']) }}">
                </div>
                <div>
                    <label for="username">Benutzer</label>
                    <input id="username" name="username" value="{{ old('username', $defaults['username']) }}">
                </div>
            </div>
            <label for="password">Passwort</label>
            <input id="password" name="password" type="password">
        </div>

        <div class="alert-info" style="margin-top:16px;">
            Beim Speichern werden Migrationen + Seed automatisch ausgefuehrt. Das kann ein paar Sekunden dauern.
        </div>

        <div style="margin-top:20px; text-align: right;">
            <a class="link" href="{{ route('install.welcome') }}">← Zurueck</a>
            &nbsp;&nbsp;
            <button type="submit" class="primary">Speichern & Migrieren →</button>
        </div>
    </form>
</div></div>
</body>
</html>
