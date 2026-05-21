<?php

namespace Updater;

/**
 * String- und kommentar-bewusster SQL-Splitter. Trennt Statements an
 * ';' am Statement-Ende, beachtet dabei:
 *  - einfache und doppelte Quotes (mit Escape-Handling)
 *  - Backticks fuer MySQL-Identifier
 *  - Line-Kommentare `-- ...` und `# ...`
 *  - Block-Kommentare /* ... \*\/
 *
 * Keine $$/BEGIN END-Unterstuetzung (PostgreSQL/Procedural). Reicht
 * fuer Standard-Migrations-DDL.
 *
 * @return array<int, string>
 */
final class SqlSplitter
{
    /** @return array<int, string> */
    public static function split(string $sql): array
    {
        $out = [];
        $buf = '';
        $i = 0;
        $n = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLine = false;
        $inHash = false;
        $inBlock = false;

        while ($i < $n) {
            $c = $sql[$i];
            $next = $i + 1 < $n ? $sql[$i + 1] : '';

            if ($inLine) {
                $buf .= $c;
                if ($c === "\n") $inLine = false;
                $i++; continue;
            }
            if ($inHash) {
                $buf .= $c;
                if ($c === "\n") $inHash = false;
                $i++; continue;
            }
            if ($inBlock) {
                $buf .= $c;
                if ($c === '*' && $next === '/') {
                    $buf .= $next;
                    $i += 2;
                    $inBlock = false;
                    continue;
                }
                $i++; continue;
            }
            if ($inSingle) {
                $buf .= $c;
                if ($c === '\\' && $next !== '') {
                    $buf .= $next;
                    $i += 2;
                    continue;
                }
                if ($c === "'") $inSingle = false;
                $i++; continue;
            }
            if ($inDouble) {
                $buf .= $c;
                if ($c === '\\' && $next !== '') {
                    $buf .= $next;
                    $i += 2;
                    continue;
                }
                if ($c === '"') $inDouble = false;
                $i++; continue;
            }
            if ($inBacktick) {
                $buf .= $c;
                if ($c === '`') $inBacktick = false;
                $i++; continue;
            }

            // Comment starts
            if ($c === '-' && $next === '-') {
                $buf .= $c.$next; $i += 2; $inLine = true; continue;
            }
            if ($c === '#') {
                $buf .= $c; $i++; $inHash = true; continue;
            }
            if ($c === '/' && $next === '*') {
                $buf .= $c.$next; $i += 2; $inBlock = true; continue;
            }
            // Strings start
            if ($c === "'") { $buf .= $c; $i++; $inSingle = true; continue; }
            if ($c === '"') { $buf .= $c; $i++; $inDouble = true; continue; }
            if ($c === '`') { $buf .= $c; $i++; $inBacktick = true; continue; }

            if ($c === ';') {
                $stmt = trim($buf);
                if ($stmt !== '') $out[] = $stmt;
                $buf = '';
                $i++; continue;
            }

            $buf .= $c;
            $i++;
        }
        $tail = trim($buf);
        if ($tail !== '') $out[] = $tail;
        return $out;
    }

    /**
     * Manche DB-Fehler sind beim Re-Run einer (teils) angewendeten
     * Migration unkritisch: "table exists", "column exists", "duplicate
     * index". Treiber-spezifisch unterschiedliche Meldungen.
     */
    public static function isIgnorableSqlError(string $message, string $driver): bool
    {
        $m = strtolower($message);
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return str_contains($m, 'already exists')
                || str_contains($m, 'duplicate column name')
                || str_contains($m, 'duplicate key name')
                || str_contains($m, "can't drop");
        }
        if ($driver === 'sqlite') {
            return str_contains($m, 'already exists')
                || str_contains($m, 'duplicate column name')
                || str_contains($m, 'no such');
        }
        return false;
    }
}
