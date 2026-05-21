<?php

namespace Updater;

use Illuminate\Database\ConnectionInterface;

/**
 * Eigenstaendiges Migrations-System fuer den Updater. Bewusst getrennt
 * von Laravels database/migrations/ (das bleibt fuer App-Schema-
 * Aenderungen reserviert). Tracking-Tabelle '_updater_migrations',
 * Praefix vermeidet Kollision mit irgendeiner '_migrations' im Projekt.
 *
 * 60-Sekunden-Lockfile-Cache verhindert, dass die Tabelle bei jedem
 * Request erneut gefuellt wird.
 *
 * SQL-Dateien liegen in $migrationsDir und werden alphabetisch
 * sortiert ausgefuehrt.
 */
final class MigrationsRunner
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $migrationsDir,
    ) {}

    /**
     * Wendet alle ausstehenden Migrationen an. Liefert die Anzahl neu
     * angewendeter Files.
     */
    public function migrate(): int
    {
        if (! is_dir($this->migrationsDir)) return 0;

        $this->ensureTrackingTable();
        $applied = $this->getApplied();
        $files = $this->listMigrations();

        $count = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) continue;
            $this->applyOne($file, $name);
            $count++;
        }
        $this->touchCache();
        return $count;
    }

    public function status(): array
    {
        $this->ensureTrackingTable();
        $applied = $this->getApplied();
        $all = array_map('basename', $this->listMigrations());
        $pending = array_values(array_diff($all, $applied));
        return [
            'applied' => array_values($applied),
            'pending' => $pending,
            'total_applied' => count($applied),
            'total_pending' => count($pending),
        ];
    }

    public function cacheIsFresh(): bool
    {
        $f = sys_get_temp_dir().'/owe-updater-migrate.lock';
        return is_file($f) && (time() - filemtime($f) < 60);
    }

    private function touchCache(): void
    {
        @touch(sys_get_temp_dir().'/owe-updater-migrate.lock');
    }

    private function driver(): string
    {
        return strtolower($this->db->getDriverName());
    }

    /** @return array<int, string> */
    private function listMigrations(): array
    {
        $files = glob(rtrim($this->migrationsDir, '/').'/*.sql') ?: [];
        sort($files);
        return $files;
    }

    /** @return array<int, string> */
    private function getApplied(): array
    {
        $rows = $this->db->select('SELECT filename FROM _updater_migrations ORDER BY id');
        return array_map(fn ($r) => is_object($r) ? $r->filename : $r['filename'], $rows);
    }

    private function ensureTrackingTable(): void
    {
        $driver = $this->driver();
        if ($driver === 'sqlite') {
            $sql = 'CREATE TABLE IF NOT EXISTS "_updater_migrations" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "filename" TEXT NOT NULL UNIQUE,
                "applied_at" DATETIME DEFAULT CURRENT_TIMESTAMP
            )';
        } else {
            $sql = 'CREATE TABLE IF NOT EXISTS `_updater_migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `filename` VARCHAR(255) NOT NULL UNIQUE,
                `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        }
        $this->db->statement($sql);
    }

    private function applyOne(string $path, string $name): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException("Migration konnte nicht gelesen werden: {$path}");
        }

        $driver = $this->driver();
        $statements = SqlSplitter::split($sql);
        foreach ($statements as $stmt) {
            try {
                $this->db->statement($stmt);
            } catch (\Throwable $e) {
                if (SqlSplitter::isIgnorableSqlError($e->getMessage(), $driver)) {
                    continue;
                }
                throw new \RuntimeException("Migration {$name} fehlgeschlagen bei Statement: ".substr($stmt, 0, 120).' — '.$e->getMessage(), 0, $e);
            }
        }

        $insert = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO _updater_migrations (filename) VALUES (?)'
            : 'INSERT IGNORE INTO _updater_migrations (filename) VALUES (?)';
        $this->db->statement($insert, [$name]);
    }
}
