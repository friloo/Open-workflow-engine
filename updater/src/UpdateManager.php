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

    /**
     * Fuehrt 'php artisan migrate --force' programmatisch ueber die
     * Artisan-Facade aus. Sicher gegen Shell-Quoting und ohne SSH.
     *
     * @return int Anzahl ausgefuehrter Migrationen (best-effort aus Output)
     */
    public function runLaravelMigrations(): int
    {
        if (! class_exists(\Illuminate\Support\Facades\Artisan::class)) return 0;
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            // Laravel printet pro Migration eine Zeile wie 'XXXX...DONE'
            return substr_count($output, 'DONE');
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Liste der noch ausstehenden Laravel-Migrationen (best-effort via
     * 'migrate:status' Parsing).
     *
     * @return array{applied: array<int,string>, pending: array<int,string>}
     */
    public function laravelMigrationStatus(): array
    {
        $applied = [];
        $pending = [];
        if (! class_exists(\Illuminate\Support\Facades\Artisan::class)) {
            return ['applied' => $applied, 'pending' => $pending];
        }
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate:status');
            $output = \Illuminate\Support\Facades\Artisan::output();
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '-') || str_starts_with($line, '=')) continue;
                // Format ist je nach Laravel-Version unterschiedlich. Heuristik:
                // 'YYYY_MM_DD_xxxxxx_name ......... Ran' oder 'Pending'.
                if (preg_match('/(\d{4}_\d{2}_\d{2}_\d+_[a-z0-9_]+)/i', $line, $m)) {
                    $name = $m[1];
                    $lower = strtolower($line);
                    if (str_contains($lower, 'pending')) {
                        $pending[] = $name;
                    } else {
                        $applied[] = $name;
                    }
                }
            }
        } catch (\Throwable) {
            // egal — Status ist nice-to-have
        }
        return ['applied' => $applied, 'pending' => $pending];
    }

    /**
     * Leert die App-Caches programmatisch. Wird genutzt nach jedem
     * Update + von einem dedizierten 'Caches leeren'-Button im UI.
     *
     * @return array<string, string> command => 'ok'|error
     */
    public function clearAppCaches(): array
    {
        $results = [];
        $cmds = ['view:clear', 'config:clear', 'route:clear', 'cache:clear'];
        if (! class_exists(\Illuminate\Support\Facades\Artisan::class)) {
            return array_fill_keys($cmds, 'artisan-nicht-verfuegbar');
        }
        foreach ($cmds as $cmd) {
            try {
                \Illuminate\Support\Facades\Artisan::call($cmd);
                $results[$cmd] = 'ok';
            } catch (\Throwable $e) {
                $results[$cmd] = $e->getMessage();
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $results['opcache'] = 'ok';
        }
        return $results;
    }

    // ─── Snapshots / Disaster-Recovery ──────────────────────────────────

    /**
     * Pre-Update-Snapshot: ruft den vorhandenen BackupService und merkt
     * den Filename + aktuellen SHA in einer Updater-eigenen Meta-Datei,
     * damit das UI die Snapshots als 'Vor-Update'-markiert auflisten kann.
     *
     * @return string Filename des Snapshots
     */
    public function createPreUpdateSnapshot(?int $userId = null): string
    {
        if (! class_exists(\App\Services\BackupService::class)) {
            throw new \RuntimeException('BackupService nicht verfuegbar — Snapshot uebersprungen.');
        }
        $svc = app(\App\Services\BackupService::class);
        $file = $svc->create($userId);

        // Meta-Eintrag
        $meta = $this->loadSnapshotMeta();
        $meta[$file] = [
            'created_at' => now()->toIso8601String(),
            'from_sha' => $this->getCurrentVersion(),
            'channel' => $this->channel,
            'by_user_id' => $userId,
        ];
        $this->saveSnapshotMeta($meta);

        $this->logAudit('updater.snapshot_created', [
            'file' => $file,
            'from_sha' => $this->getCurrentVersion(),
        ], "Pre-Update-Snapshot erstellt: {$file}", $userId);

        return $file;
    }

    /**
     * Liste aller Snapshots (auch nicht-Updater-Backups), markiert die
     * Updater-eigenen via 'pre_update' = true.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSnapshots(): array
    {
        if (! class_exists(\App\Services\BackupService::class)) return [];
        $svc = app(\App\Services\BackupService::class);
        $meta = $this->loadSnapshotMeta();
        $out = [];
        foreach ($svc->list() as $b) {
            $info = $meta[$b['file']] ?? null;
            $out[] = [
                'file' => $b['file'],
                'size' => $b['size'],
                'created_at' => $b['created_at'],
                'pre_update' => $info !== null,
                'from_sha' => $info['from_sha'] ?? null,
                'channel' => $info['channel'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * Restore aus einem Snapshot. Wartungsmodus ist im BackupService::restore()
     * selbst aktiv waehrend des Restores; wir setzen ihn vorher zusaetzlich
     * fuer den Audit-Pfad.
     *
     * @return array<string, mixed>
     */
    public function restoreSnapshot(string $filename, ?int $userId = null): array
    {
        if (! class_exists(\App\Services\BackupService::class)) {
            throw new \RuntimeException('BackupService nicht verfuegbar.');
        }
        $svc = app(\App\Services\BackupService::class);

        $this->setProgress('restore', "Restore aus Snapshot {$filename}", 30);
        $this->maintenanceOn();
        try {
            $result = $svc->restore($filename);
            $this->setProgress('done', 'Restore abgeschlossen', 100);
            $this->logAudit('updater.snapshot_restored', [
                'file' => $filename,
            ], "Snapshot {$filename} wiederhergestellt", $userId);
            return ['ok' => true, 'data' => $result];
        } catch (\Throwable $e) {
            $this->setProgress('error', 'Restore fehlgeschlagen: '.$e->getMessage(), 0);
            $this->logAudit('updater.snapshot_restore_failed', [
                'file' => $filename, 'error' => $e->getMessage(),
            ], "Snapshot-Restore {$filename} fehlgeschlagen", $userId);
            throw $e;
        } finally {
            $this->maintenanceOff();
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function loadSnapshotMeta(): array
    {
        $f = $this->projectRoot.'/.updater-snapshots.json';
        if (! is_file($f)) return [];
        $j = json_decode((string) file_get_contents($f), true);
        return is_array($j) ? $j : [];
    }

    private function saveSnapshotMeta(array $meta): void
    {
        file_put_contents(
            $this->projectRoot.'/.updater-snapshots.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    public function installUpdate(?int $userId = null): array
    {
        $this->setProgress('start', 'Update beginnt', 0);
        $this->maintenanceOn();

        $snapshotFile = null;
        try {
            // Vor dem Apply: Pre-Update-Snapshot ziehen, damit ein Restore
            // moeglich ist falls das Update kaputt geht.
            $this->setProgress('snapshot', 'Erstelle Pre-Update-Snapshot', 3);
            try {
                $snapshotFile = $this->createPreUpdateSnapshot($userId);
            } catch (\Throwable $e) {
                // Snapshot ist nice-to-have. Wenn er scheitert (z.B.
                // mysqldump fehlt) machen wir trotzdem weiter, loggen das.
                $this->logAudit('updater.snapshot_failed', ['error' => $e->getMessage()],
                    'Pre-Update-Snapshot fehlgeschlagen — Update laeuft ohne Restore-Punkt weiter', $userId);
            }

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

            $this->setProgress('migrate', 'Wende Updater-Migrationen an', 75);
            $applied = $this->migrations->migrate();

            $this->setProgress('migrate-app', 'Wende App-Migrationen an (php artisan migrate)', 85);
            $appMigrations = $this->runLaravelMigrations();

            $this->setProgress('cleanup', 'Cache invalidieren', 92);
            $this->invalidateCaches();

            $this->saveCurrentVersion($latestSha);
            $this->setProgress('done', "Auf {$latestSha} aktualisiert ({$copied} Dateien, {$applied} Updater- + {$appMigrations} App-Migrationen)", 100);

            $this->logAudit('updater.installed', [
                'from' => $this->getCurrentVersion(),
                'to' => $latestSha,
                'files_copied' => $copied,
                'migrations_applied' => $applied,
                'app_migrations_applied' => $appMigrations,
                'channel' => $this->channel,
            ], "Update auf {$latestSha} eingespielt", $userId);

            return [
                'ok' => true,
                'sha' => $latestSha,
                'files_copied' => $copied,
                'migrations_applied' => $applied,
                'app_migrations_applied' => $appMigrations,
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
        // Delegiert an die programmatische Variante — keine shell_exec,
        // die in disabled_functions-Setups still scheitern wuerde.
        $this->clearAppCaches();
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
