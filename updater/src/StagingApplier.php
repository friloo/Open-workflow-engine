<?php

namespace Updater;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Entpackt ein ZIP nach Staging und kopiert von dort nach Produktion,
 * waehrend PROTECTED_PATHS unangetastet bleiben. Auch ein Einzeldatei-
 * Fallback ueber den ProxyClient ist drin, falls /zip ausfaellt.
 */
final class StagingApplier
{
    /**
     * Pfade, die der Updater NIE ueberschreibt — relativ zum projectRoot.
     * Mit '/' am Ende = Verzeichnis-Prefix (greift fuer alles darunter).
     * Ohne = exakter Datei-Vergleich.
     */
    private const PROTECTED_PATHS = [
        'config/',
        'storage/',
        '.env',
        '.env.example',
        '.git/',
        '.gitignore',
        'public/uploads/',
        'public/build/',     // Vite-Output; wird via npm regeneriert
        'vendor/',
        'composer.lock',
        'node_modules/',
        // SQLite-DB-Dateien — wenn das Projekt mit SQLite faehrt, MUSS
        // die DB-Datei ueberleben. Wir matchen ueber das Suffix.
        '*.sqlite',
        '*.sqlite3',
        '*.db',
        'database/database.sqlite',
        // Updater-eigene Artefakte
        '.version',
        '.maintenance',
        '.update-staging/',
        '.update-staging.zip',
        '.update-progress',
        'updater-settings.json',
        // Bewusst NICHT geschuetzt: 'updater/' — der Updater darf sich
        // selbst aktualisieren.
    ];

    public function __construct(private readonly string $projectRoot) {}

    public function resetStaging(string $stagingDir): void
    {
        if (is_dir($stagingDir)) {
            $this->rrmdir($stagingDir);
        }
        if (! mkdir($stagingDir, 0755, true) && ! is_dir($stagingDir)) {
            throw new \RuntimeException("Kann Staging-Verzeichnis nicht anlegen: {$stagingDir}");
        }
    }

    public function extractZip(string $zipPath, string $stagingDir): void
    {
        if (! extension_loaded('zip')) {
            throw new \RuntimeException('PHP-ZIP-Extension fehlt. Fallback ueber Einzeldateien laeuft separat.');
        }
        $zip = new ZipArchive();
        $rc = $zip->open($zipPath);
        if ($rc !== true) {
            throw new \RuntimeException("ZIP-Open fehlgeschlagen (code {$rc}): {$zipPath}");
        }
        if (! $zip->extractTo($stagingDir)) {
            $zip->close();
            throw new \RuntimeException("ZIP-Extract fehlgeschlagen nach: {$stagingDir}");
        }
        $zip->close();

        // GitHub/GitLab packen oft einen <project>-<sha>/-Wrapper-Ordner
        // in das ZIP. Wenn das Staging exakt ein einzelnes Unterverzeichnis
        // enthaelt, flatten wir um eine Ebene.
        $entries = array_diff(scandir($stagingDir) ?: [], ['.', '..']);
        $entries = array_values($entries);
        if (count($entries) === 1) {
            $sub = $stagingDir.'/'.$entries[0];
            if (is_dir($sub)) {
                $this->moveDirContents($sub, $stagingDir);
                @rmdir($sub);
            }
        }
    }

    public function fetchFilesIndividually(ProxyClient $proxy, string $stagingDir): void
    {
        $this->walkFiles($proxy, '', $stagingDir);
    }

    private function walkFiles(ProxyClient $proxy, string $path, string $stagingDir): void
    {
        foreach ($proxy->files($path) as $entry) {
            $rel = (string) ($entry['path'] ?? '');
            $type = (string) ($entry['type'] ?? '');
            if ($rel === '' || $this->isProtected($rel)) continue;
            if ($type === 'dir') {
                $this->walkFiles($proxy, $rel, $stagingDir);
                continue;
            }
            $bytes = $proxy->download($rel);
            $dest = $stagingDir.'/'.$rel;
            $dir = dirname($dest);
            if (! is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dest, $bytes);
        }
    }

    /**
     * Kopiert alles aus dem Staging in die Produktion, ueberspringt
     * PROTECTED_PATHS. Loescht KEINE Dateien in Produktion, die im
     * Staging fehlen — Update ist additiv-ueberschreibend.
     *
     * @return int Anzahl kopierter Dateien
     */
    public function applyToProduction(string $stagingDir): int
    {
        if (! is_dir($stagingDir)) {
            throw new \RuntimeException("Staging-Verzeichnis fehlt: {$stagingDir}");
        }
        $count = 0;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stagingDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $stagingPrefix = rtrim($stagingDir, '/').'/';
        foreach ($iter as $fileInfo) {
            $rel = substr($fileInfo->getPathname(), strlen($stagingPrefix));
            if ($rel === '' || $this->isProtected($rel)) continue;

            $target = $this->projectRoot.'/'.$rel;
            if ($fileInfo->isDir()) {
                if (! is_dir($target)) @mkdir($target, 0755, true);
                continue;
            }
            $dir = dirname($target);
            if (! is_dir($dir)) @mkdir($dir, 0755, true);
            if (@copy($fileInfo->getPathname(), $target)) {
                $count++;
            }
        }
        return $count;
    }

    public function isProtected(string $rel): bool
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        foreach (self::PROTECTED_PATHS as $pat) {
            if (str_contains($pat, '*')) {
                $regex = '#^'.str_replace('\\*', '[^/]*', preg_quote($pat, '#')).'$#';
                if (preg_match($regex, $rel)) return true;
                continue;
            }
            if (str_ends_with($pat, '/')) {
                if (str_starts_with($rel.'/', $pat)) return true;
                continue;
            }
            if ($rel === $pat) return true;
        }
        return false;
    }

    private function rrmdir(string $dir): void
    {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private function moveDirContents(string $from, string $to): void
    {
        foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $entry) {
            @rename($from.'/'.$entry, $to.'/'.$entry);
        }
    }
}
