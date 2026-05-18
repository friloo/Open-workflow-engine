<?php

namespace App\Services\Update;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Update-Pipeline:
 *
 *   1. Channel + aktuelle SHA bestimmen.
 *   2. Remote /version -> Soll-SHA.
 *   3. Bei Aenderung: ZIP herunterladen, in Staging entpacken.
 *   4. Maintenance aktivieren.
 *   5. Atomar in das Projekt kopieren, dabei PROTECTED_PATHS nie anfassen.
 *   6. composer install --no-dev und php artisan migrate --force ausfuehren.
 *   7. .version aktualisieren, Maintenance IMMER deaktivieren (finally).
 *
 * Fortschritt landet in storage/app/.update-progress (JSON) und kann von
 * der UI per Polling abgefragt werden.
 */
class UpdateManager
{
    public const USER_AGENT = 'owe-Updater/1.0';
    public const PROGRESS_FILE = '.update-progress';
    public const VERSION_FILE = '.version';
    public const MAINTENANCE_FILE = '.maintenance';
    public const STAGING_DIR = '.update-staging';

    /** Pfade RELATIV zum Projekt-Root, die beim Update NIE ueberschrieben werden. */
    public const PROTECTED_PATHS = [
        '.env',
        '.env.backup',
        'storage',
        'database/database.sqlite',
        'database/database.sqlite-journal',
        'database/database.sqlite-shm',
        'database/database.sqlite-wal',
        'public/storage',
        'public/.htaccess',                // shared hosting: vom Hoster gepflegt
        '.htaccess',
        'bootstrap/cache',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function currentVersion(): ?string
    {
        $path = base_path(self::VERSION_FILE);
        if (! is_file($path)) return null;
        $sha = trim((string) @file_get_contents($path));
        return $this->isValidSha($sha) ? $sha : null;
    }

    /** @return array{current: ?string, latest: ?string, has_update: bool, channel: string, label: string, error: ?string} */
    public function check(): array
    {
        $channel = UpdateChannelFactory::current();
        $current = $this->currentVersion();
        try {
            $resp = Http::withUserAgent(self::USER_AGENT)->timeout(20)->get($channel->baseUrl.'/version');
            $latest = trim((string) $resp->body());
            if (! $this->isValidSha($latest)) {
                return ['current' => $current, 'latest' => null, 'has_update' => false, 'channel' => $channel->slug, 'label' => $channel->label, 'error' => 'Antwort nicht erkennbar.'];
            }
            return [
                'current' => $current,
                'latest' => $latest,
                'has_update' => $current !== $latest,
                'channel' => $channel->slug,
                'label' => $channel->label,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['current' => $current, 'latest' => null, 'has_update' => false, 'channel' => $channel->slug, 'label' => $channel->label, 'error' => $e->getMessage()];
        }
    }

    public function isMaintenanceActive(): bool
    {
        return is_file(base_path(self::MAINTENANCE_FILE));
    }

    public function getProgress(): array
    {
        $path = storage_path('app/'.self::PROGRESS_FILE);
        if (! is_file($path)) {
            return ['stage' => 'idle', 'message' => 'Kein Update laufend.', 'updated_at' => null];
        }
        $json = @json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : ['stage' => 'idle', 'message' => '', 'updated_at' => null];
    }

    public function run(?int $userId = null): array
    {
        $channel = UpdateChannelFactory::current();
        $check = $this->check();
        if (! empty($check['error'])) {
            throw new \RuntimeException('Versionscheck fehlgeschlagen: '.$check['error']);
        }
        if (! $check['has_update']) {
            return ['status' => 'noop', 'message' => 'Schon aktuell.', 'version' => $check['current']];
        }

        $latest = (string) $check['latest'];
        $this->setMaintenance(true);

        try {
            $this->progress('download', "Lade {$channel->slug} {$latest}…");
            $zipPath = $this->downloadZip($channel, $latest);

            $this->progress('stage', 'Entpacke nach Staging…');
            $staging = $this->prepareStaging($zipPath);

            $this->progress('install', 'Aktualisiere Dateien…');
            $this->applyStaging($staging);

            $this->progress('composer', 'composer install --no-dev (optional) …');
            $this->runComposerIfPossible();

            // Migrate IM PROZESS — so brauchen wir kein proc_open.
            $this->progress('migrate', 'Datenbank-Migrationen …');
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

            $this->progress('finalize', 'Schliesse ab…');
            @file_put_contents(base_path(self::VERSION_FILE), $latest);
            $this->cleanupStaging();
            @unlink(base_path('storage/app/'.self::PROGRESS_FILE));

            $this->audit->log('update.completed', null, ['previous' => $check['current']], [
                'channel' => $channel->slug,
                'version' => $latest,
            ], "Update auf {$latest} ({$channel->slug}) abgeschlossen", $userId);

            return ['status' => 'ok', 'version' => $latest, 'channel' => $channel->slug];
        } catch (\Throwable $e) {
            $this->progress('failed', 'Fehler: '.$e->getMessage());
            Log::error('Update failed', ['error' => $e->getMessage()]);
            $this->audit->log('update.failed', null, null, [
                'channel' => $channel->slug,
                'target' => $latest,
                'error' => $e->getMessage(),
            ], 'Update fehlgeschlagen: '.$e->getMessage(), $userId);
            throw $e;
        } finally {
            // Maintenance MUSS in finally aus — sonst bleibt die App 503.
            $this->setMaintenance(false);
        }
    }

    private function downloadZip(UpdateChannel $channel, string $sha): string
    {
        $url = $channel->baseUrl.'/zip?ref='.urlencode($sha);
        $resp = Http::withUserAgent(self::USER_AGENT)->timeout(180)->get($url);
        if (! $resp->successful()) {
            throw new \RuntimeException("ZIP-Download HTTP {$resp->status()} fuer {$sha}");
        }
        $path = storage_path('app/'.self::STAGING_DIR.'.zip');
        @mkdir(dirname($path), 0775, true);
        if (file_put_contents($path, $resp->body()) === false) {
            throw new \RuntimeException('ZIP konnte nicht gespeichert werden.');
        }
        return $path;
    }

    private function prepareStaging(string $zipPath): string
    {
        $staging = storage_path('app/'.self::STAGING_DIR);
        $this->rrmdir($staging);
        @mkdir($staging, 0775, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('ZIP konnte nicht geoeffnet werden.');
        }
        $zip->extractTo($staging);
        $zip->close();
        @unlink($zipPath);

        // Wenn der Proxy einen Wurzel-Ordner mitliefert (Repo-zipball), in
        // den ersten Unter-Ordner ducken.
        $entries = array_values(array_filter(scandir($staging), fn ($e) => $e !== '.' && $e !== '..'));
        if (count($entries) === 1 && is_dir($staging.'/'.$entries[0])) {
            return $staging.'/'.$entries[0];
        }
        return $staging;
    }

    private function applyStaging(string $staging): void
    {
        $root = base_path();
        $protected = self::PROTECTED_PATHS;

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($staging, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $rel = ltrim(str_replace($staging, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            if ($rel === '') continue;
            if ($this->isProtected($rel, $protected)) continue;

            $target = $root.DIRECTORY_SEPARATOR.$rel;
            if ($item->isDir()) {
                if (! is_dir($target)) @mkdir($target, 0775, true);
            } else {
                @mkdir(dirname($target), 0775, true);
                if (! @copy($item->getPathname(), $target)) {
                    throw new \RuntimeException("Kopie fehlgeschlagen: {$rel}");
                }
            }
        }
    }

    private function isProtected(string $rel, array $protected): bool
    {
        $relNorm = str_replace('\\', '/', $rel);
        foreach ($protected as $p) {
            $p = str_replace('\\', '/', $p);
            if ($relNorm === $p) return true;
            if (str_starts_with($relNorm, $p.'/')) return true;
        }
        return false;
    }

    private function cleanupStaging(): void
    {
        $this->rrmdir(storage_path('app/'.self::STAGING_DIR));
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private function setMaintenance(bool $on): void
    {
        $path = base_path(self::MAINTENANCE_FILE);
        if ($on) {
            @file_put_contents($path, (string) now()->toIso8601String());
        } else {
            @unlink($path);
        }
    }

    private function progress(string $stage, string $message): void
    {
        @file_put_contents(storage_path('app/'.self::PROGRESS_FILE), json_encode([
            'stage' => $stage,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function runProcess(array $cmd, int $timeout): void
    {
        $process = new Process($cmd, base_path(), null, null, $timeout);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException(implode(' ', $cmd).' failed: '.$process->getErrorOutput());
        }
    }

    /**
     * Versucht `composer install --no-dev` — schluckt aber Fehler, weil
     * Shared Hosts oft kein proc_open / kein composer im PATH haben. Das
     * Release-ZIP enthaelt vendor/ ohnehin vorgebaut, daher ist composer
     * im Normalfall unnoetig.
     */
    private function runComposerIfPossible(): void
    {
        if (! function_exists('proc_open')) {
            $this->progress('composer', 'composer install uebersprungen (proc_open deaktiviert). vendor/ aus dem Release-ZIP wird verwendet.');
            return;
        }
        try {
            $this->runProcess(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'], 600);
        } catch (\Throwable $e) {
            $this->progress('composer', 'composer install uebersprungen ('.\Illuminate\Support\Str::limit($e->getMessage(), 120).'). vendor/ aus dem Release-ZIP wird verwendet.');
        }
    }

    private function isValidSha(string $sha): bool
    {
        return (bool) preg_match('/^[0-9a-f]{40}$/', $sha);
    }
}
