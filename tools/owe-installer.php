<?php
/**
 * Open Workflow Engine — Bootstrap-Installer
 * ──────────────────────────────────────────
 * Einzelne PHP-Datei, die du via FTP in den (leeren) Webroot deines
 * Hosters lädst und im Browser oeffnest. Sie laedt die neueste
 * OWE-Version vom Update-Proxy, entpackt sie hier und leitet
 * anschliessend zum App-Installer (/install) weiter.
 *
 * Loescht sich am Ende selbst.
 *
 * Hosted at: https://github.com/friloo/open-workflow-engine
 * License:   MIT
 */

declare(strict_types=1);

// ────────────────────────────────────────────────────────────────────
// Konfiguration
// ────────────────────────────────────────────────────────────────────

const OWE_CHANNELS = [
    'stable' => 'https://update.loheide.eu/open-workflow-engine',
    'development' => 'https://update.loheide.eu/open-workflow-engine-development',
];
const OWE_USER_AGENT = 'owe-Bootstrap/1.0';
const OWE_MIN_PHP = '8.2.0';
const OWE_ZIP_NAME = '.owe-bootstrap.zip';
const OWE_STATE_NAME = '.owe-bootstrap.json';

@set_time_limit(600);

$baseDir = __DIR__;
$selfFile = __FILE__;
$step = preg_replace('/[^a-z]/', '', (string) ($_REQUEST['step'] ?? 'welcome'));
$channel = ((string) ($_REQUEST['channel'] ?? '')) === 'development' ? 'development' : 'stable';
$baseUrl = OWE_CHANNELS[$channel];

if (! in_array($step, ['welcome', 'download', 'extract', 'finish', 'doFinish'], true)) {
    $step = 'welcome';
}

$precond = owe_check_precondition($baseDir);

// ────────────────────────────────────────────────────────────────────
// Hauptschalter
// ────────────────────────────────────────────────────────────────────

owe_render_start($step, $channel);

if ($precond['blocked'] && $step === 'welcome') {
    owe_render_blocked($precond['reason']);
    owe_render_end();
    exit;
}

switch ($step) {
    case 'welcome':
        owe_step_welcome($channel);
        break;
    case 'download':
        owe_step_download($baseDir, $baseUrl, $channel);
        break;
    case 'extract':
        owe_step_extract($baseDir, $selfFile, $channel);
        break;
    case 'finish':
        owe_step_finish($channel);
        break;
    case 'doFinish':
        owe_do_finish($baseDir, $selfFile);
        // doFinish does its own header() + exit
        break;
}

owe_render_end();

// ════════════════════════════════════════════════════════════════════
// Steps
// ════════════════════════════════════════════════════════════════════

function owe_step_welcome(string $channel): void
{
    ?>
    <h2>Bootstrap-Installer</h2>
    <p>Dieses kleine PHP-Skript laedt die <strong>aktuelle Version</strong> der
       Open Workflow Engine vom Update-Proxy <code>update.loheide.eu</code>,
       entpackt sie hier ins Webroot und leitet anschliessend zum
       eingebauten App-Installer <code>/install</code> weiter.</p>
    <p class="muted">Funktioniert ohne SSH und ohne Composer — solltest du nur einmal nutzen, danach loescht sich diese Datei selbst.</p>

    <form method="post" action="?step=download">
        <h3 style="margin-top:24px;">Channel</h3>
        <label class="radio">
            <input type="radio" name="channel" value="stable" <?= $channel === 'stable' ? 'checked' : '' ?>>
            <span><strong>Stable</strong> &mdash; empfohlen fuer Produktion</span>
        </label>
        <label class="radio">
            <input type="radio" name="channel" value="development" <?= $channel === 'development' ? 'checked' : '' ?>>
            <span><strong>Development</strong> &mdash; Vorschau, nicht produktiv nutzen</span>
        </label>

        <div class="info">Geladen von: <code><?= htmlspecialchars(OWE_CHANNELS[$channel]) ?></code></div>

        <p style="text-align:right;">
            <button type="submit" class="primary">Loslegen →</button>
        </p>
    </form>
    <?php
}

