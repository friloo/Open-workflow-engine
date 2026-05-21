<?php

namespace Updater;

use Illuminate\Database\ConnectionInterface;

/**
 * Zentrale Orchestrierung des Update-Vorgangs. Versteht keine Laravel-
 * Spezifika ausser dem ConnectionInterface — sonst pure PHP. Bringt
 * seine Hilfs-Klassen (ProxyClient, StagingApplier, MigrationsRunner)
 * via Konstruktor mit und delegiert.
 *
 * Storage-Konvention dieses Projekts:
 *   - .version              : 40-stelliger Git-SHA
 *   - .maintenance          : Existiert-Datei; Front-Controller liest sie
 *   - .update-staging/      : Entpackt-Verzeichnis fuer das neue ZIP
 *   - .update-progress      : JSON mit Status-Felder (step, message, percent)
 *   - updater-settings.json : aktuell nur 'channel'
 *
 * Alle liegen unter <projectRoot>/ — .maintenance ist absichtlich auf
 * der gleichen Ebene wie public/, weil der Front-Controller-Hook dort
 * sucht. Die anderen Updater-Artefakte koennen verschoben werden falls
 * es bessere Ablage-Konventionen gibt.
 */
final class UpdateManager
{
    public const CHANNELS = [
        'stable'      => 'https://update.loheide.eu/open-workflow-engine',
        'development' => 'https://update.loheide.eu/open-workflow-engine-development',
    ];

