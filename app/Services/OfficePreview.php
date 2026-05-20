<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Konvertiert Office-Dateien (DOCX/XLSX/PPTX/ODT/ODS/ODP) per
 * 'libreoffice --headless --convert-to pdf' in PDF, damit sie im
 * Iframe-Preview angezeigt werden koennen.
 *
 * Cache: das konvertierte PDF wird in storage/app/preview-cache/
 * unter dem SHA-256-Hash des Originals abgelegt. Zweite Anfrage:
 * sofortiger Cache-Hit.
 *
 * Wenn libreoffice nicht installiert ist → null. Caller faellt
 * auf eine Hinweis-Seite zurueck. Das System bleibt ohne LibreOffice
 * voll funktionsfaehig — nur Office-Files koennen dann nicht inline
 * angezeigt werden.
 */
class OfficePreview
{
    private const OFFICE_MIMES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/rtf',
        'text/rtf',
    ];

    public static function isOfficeAttachment(Attachment $att): bool
    {
        if (in_array($att->mime_type, self::OFFICE_MIMES, true)) return true;
        $ext = strtolower(pathinfo($att->original_name, PATHINFO_EXTENSION));
        return in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'], true);
    }

    /**
     * Liefert true wenn LibreOffice auf dem Server gefunden wurde.
     * Cached fuer die Dauer des Requests.
     */
    public static function isAvailable(): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        if (! (bool) config('app.libreoffice_preview', true)) {
            return $available = false;
        }
        $bin = self::binary();
        if (! $bin) return $available = false;
        return $available = is_executable($bin);
    }

    /**
     * Liefert den Pfad zum konvertierten PDF (im Cache). Null wenn
     * Conversion fehlschlaegt oder LibreOffice fehlt.
     */
    public function convertToPdf(Attachment $att): ?string
    {
        if (! self::isAvailable()) return null;

        $cacheRel = 'preview-cache/'.$att->content_hash.'.pdf';
        $cacheAbs = storage_path('app/'.$cacheRel);

        if (is_file($cacheAbs) && filesize($cacheAbs) > 0) {
            return $cacheAbs;
        }

        // Original-Datei in einen Temp-Pfad ziehen (S3-safe)
        $srcDisk = Storage::disk($att->disk);
        if (! $srcDisk->exists($att->path)) return null;

        $tmpDir = sys_get_temp_dir().'/owe-libreoffice-'.bin2hex(random_bytes(6));
        @mkdir($tmpDir, 0755, true);
        $ext = strtolower(pathinfo($att->original_name, PATHINFO_EXTENSION) ?: 'bin');
        $tmpIn = $tmpDir.'/source.'.$ext;
        file_put_contents($tmpIn, $srcDisk->get($att->path));

        try {
            $bin = self::binary();
            $proc = new Process([
                $bin, '--headless', '--norestore', '--nolockcheck', '--nodefault',
                '--nofirststartwizard',
                '--convert-to', 'pdf', '--outdir', $tmpDir, $tmpIn,
            ]);
            $proc->setTimeout(60);
            $proc->run();
            if (! $proc->isSuccessful()) {
                return null;
            }
            $tmpOut = $tmpDir.'/source.pdf';
            if (! is_file($tmpOut)) return null;

            @mkdir(dirname($cacheAbs), 0755, true);
            @rename($tmpOut, $cacheAbs);
            return is_file($cacheAbs) ? $cacheAbs : null;
        } catch (\Throwable) {
            return null;
        } finally {
            // Temp wegraeumen
            if (is_dir($tmpDir)) {
                foreach (glob($tmpDir.'/*') as $f) @unlink($f);
                @rmdir($tmpDir);
            }
        }
    }

    public static function clearCache(?Attachment $att = null): int
    {
        if ($att) {
            $path = storage_path('app/preview-cache/'.$att->content_hash.'.pdf');
            return @unlink($path) ? 1 : 0;
        }
        $count = 0;
        foreach (glob(storage_path('app/preview-cache/*.pdf')) ?: [] as $f) {
            if (@unlink($f)) $count++;
        }
        return $count;
    }

    private static function binary(): ?string
    {
        $configured = config('app.libreoffice_bin');
        if ($configured && is_executable($configured)) return $configured;
        foreach (['/usr/bin/libreoffice', '/usr/bin/soffice', '/opt/libreoffice/program/soffice', '/Applications/LibreOffice.app/Contents/MacOS/soffice'] as $candidate) {
            if (is_executable($candidate)) return $candidate;
        }
        // PATH-Suche
        $which = trim((string) @shell_exec('command -v libreoffice 2>/dev/null'));
        if ($which && is_executable($which)) return $which;
        $which = trim((string) @shell_exec('command -v soffice 2>/dev/null'));
        if ($which && is_executable($which)) return $which;
        return null;
    }
}