function owe_step_download(string $baseDir, string $baseUrl, string $channel): void
{
    echo '<h2>Schritt 1: Download</h2>';

    // 1. Aktuelle Version vom Proxy holen
    [$sha, $err] = owe_http_get_text($baseUrl.'/version');
    if ($err !== null) {
        owe_render_error('Konnte aktuelle Version nicht abfragen: '.$err);
        return;
    }
    $sha = trim($sha);
    if (! preg_match('/^[0-9a-f]{40}$/', $sha)) {
        owe_render_error('Antwort vom Proxy ist keine 40-stellige SHA: '.htmlspecialchars(substr($sha, 0, 120)));
        return;
    }
    echo '<p>Aktuelle Version (Channel <em>'.htmlspecialchars($channel).'</em>): <code>'.htmlspecialchars($sha).'</code></p>';

    // 2. ZIP herunterladen
    $zipPath = $baseDir.DIRECTORY_SEPARATOR.OWE_ZIP_NAME;
    @unlink($zipPath);
    echo '<p>Lade ZIP herunter &hellip;</p>';

    $err = owe_http_get_to_file($baseUrl.'/zip?ref='.urlencode($sha), $zipPath);
    if ($err !== null) {
        owe_render_error('ZIP-Download fehlgeschlagen: '.$err);
        return;
    }
    $size = filesize($zipPath) ?: 0;
    if ($size < 1024) {
        @unlink($zipPath);
        owe_render_error('ZIP-Download fehlgeschlagen: Datei zu klein ('.$size.' bytes).');
        return;
    }

    echo '<div class="ok">Heruntergeladen: '.number_format($size / 1024 / 1024, 2).' MB</div>';

    // State speichern
    owe_state_save($baseDir, ['sha' => $sha, 'channel' => $channel, 'zip' => OWE_ZIP_NAME]);

    ?>
    <p style="text-align:right;">
        <a href="?step=extract&amp;channel=<?= htmlspecialchars($channel) ?>" class="primary-link">Weiter zum Entpacken →</a>
    </p>
    <?php
}

function owe_step_extract(string $baseDir, string $selfFile, string $channel): void
{
    echo '<h2>Schritt 2: Entpacken</h2>';

    $state = owe_state_load($baseDir);
    if (! $state) {
        owe_render_error('Bootstrap-State nicht gefunden. Bitte mit Schritt 1 starten.');
        echo '<p><a href="?step=welcome">Zurueck</a></p>';
        return;
    }

    $zipPath = $baseDir.DIRECTORY_SEPARATOR.$state['zip'];
    if (! is_file($zipPath)) {
        owe_render_error('ZIP-Datei fehlt: '.$zipPath);
        echo '<p><a href="?step=welcome">Zurueck</a></p>';
        return;
    }

    $zip = new ZipArchive();
    $code = $zip->open($zipPath);
    if ($code !== true) {
        owe_render_error('ZIP konnte nicht geoeffnet werden (Code '.$code.').');
        return;
    }

    // Root-Folder erkennen (Github-zipball hat einen Root-Ordner)
    $rootPrefix = '';
    if ($zip->numFiles > 0) {
        $firstName = $zip->getNameIndex(0);
        $firstSlash = strpos($firstName, '/');
        if ($firstSlash !== false) {
            $candidate = substr($firstName, 0, $firstSlash + 1);
            $consistent = true;
            for ($i = 0; $i < min($zip->numFiles, 20); $i++) {
                if (! str_starts_with($zip->getNameIndex($i), $candidate)) {
                    $consistent = false;
                    break;
                }
            }
            if ($consistent) $rootPrefix = $candidate;
        }
    }

    $skip = [basename($selfFile), OWE_ZIP_NAME, OWE_STATE_NAME];

    $count = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($rootPrefix !== '' && ! str_starts_with($entry, $rootPrefix)) continue;
        $rel = $rootPrefix !== '' ? substr($entry, strlen($rootPrefix)) : $entry;
        $rel = ltrim($rel, '/');
        if ($rel === '') continue;
        if (in_array(basename($rel), $skip, true)) continue;

        $target = $baseDir.DIRECTORY_SEPARATOR.$rel;
        if (str_ends_with($entry, '/')) {
            if (! is_dir($target)) @mkdir($target, 0775, true);
            continue;
        }
        @mkdir(dirname($target), 0775, true);
        $stream = $zip->getStream($entry);
        if ($stream === false) continue;
        $out = @fopen($target, 'wb');
        if ($out === false) {
            fclose($stream);
            continue;
        }
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        $count++;
    }
    $zip->close();

    // .version Marker schreiben
    @file_put_contents($baseDir.DIRECTORY_SEPARATOR.'.version', $state['sha']);

    // ZIP wegraeumen
    @unlink($zipPath);

    echo '<div class="ok">Entpackt: '.number_format($count).' Datei(en). Version-Marker <code>.version</code> gesetzt.</div>';

    if (! is_file($baseDir.'/vendor/autoload.php')) {
        echo '<div class="warn">⚠️ <strong>vendor/autoload.php</strong> nicht gefunden. Wenn das Release-ZIP <code>vendor/</code> normalerweise enthaelt, war beim Pull etwas faul. Andernfalls musst du jetzt einmalig <code>composer install --no-dev</code> lokal ausfuehren und <code>vendor/</code> per FTP hochladen.</div>';
    } else {
        echo '<div class="ok">vendor/autoload.php gefunden — Laravel bootet.</div>';
    }

    ?>
    <p style="text-align:right;">
        <a href="?step=finish&amp;channel=<?= htmlspecialchars($channel) ?>" class="primary-link">Weiter →</a>
    </p>
    <?php
}

