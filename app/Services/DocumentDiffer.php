<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Vergleicht zwei Dokument-Versionen Text-für-Text.
 *
 * Extrahiert Text aus PDF/TXT/CSV, normalisiert auf Absätze und liefert
 * eine Liste von Hunks (alt / neu / Gleich) zurück, mit der eine Side-by-
 * Side- oder Inline-Ansicht gerendert werden kann.
 *
 * Office-Dokumente werden – falls LibreOffice verfügbar – on-the-fly nach
 * PDF konvertiert. Andernfalls liefert die Methode einen Fehler-Hint
 * statt einer Fake-Vergleich-Ansicht.
 */
class DocumentDiffer
{
    /**
     * @return array{
     *   supported: bool,
     *   reason?: string,
     *   left_label: string,
     *   right_label: string,
     *   left_text?: string,
     *   right_text?: string,
     *   hunks?: array<int, array{type: string, left: string, right: string, line_left?: int, line_right?: int}>,
     *   stats?: array{added: int, removed: int, unchanged: int}
     * }
     */
    public function diff(Attachment $left, Attachment $right): array
    {
        if ($left->version_chain_id !== $right->version_chain_id) {
            return [
                'supported' => false,
                'reason' => 'Die beiden Dokumente gehören nicht zur gleichen Versionskette.',
                'left_label' => $this->label($left),
                'right_label' => $this->label($right),
            ];
        }

        $leftText = $this->extractText($left);
        $rightText = $this->extractText($right);

        if ($leftText === null || $rightText === null) {
            return [
                'supported' => false,
                'reason' => 'Mindestens eines der Dokumente liefert keinen extrahierbaren Text (Bilder, gescannte PDFs ohne OCR oder unsupported Format).',
                'left_label' => $this->label($left),
                'right_label' => $this->label($right),
            ];
        }

        $hunks = $this->buildHunks($leftText, $rightText);
        $stats = $this->stats($hunks);

        return [
            'supported' => true,
            'left_label' => $this->label($left),
            'right_label' => $this->label($right),
            'left_text' => $leftText,
            'right_text' => $rightText,
            'hunks' => $hunks,
            'stats' => $stats,
        ];
    }

    private function label(Attachment $a): string
    {
        return 'v'.$a->version_number.' · '.optional($a->created_at)->format('d.m.Y H:i');
    }

    private function extractText(Attachment $a): ?string
    {
        try {
            $bytes = Storage::disk($a->disk)->get($a->path);
        } catch (\Throwable) {
            return null;
        }
        if ($bytes === null || $bytes === '') return null;

        $mime = (string) $a->mime_type;

        if ($a->isPdf()) {
            try {
                $text = (new PdfParser())->parseContent($bytes)->getText();
                return $this->normalize($text);
            } catch (\Throwable) {
                return null;
            }
        }

        if (str_starts_with($mime, 'text/') || in_array($mime, ['application/csv', 'application/json', 'application/xml'], true)) {
            return $this->normalize($bytes);
        }

        // Office-Dokumente via LibreOffice → PDF → Text
        if ($a->isOffice() && OfficePreview::isAvailable()) {
            try {
                $pdfPath = app(OfficePreview::class)->convertToPdf($a);
                if (! $pdfPath || ! is_file($pdfPath)) return null;
                $text = (new PdfParser())->parseFile($pdfPath)->getText();
                return $this->normalize($text);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function normalize(string $text): string
    {
        // CRLF → LF, mehrfache Leerzeilen → eine, trim am Anfang/Ende
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /**
     * @return array<int, array{type: string, left: string, right: string, line_left?: int, line_right?: int}>
     */
    private function buildHunks(string $left, string $right): array
    {
        $differ = new Differ(new UnifiedDiffOutputBuilder("--- v_alt\n+++ v_neu\n", false));
        $unified = $differ->diff($left, $right);

        // Aus dem Unified-Diff bauen wir eine Reihe von Hunks mit
        // gleich-Text und (links-removed / rechts-added)-Bloecken.
        $lines = explode("\n", $unified);
        $hunks = [];
        $bufLeft = [];
        $bufRight = [];
        $lineLeft = 0;
        $lineRight = 0;
        $startLeft = 0;
        $startRight = 0;

        $flushChange = function () use (&$hunks, &$bufLeft, &$bufRight, &$startLeft, &$startRight) {
            if (empty($bufLeft) && empty($bufRight)) return;
            $type = (! empty($bufLeft) && ! empty($bufRight)) ? 'change'
                : (! empty($bufLeft) ? 'removed' : 'added');
            $hunks[] = [
                'type' => $type,
                'left' => implode("\n", $bufLeft),
                'right' => implode("\n", $bufRight),
                'line_left' => $startLeft,
                'line_right' => $startRight,
            ];
            $bufLeft = [];
            $bufRight = [];
        };

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '---') || str_starts_with($line, '+++')) {
                continue;
            }
            if (str_starts_with($line, '@@')) {
                $flushChange();
                if (preg_match('/-([0-9]+)(?:,([0-9]+))?\s+\+([0-9]+)/', $line, $m)) {
                    $lineLeft = (int) $m[1];
                    $lineRight = (int) $m[3];
                }
                continue;
            }
            $marker = substr($line, 0, 1);
            $payload = substr($line, 1);
            if ($marker === ' ') {
                if (! empty($bufLeft) || ! empty($bufRight)) $flushChange();
                $hunks[] = [
                    'type' => 'unchanged',
                    'left' => $payload,
                    'right' => $payload,
                    'line_left' => $lineLeft,
                    'line_right' => $lineRight,
                ];
                $lineLeft++; $lineRight++;
            } elseif ($marker === '-') {
                if (empty($bufLeft) && empty($bufRight)) {
                    $startLeft = $lineLeft; $startRight = $lineRight;
                }
                $bufLeft[] = $payload;
                $lineLeft++;
            } elseif ($marker === '+') {
                if (empty($bufLeft) && empty($bufRight)) {
                    $startLeft = $lineLeft; $startRight = $lineRight;
                }
                $bufRight[] = $payload;
                $lineRight++;
            }
        }
        $flushChange();

        return $hunks;
    }

    /** @return array{added: int, removed: int, unchanged: int} */
    private function stats(array $hunks): array
    {
        $added = 0; $removed = 0; $unchanged = 0;
        foreach ($hunks as $h) {
            switch ($h['type']) {
                case 'unchanged': $unchanged++; break;
                case 'added': $added += substr_count($h['right'], "\n") + 1; break;
                case 'removed': $removed += substr_count($h['left'], "\n") + 1; break;
                case 'change':
                    $added += substr_count($h['right'], "\n") + 1;
                    $removed += substr_count($h['left'], "\n") + 1;
                    break;
            }
        }
        return ['added' => $added, 'removed' => $removed, 'unchanged' => $unchanged];
    }
}
