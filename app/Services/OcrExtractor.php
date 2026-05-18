<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Extrahiert Text aus hochgeladenen Dokumenten fuer die Volltextsuche.
 *
 * Reihenfolge:
 *  - PDFs mit eingebettetem Text: `pdftotext` (poppler-utils)
 *  - PDF-Scans ohne Text: `pdftoppm` + `tesseract`
 *  - Bilder direkt: `tesseract`
 *  - Andere Dateien: skip
 *
 * Sind die Tools nicht installiert, wird die Datei mit Status "skipped"
 * markiert. Der Workflow bricht nie ab.
 */
class OcrExtractor
{
    private const MAX_BYTES = 25 * 1024 * 1024; // 25 MB
    private const MAX_TEXT = 2 * 1024 * 1024;   // 2 MB Text speichern
    private const TIMEOUT = 60;

    public function extract(Attachment $att): void
    {
        if ($att->size > self::MAX_BYTES) {
            $att->forceFill(['ocr_status' => 'skipped', 'ocr_extracted_at' => now()])->save();
            return;
        }

        $disk = Storage::disk($att->disk);
        if (! $disk->exists($att->path)) {
            $att->forceFill(['ocr_status' => 'failed', 'ocr_extracted_at' => now()])->save();
            return;
        }

        $absolute = $disk->path($att->path);
        $mime = $att->mime_type ?? '';

        try {
            [$tool, $text] = match (true) {
                $mime === 'application/pdf' => $this->extractPdf($absolute),
                str_starts_with($mime, 'image/') => $this->extractImage($absolute),
                default => [null, null],
            };

            if ($text === null) {
                $att->forceFill(['ocr_status' => 'skipped', 'ocr_extracted_at' => now()])->save();
                return;
            }

            $text = $this->normalize($text);
            $att->forceFill([
                'ocr_text' => substr($text, 0, self::MAX_TEXT),
                'ocr_status' => $text === '' ? 'skipped' : 'done',
                'ocr_extracted_at' => now(),
                'ocr_tool' => $tool,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('OCR fehlgeschlagen', ['attachment' => $att->id, 'error' => $e->getMessage()]);
            $att->forceFill(['ocr_status' => 'failed', 'ocr_extracted_at' => now()])->save();
        }
    }

    /** @return array{0:?string, 1:?string} */
    private function extractPdf(string $absolute): array
    {
        if ($this->hasTool('pdftotext')) {
            $text = $this->run(['pdftotext', '-layout', '-nopgbrk', $absolute, '-']);
            if ($text !== null && trim($text) !== '') {
                return ['pdftotext', $text];
            }
        }

        // Scan-PDF: erst in Bilder konvertieren, dann tesseract drueberlaufen
        if ($this->hasTool('pdftoppm') && $this->hasTool('tesseract')) {
            $tmp = sys_get_temp_dir().'/ocr_'.uniqid();
            @mkdir($tmp);
            try {
                $this->run(['pdftoppm', '-r', '200', '-png', $absolute, $tmp.'/page']);
                $text = '';
                foreach (glob($tmp.'/page-*.png') ?: [] as $img) {
                    $part = $this->run(['tesseract', $img, 'stdout', '-l', $this->tessLang()]);
                    if ($part) $text .= "\n".$part;
                }
                return ['tesseract', $text];
            } finally {
                array_map('unlink', glob($tmp.'/*') ?: []);
                @rmdir($tmp);
            }
        }

        return [null, null];
    }

    /** @return array{0:?string, 1:?string} */
    private function extractImage(string $absolute): array
    {
        if (! $this->hasTool('tesseract')) return [null, null];
        $text = $this->run(['tesseract', $absolute, 'stdout', '-l', $this->tessLang()]);
        return ['tesseract', $text ?? ''];
    }

    public function availability(): array
    {
        return [
            'pdftotext' => $this->hasTool('pdftotext'),
            'pdftoppm' => $this->hasTool('pdftoppm'),
            'tesseract' => $this->hasTool('tesseract'),
        ];
    }

    private function hasTool(string $bin): bool
    {
        static $cache = [];
        if (isset($cache[$bin])) return $cache[$bin];
        $p = new Process(['which', $bin]);
        $p->run();
        return $cache[$bin] = $p->isSuccessful();
    }

    private function run(array $cmd): ?string
    {
        $p = new Process($cmd);
        $p->setTimeout(self::TIMEOUT);
        try {
            $p->run();
        } catch (\Throwable) {
            return null;
        }
        if (! $p->isSuccessful()) return null;
        return $p->getOutput();
    }

    private function normalize(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private function tessLang(): string
    {
        return (string) (\App\Support\Settings::get('ocr.tesseract_lang') ?: 'deu+eng');
    }
}