function owe_step_finish(string $channel): void
{
    ?>
    <h2>Schritt 3: Abschliessen</h2>
    <p>Gleich geht's los: dieses Bootstrap-Skript wird sich <strong>selbst loeschen</strong>,
       und du wirst direkt zum eingebauten App-Installer (<code>/install</code>) weitergeleitet.</p>
    <p>Im App-Installer machst du dann:</p>
    <ul>
        <li>System-Pruefung (sollte gruen sein)</li>
        <li>Datenbank waehlen (SQLite default, MySQL optional)</li>
        <li>Admin-Konto anlegen <em>oder</em> Backup hochladen (Migration)</li>
    </ul>

    <form method="post" action="?step=doFinish">
        <p style="text-align:right;">
            <button type="submit" class="primary">Selbst loeschen und zum /install →</button>
        </p>
    </form>
    <?php
}

function owe_do_finish(string $baseDir, string $selfFile): void
{
    @unlink($baseDir.DIRECTORY_SEPARATOR.OWE_STATE_NAME);
    @unlink($baseDir.DIRECTORY_SEPARATOR.OWE_ZIP_NAME);

    // Selbst loeschen — best effort.
    $deleted = @unlink($selfFile);

    $target = '/install';
    header('Location: '.$target, true, 302);
    // Fallback HTML, falls Header nicht greift
    echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='.$target.'">';
    echo '<p>Weiterleitung zu <a href="'.$target.'">'.$target.'</a> &hellip;</p>';
    if (! $deleted) {
        echo '<p style="color:#7f1d1d;">⚠️ Konnte mich selbst nicht loeschen. Bitte <strong>'.htmlspecialchars(basename($selfFile)).'</strong> manuell per FTP entfernen.</p>';
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════
// Helpers
// ════════════════════════════════════════════════════════════════════

function owe_check_precondition(string $dir): array
{
    if (version_compare(PHP_VERSION, OWE_MIN_PHP, '<')) {
        return ['blocked' => true, 'reason' => 'PHP-Version zu alt: '.PHP_VERSION.' (benoetigt '.OWE_MIN_PHP.' oder neuer).'];
    }
    foreach (['zip'] as $ext) {
        if (! extension_loaded($ext)) {
            return ['blocked' => true, 'reason' => "PHP-Extension '{$ext}' fehlt. Bitte beim Hoster aktivieren lassen."];
        }
    }
    if (! function_exists('curl_init') && ! ini_get('allow_url_fopen')) {
        return ['blocked' => true, 'reason' => "Weder cURL noch allow_url_fopen verfuegbar — kein HTTP-Download moeglich. Bitte einen davon aktivieren."];
    }
    if (! is_writable($dir)) {
        return ['blocked' => true, 'reason' => "Verzeichnis nicht beschreibbar: {$dir}"];
    }
    if (is_file($dir.'/storage/app/.installed')) {
        return ['blocked' => true, 'reason' => "OWE ist hier bereits installiert (storage/app/.installed gefunden). Fuer Updates bitte /admin/update in der App nutzen — nicht diesen Bootstrap erneut laufen lassen."];
    }
    if (is_file($dir.'/vendor/autoload.php')) {
        return ['blocked' => true, 'reason' => "vendor/autoload.php existiert bereits — sieht nach einer existierenden Installation aus. Bootstrap abgebrochen, damit nichts ueberschrieben wird. Bitte das Webroot leeren oder einen neuen Pfad nutzen."];
    }
    return ['blocked' => false, 'reason' => null];
}

function owe_http_get_text(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => OWE_USER_AGENT,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) return ['', $err ?: 'cURL-Fehler'];
        if ($code !== 200) return ['', "HTTP {$code}"];
        return [(string) $body, null];
    }
    $ctx = stream_context_create(['http' => ['user_agent' => OWE_USER_AGENT, 'timeout' => 30]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return ['', 'file_get_contents fehlgeschlagen'];
    return [$body, null];
}

function owe_http_get_to_file(string $url, string $path): ?string
{
    if (function_exists('curl_init')) {
        $fp = @fopen($path, 'wb');
        if ($fp === false) return 'Datei nicht schreibbar: '.$path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_USERAGENT => OWE_USER_AGENT,
        ]);
        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if (! $ok) return $err ?: 'cURL-Fehler';
        if ($code !== 200) {
            @unlink($path);
            return "HTTP {$code}";
        }
        return null;
    }
    $ctx = stream_context_create(['http' => ['user_agent' => OWE_USER_AGENT, 'timeout' => 600]]);
    $bytes = @copy($url, $path, $ctx);
    return $bytes ? null : 'copy() fehlgeschlagen';
}