    private readonly ProxyClient $proxy;
    private readonly StagingApplier $applier;
    private readonly MigrationsRunner $migrations;
    private readonly string $projectRoot;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly mixed $audit = null,
        private readonly string $channel = 'stable',
        ?ProxyClient $proxy = null,
        ?StagingApplier $applier = null,
        ?MigrationsRunner $migrations = null,
        ?string $projectRoot = null,
    ) {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
        $baseUrl = self::CHANNELS[$channel] ?? self::CHANNELS['stable'];
        $this->proxy = $proxy ?? new ProxyClient($baseUrl);
        $this->applier = $applier ?? new StagingApplier($this->projectRoot);
        $this->migrations = $migrations ?? new MigrationsRunner($db, $this->projectRoot.'/updater/migrations');
    }

    // ─── Public API ─────────────────────────────────────────────────────

    public function getCurrentVersion(): ?string
    {
        $f = $this->projectRoot.'/.version';
        if (! is_file($f)) return null;
        $v = trim((string) file_get_contents($f));
        return preg_match('/^[a-f0-9]{40}$/', $v) ? $v : null;
    }

    public function saveCurrentVersion(string $sha): void
    {
        if (! preg_match('/^[a-f0-9]{40}$/', $sha)) {
            throw new \InvalidArgumentException('Ungueltige SHA (erwartet 40 hex chars): '.$sha);
        }
        file_put_contents($this->projectRoot.'/.version', $sha);
    }

    public function channel(): string
    {
        return $this->channel;
    }

    /** @return array<string, mixed> */
    public function checkForUpdates(): array
    {
        $current = $this->getCurrentVersion() ?? str_repeat('0', 40);
        $info = $this->proxy->check($current);
        $info['current_sha'] = $current;
        $info['channel'] = $this->channel;
        return $info;
    }

    public function maintenanceOn(): void
    {
        file_put_contents($this->projectRoot.'/.maintenance', 'updater@'.now()->toIso8601String());
    }

    public function maintenanceOff(): void
    {
        @unlink($this->projectRoot.'/.maintenance');
    }

    public function isInMaintenance(): bool
    {
        return is_file($this->projectRoot.'/.maintenance');
    }

    /** @return array<string, mixed>|null */
    public function getProgress(): ?array
    {
        $f = $this->projectRoot.'/.update-progress';
        if (! is_file($f)) return null;
        $j = json_decode((string) file_get_contents($f), true);
        return is_array($j) ? $j : null;
    }

    /**
     * Loescht das Progress-File. Wird nach Auto-Reload aufgerufen, damit
     * der 'done'/'error'-Status nicht dauerhaft auf der Seite bleibt.
     */
    public function clearProgress(): void
    {
        @unlink($this->projectRoot.'/.update-progress');
    }

    public function installUpdate(?int $userId = null): array
    {
        $this->setProgress('start', 'Update beginnt', 0);
        $this->maintenanceOn();

        try {
            $this->setProgress('check', 'Hole Ziel-Version vom Proxy', 5);
            $latest = $this->proxy->version();
            $latestSha = (string) ($latest['sha'] ?? '');
            if (! preg_match('/^[a-f0-9]{40}$/', $latestSha)) {
                throw new \RuntimeException('Proxy lieferte keine gueltige SHA.');
            }

            $stagingDir = $this->projectRoot.'/.update-staging';
            $this->applier->resetStaging($stagingDir);

            $zipPath = $this->projectRoot.'/.update-staging.zip';
            $this->setProgress('download', "Lade ZIP fuer {$latestSha}", 15);

            try {
                $this->proxy->downloadZip($latestSha, $zipPath);
            } catch (\Throwable $e) {
                // Fallback: Einzeldateien — fuer kleine Updates oder wenn
                // der Proxy zip-Endpoint gerade haengt. Reicht nur fuer
                // einfache File-Listings; bei groesseren Updates faellt
                // der gesamte Versuch aus.
                $this->setProgress('download', 'ZIP-Fallback: Einzeldateien ('.$e->getMessage().')', 18);
                $this->applier->fetchFilesIndividually($this->proxy, $stagingDir);
                $zipPath = null;
            }

            if ($zipPath !== null) {
                $this->setProgress('extract', 'Entpacke ZIP', 35);
                $this->applier->extractZip($zipPath, $stagingDir);
                @unlink($zipPath);
            }

            $this->setProgress('apply', 'Schreibe neue Dateien (geschuetzte Pfade bleiben)', 60);
            $copied = $this->applier->applyToProduction($stagingDir);

            $this->setProgress('migrate', 'Wende Migrationen an', 80);
            $applied = $this->migrations->migrate();

            $this->setProgress('cleanup', 'Cache invalidieren', 92);
            $this->invalidateCaches();

            $this->saveCurrentVersion($latestSha);
            $this->setProgress('done', "Auf {$latestSha} aktualisiert ({$copied} Dateien, {$applied} Migrationen)", 100);

            $this->logAudit('updater.installed', [
                'from' => $this->getCurrentVersion(),
                'to' => $latestSha,
                'files_copied' => $copied,
                'migrations_applied' => $applied,
                'channel' => $this->channel,
            ], "Update auf {$latestSha} eingespielt", $userId);

            return [
                'ok' => true,
                'sha' => $latestSha,
                'files_copied' => $copied,
                'migrations_applied' => $applied,
            ];
        } catch (\Throwable $e) {
            $this->setProgress('error', 'Fehler: '.$e->getMessage(), 0);
            $this->logAudit('updater.failed', [
                'error' => $e->getMessage(),
                'channel' => $this->channel,
            ], 'Update fehlgeschlagen: '.$e->getMessage(), $userId);
            throw $e;
        } finally {
            $this->maintenanceOff();
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function setProgress(string $step, string $message, int $percent): void
    {
        file_put_contents($this->projectRoot.'/.update-progress', json_encode([
            'step' => $step,
            'message' => $message,
            'percent' => $percent,
            'at' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function invalidateCaches(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        // Laravel-Caches (best-effort, schlucken wenn Artisan nicht da)
        @shell_exec('php '.escapeshellarg($this->projectRoot.'/artisan').' view:clear 2>&1');
        @shell_exec('php '.escapeshellarg($this->projectRoot.'/artisan').' config:clear 2>&1');
        @shell_exec('php '.escapeshellarg($this->projectRoot.'/artisan').' route:clear 2>&1');
    }

    private function logAudit(string $event, array $data, string $description, ?int $userId): void
    {
        if (! $this->audit) return;
        if (! method_exists($this->audit, 'log')) return;
        try {
            $this->audit->log($event, null, null, $data, $description, $userId);
        } catch (\Throwable) {
            // Audit darf nie das Update zum Scheitern bringen
        }
    }
}
