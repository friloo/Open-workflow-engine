<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Tagessicherung: ZIP mit DB-Dump + Attachments.
 *
 * Speicherort: storage/app/backups/owe-YYYY-MM-DD_HHMMSS.zip
 *
 * DB-Strategie:
 *   - SQLite:  database/database.sqlite kopieren
 *   - MySQL/MariaDB: mysqldump via Process; faellt sonst auf
 *     PHP-basierten Dump zurueck (fuer Shared Hosting ohne shell-Zugriff)
 *
 * Restore: bewusst CLI-only, mit Maintenance-Flag (kein UI-Knopf).
 */
class BackupService
{
    public const DIR = 'backups';

    public function __construct(private readonly AuditLogger $audit) {}

    public function backupDir(): string
    {
        $path = storage_path('app/'.self::DIR);
        if (! is_dir($path)) @mkdir($path, 0775, true);
        return $path;
    }

    /** @return array<int, array{file:string, size:int, created_at:int}> */
    public function list(): array
    {
        $out = [];
        foreach (glob($this->backupDir().'/*.zip') ?: [] as $file) {
            $out[] = [
                'file' => basename($file),
                'size' => filesize($file) ?: 0,
                'created_at' => filemtime($file) ?: 0,
            ];
        }
        usort($out, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);
        return $out;
    }

    public function create(?int $userId = null): string
    {
        $name = 'owe-'.now()->format('Y-m-d_His').'.zip';
        $zipPath = $this->backupDir().'/'.$name;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("ZIP konnte nicht angelegt werden: {$zipPath}");
        }

        // DB-Dump
        $dump = $this->dumpDatabase();
        $zip->addFromString($dump['filename'], $dump['contents']);

        // Attachments
        $attachmentsDir = storage_path('app/attachments');
        if (is_dir($attachmentsDir)) {
            $base = realpath(storage_path('app'));
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($attachmentsDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $f) {
                if (! $f->isFile()) continue;
                $real = $f->getRealPath();
                $rel = ltrim(str_replace($base, '', $real), DIRECTORY_SEPARATOR);
                $zip->addFile($real, $rel);
            }
        }

        // Metadaten-Manifest
        $zip->addFromString('manifest.json', json_encode([
            'owe_backup' => 1,
            'created_at' => now()->toIso8601String(),
            'driver' => (string) config('database.default'),
            'app_version' => $this->appVersion(),
            'dump_file' => $dump['filename'],
        ], JSON_PRETTY_PRINT));

        $zip->close();
        $this->prune();

        $this->audit->log('backup.created', null, null, [
            'file' => $name, 'size' => filesize($zipPath),
        ], "Backup angelegt: {$name}", $userId);