function owe_state_save(string $dir, array $data): void
{
    @file_put_contents($dir.DIRECTORY_SEPARATOR.OWE_STATE_NAME, json_encode($data));
}

function owe_state_load(string $dir): ?array
{
    $path = $dir.DIRECTORY_SEPARATOR.OWE_STATE_NAME;
    if (! is_file($path)) return null;
    $data = json_decode((string) @file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

// ════════════════════════════════════════════════════════════════════
// UI
// ════════════════════════════════════════════════════════════════════

function owe_render_start(string $step, string $channel): void
{
    $stepIdx = match ($step) {
        'welcome' => 1,
        'download' => 2,
        'extract' => 3,
        default => 4,
    };
    ?>
    <!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OWE Bootstrap-Installer</title>
        <style>
            :root { color-scheme: light; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                   background: #f1f5f9; color: #0f172a; margin: 0; min-height: 100vh; line-height: 1.5; }
            .wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
            .card { background: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(15,23,42,0.06);
                    padding: 32px; border: 1px solid #e2e8f0; }
            h1 { margin: 0 0 4px; font-size: 24px; }
            h2 { margin: 24px 0 6px; font-size: 18px; }
            h3 { margin: 16px 0 4px; font-size: 14px; color:#475569; text-transform: uppercase; letter-spacing: 0.05em; }
            p { margin: 8px 0; }
            .sub { color: #64748b; margin: 0 0 20px; }
            .muted { color: #64748b; font-size: 14px; }
            code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-size: 13px; }
            .steps { display: flex; gap: 6px; margin-bottom: 24px; font-size: 12px; }
            .step { padding: 6px 12px; border-radius: 999px; background: #e2e8f0; color: #475569; flex: 1; text-align: center; }
            .step.active { background: #6366f1; color: white; }
            .step.done { background: #10b981; color: white; }
            label.radio { display:flex; align-items:flex-start; gap:10px; padding:12px; border:1px solid #e2e8f0;
                          border-radius:10px; margin: 6px 0; cursor: pointer; }
            label.radio:hover { background: #f8fafc; }
            label.radio input { margin-top: 4px; }
            label.radio:has(input:checked) { border-color: #6366f1; background: #eef2ff; }
            button.primary, a.primary-link { display:inline-block; background:#6366f1; color:white;
                                              border:0; padding:12px 22px; border-radius:10px; font-weight:600;
                                              cursor:pointer; font-size:14px; text-decoration:none; }
            button.primary:hover, a.primary-link:hover { background:#4f46e5; }
            .info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
                    padding: 10px 12px; border-radius: 8px; font-size: 13px; margin: 14px 0; }
            .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;
                  padding: 12px; border-radius: 8px; font-size: 14px; margin: 14px 0; }
            .warn { background: #fffbeb; border: 1px solid #fde68a; color: #78350f;
                    padding: 12px; border-radius: 8px; font-size: 14px; margin: 14px 0; }
            .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
                   padding: 12px; border-radius: 8px; font-size: 14px; margin: 14px 0; }
            ul { padding-left: 20px; }
        </style>
    </head>
    <body>
    <div class="wrap">
        <div class="card">
            <h1>Open Workflow Engine</h1>
            <p class="sub">Bootstrap-Installer · holt die aktuelle Version vom Update-Proxy</p>
            <div class="steps">
                <span class="step <?= $stepIdx === 1 ? 'active' : ($stepIdx > 1 ? 'done' : '') ?>">1. Start</span>
                <span class="step <?= $stepIdx === 2 ? 'active' : ($stepIdx > 2 ? 'done' : '') ?>">2. Download</span>
                <span class="step <?= $stepIdx === 3 ? 'active' : ($stepIdx > 3 ? 'done' : '') ?>">3. Entpacken</span>
                <span class="step <?= $stepIdx === 4 ? 'active' : '' ?>">4. Fertig</span>
            </div>
    <?php
}

function owe_render_end(): void
{
    echo '</div></div></body></html>';
}

function owe_render_blocked(string $reason): void
{
    echo '<div class="err"><strong>Abbruch:</strong> '.htmlspecialchars($reason).'</div>';
    echo '<p class="muted">Wenn das ein Versehen ist (z. B. existierende, aber kaputte Installation),'
        .' Webroot leeren oder einen neuen Unterordner anlegen und den Bootstrap-Installer dorthin hochladen.</p>';
}

function owe_render_error(string $msg): void
{
    echo '<div class="err">'.htmlspecialchars($msg).'</div>';
    echo '<p><a href="?step=welcome">← Zurueck zum Start</a></p>';
}
