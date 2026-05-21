<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OWE Installer — Aus Backup wiederherstellen</title>
    @include('install._styles')
</head>
<body>
<div class="wrap">
    @include('install._header', ['step' => 2])

    <h2>Aus Backup wiederherstellen</h2>
    <p class="sub">Lade ein <strong>OWE-Backup-ZIP</strong> hoch. DB-Inhalte und alle Anhänge werden eingespielt — perfekt für Umzug auf einen neuen Host.</p>

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

    <form method="POST" action="{{ route('install.restore') }}" enctype="multipart/form-data">
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

        <h2>Datenbank-Treiber</h2>
        <p class="sub">Muss zum Treiber des Backups passen (steht in der <code>manifest.json</code> im ZIP).</p>
        <select id="driver" name="driver" onchange="document.getElementById('mysql').style.display = this.value === 'mysql' ? 'block' : 'none';">
            <option value="sqlite" @selected(old('driver', $defaults['driver']) === 'sqlite')>SQLite</option>
            <option value="mysql" @selected(old('driver', $defaults['driver']) === 'mysql')>MySQL / MariaDB</option>
        </select>

        <div id="mysql" style="display: {{ old('driver', $defaults['driver']) === 'mysql' ? 'block' : 'none' }};">
            <div class="row">
                <div><label for="host">Host</label><input id="host" name="host" value="{{ old('host', $defaults['host']) }}"></div>
                <div><label for="port">Port</label><input id="port" name="port" type="number" value="{{ old('port', $defaults['port']) }}"></div>
            </div>
            <div class="row">
                <div><label for="database">Datenbank-Name</label><input id="database" name="database" value="{{ old('database', $defaults['database']) }}"></div>
                <div><label for="username">Benutzer</label><input id="username" name="username" value="{{ old('username', $defaults['username']) }}"></div>
            </div>
            <label for="password">Passwort</label>
            <input id="password" name="password" type="password">
        </div>

        <h2>Backup-Datei</h2>
        <label for="backup_file">ZIP-Datei auswählen</label>
        <input id="backup_file" name="backup_file" type="file" accept=".zip" required>
        <p class="sub" style="margin-top:6px;">
            Max. Upload-Größe auf diesem Server: <strong>{{ $uploadMaxMb }} MB</strong>
            (begrenzt durch PHP <code>upload_max_filesize</code> / <code>post_max_size</code>).
            Bei größeren Backups bitte den Server-Admin um eine höhere Grenze, oder Backup
            per FTP nach <code>storage/app/backups/</code> kopieren und über CLI
            <code>php artisan backup:restore &lt;datei&gt;</code> restoren.
        </p>

        <div class="alert-error" style="margin-top:12px;">
            <label style="display:flex; gap:8px; align-items:flex-start; margin:0; color:#7f1d1d;">
                <input type="checkbox" name="confirm" value="1" style="width:auto; margin-top:3px;" required>
                <span><strong>Mir ist klar:</strong> bestehende Daten (DB und Anhänge) auf diesem Server werden <u>überschrieben</u>.</span>
            </label>
        </div>

        <div style="margin-top:20px; text-align: right;">
            <a class="link" href="{{ route('install.welcome') }}">← Zurück</a>
            &nbsp;&nbsp;
            <button type="submit" class="primary">Backup einspielen →</button>
        </div>
    </form>
</div></div>
</body>
</html>