        return $zipPath;
    }

    public function delete(string $filename, ?int $userId = null): bool
    {
        $safe = basename($filename);
        $path = $this->backupDir().'/'.$safe;
        if (! is_file($path)) return false;
        @unlink($path);
        $this->audit->log('backup.deleted', null, ['file' => $safe], null, "Backup geloescht: {$safe}", $userId);
        return true;
    }

    public function path(string $filename): ?string
    {
        $safe = basename($filename);
        if (! preg_match('/^owe-\d{4}-\d{2}-\d{2}_\d{6}\.zip$/', $safe)) return null;
        $path = $this->backupDir().'/'.$safe;
        return is_file($path) ? $path : null;
    }

    /**
     * Restore aus einer ZIP-Datei.
     * ACHTUNG: ueberschreibt aktuelle DB und Anhaenge.
     */
    public function restore(string $filename): array
    {
        $path = $this->path($filename);
        if (! $path) throw new \RuntimeException('Backup nicht gefunden.');

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \RuntimeException('ZIP konnte nicht geoeffnet werden.');

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ($manifest['owe_backup'] ?? 0) !== 1) {
            $zip->close();
            throw new \RuntimeException('Kein OWE-Backup (manifest.json fehlt).');
        }

        // Maintenance an
        @file_put_contents(base_path('.maintenance'), now()->toIso8601String());

        try {
            // DB ersetzen
            $dumpFile = (string) ($manifest['dump_file'] ?? '');
            $dumpData = $dumpFile ? $zip->getFromName($dumpFile) : false;
            if ($dumpData === false) {
                throw new \RuntimeException('DB-Dump nicht im Archiv.');
            }
            $this->restoreDatabase($dumpData, (string) $manifest['driver']);

            // Attachments ersetzen
            $attachmentsDir = storage_path('app/attachments');
            $this->rrmdir($attachmentsDir);
            @mkdir($attachmentsDir, 0775, true);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';
                if (! str_starts_with($name, 'attachments/')) continue;
                $target = storage_path('app/'.$name);
                @mkdir(dirname($target), 0775, true);
                file_put_contents($target, $zip->getFromIndex($i));
            }
            $zip->close();

            $this->audit->log('backup.restored', null, null, [
                'file' => $filename,
                'manifest' => $manifest,
            ], "Backup wiederhergestellt: {$filename}");

            return ['ok' => true, 'manifest' => $manifest];
        } finally {
            @unlink(base_path('.maintenance'));
        }
    }

    private function dumpDatabase(): array
    {
        $driver = (string) config('database.default');
        $cfg = config("database.connections.{$driver}");

        if (($cfg['driver'] ?? '') === 'sqlite') {
            $src = (string) $cfg['database'];
            if ($src !== ':memory:' && is_file($src)) {
                return ['filename' => 'database.sqlite', 'contents' => (string) file_get_contents($src)];
            }
            // :memory: oder fehlende Datei -> SQL-Text-Dump als Fallback.
            return ['filename' => 'database.sql', 'contents' => $this->phpSqliteDump()];
        }

        if (in_array($cfg['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            try {
                $process = new Process([
                    'mysqldump',
                    '-h', (string) $cfg['host'],
                    '-P', (string) ($cfg['port'] ?? 3306),
                    '-u', (string) $cfg['username'],
                    '-p'.(string) $cfg['password'],
                    '--single-transaction',
                    '--routines',
                    '--triggers',
                    '--no-tablespaces',
                    (string) $cfg['database'],
                ], null, null, null, 600);
                $process->run();
                if ($process->isSuccessful()) {
                    return ['filename' => 'database.sql', 'contents' => $process->getOutput()];
                }
            } catch (\Throwable) {
                // fall through zu PHP-Dump
            }
            return ['filename' => 'database.sql', 'contents' => $this->phpMysqlDump()];
        }

        throw new \RuntimeException("Backup fuer Driver {$cfg['driver']} nicht unterstuetzt.");
    }

    private function phpSqliteDump(): string
    {
        $sql = "-- OWE SQLite-Dump\nPRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n";
        $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        foreach ($tables as $t) {
            $sql .= "DROP TABLE IF EXISTS \"{$t->name}\";\n";
            $sql .= ($t->sql ?? '').";\n";
            DB::table($t->name)->orderBy('rowid')->chunk(500, function ($rows) use (&$sql, $t) {
                foreach ($rows as $row) {
                    $arr = (array) $row;
                    $cols = array_map(fn ($k) => '"'.$k.'"', array_keys($arr));
                    $vals = array_map(function ($v) {
                        if ($v === null) return 'NULL';
                        if (is_int($v) || is_float($v)) return (string) $v;
                        return "'".str_replace("'", "''", (string) $v)."'";
                    }, array_values($arr));
                    $sql .= "INSERT INTO \"{$t->name}\" (".implode(',', $cols).") VALUES (".implode(',', $vals).");\n";
                }
            });
        }
        $sql .= "COMMIT;\n";
        return $sql;
    }

    private function phpMysqlDump(): string
    {
        $sql = "-- OWE PHP-Dump (Fallback)\nSET FOREIGN_KEY_CHECKS=0;\n";
        $tables = collect(DB::select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0])->all();
        foreach ($tables as $t) {
            $create = DB::select("SHOW CREATE TABLE `{$t}`");
            $sql .= "DROP TABLE IF EXISTS `{$t}`;\n";
            $sql .= ($create[0]->{'Create Table'} ?? '').";\n";
            DB::table($t)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use (&$sql, $t) {
                foreach ($rows as $row) {
                    $cols = array_map(fn ($k) => "`{$k}`", array_keys((array) $row));
                    $vals = array_map(function ($v) {
                        if ($v === null) return 'NULL';
                        if (is_bool($v)) return $v ? '1' : '0';
                        if (is_int($v) || is_float($v)) return (string) $v;
                        return "'".addslashes((string) $v)."'";
                    }, array_values((array) $row));
                    $sql .= "INSERT INTO `{$t}` (".implode(',', $cols).") VALUES (".implode(',', $vals).");\n";
                }
            });
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    private function restoreDatabase(string $dump, string $defaultConnection): void
    {
        $cfg = config("database.connections.{$defaultConnection}");

        if (($cfg['driver'] ?? '') === 'sqlite') {
            $dest = (string) $cfg['database'];
            if (! $dest) throw new \RuntimeException('Kein SQLite-Pfad.');
            // Verbindungen schliessen, sonst gibt's Lock-Probleme
            DB::disconnect();
            if (file_put_contents($dest, $dump) === false) {
                throw new \RuntimeException("Konnte SQLite-Datei nicht schreiben: {$dest}");
            }
            return;
        }

        if (in_array($cfg['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            // SQL in Statements zerlegen und ausfuehren
            DB::unprepared($dump);
            return;
        }

        throw new \RuntimeException("Restore fuer Driver {$cfg['driver']} nicht unterstuetzt.");
    }

    private function prune(): void
    {
        $retentionDays = (int) Settings::get('backups.retention_days', 14);
        if ($retentionDays <= 0) return;
        $cutoff = now()->subDays($retentionDays)->timestamp;
        foreach (glob($this->backupDir().'/*.zip') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
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

    private function appVersion(): ?string
    {
        $f = base_path('.version');
        return is_file($f) ? trim((string) file_get_contents($f)) : null;
    }
}
