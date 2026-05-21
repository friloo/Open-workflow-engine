<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

/**
 * Stempelt eine vorhandene PDF mit einem rechteckigen Approval-Stempel
 * unten rechts auf der letzten Seite. Erzeugt eine NEUE PDF-Datei
 * (Original bleibt unverändert) und liefert die Bytes zurück.
 *
 * Erwartete Stempel-Daten:
 *   [
 *       'title'    => 'Genehmigt',          // große Überschrift
 *       'lines'    => [                     // einzelne Zeilen im Stempel
 *           'von Max Mustermann',
 *           'am 15.05.2026 14:32',
 *           'Workflow: Rechnungseingang #42',
 *       ],
 *       'color'    => 'emerald',            // emerald|rose|amber|slate
 *   ]
 *
 * Der Stempel ist serifenlos, der Rahmen leicht abgerundet (simuliert
 * via Strichstärke). Wird absichtlich nicht super-realistisch wie ein
 * Gummistempel gemacht — soll als digitaler Audit-Marker erkennbar sein.
 */
class PdfStamper
{
    /** @var array<string, array{r:int,g:int,b:int}> */
    private const PALETTE = [
        'emerald' => ['r' => 5, 'g' => 150, 'b' => 105],
        'rose' => ['r' => 220, 'g' => 38, 'b' => 38],
        'amber' => ['r' => 217, 'g' => 119, 'b' => 6],
        'slate' => ['r' => 71, 'g' => 85, 'b' => 105],
    ];

    public function stamp(string $pdfBytes, array $data): string
    {
        $title = (string) ($data['title'] ?? 'Genehmigt');
        $lines = array_values(array_filter(array_map('strval', $data['lines'] ?? [])));
        $color = self::PALETTE[$data['color'] ?? 'emerald'] ?? self::PALETTE['emerald'];

        // FPDI verlangt eine temporäre Datei zum Importieren — der
        // FpdfTcpdfParser kennt keine Speicher-Streams direkt.
        $tmpIn = tempnam(sys_get_temp_dir(), 'owe-pdf-in-');
        file_put_contents($tmpIn, $pdfBytes);

        try {
            $pdf = new Fpdi();
            $pdf->setSourceFile($tmpIn);
            $totalPages = $pdf->setSourceFile($tmpIn); // returns pageCount

            for ($p = 1; $p <= $totalPages; $p++) {
                $tplId = $pdf->importPage($p);
                $size = $pdf->getTemplateSize($tplId);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                // Stempel nur auf letzter Seite
                if ($p === $totalPages) {
                    $this->drawStamp($pdf, $size['width'], $size['height'], $title, $lines, $color);
                }
            }

            return $pdf->Output('S');
        } finally {
            @unlink($tmpIn);
        }
    }

    /**
     * Zeichnet einen Stempel-Kasten unten rechts auf die aktuelle Seite.
     */
    private function drawStamp(Fpdi $pdf, float $pageW, float $pageH, string $title, array $lines, array $color): void
    {
        // Box: 70mm breit, dynamisch hoch je nach Zeilen
        $boxW = 70.0;
        $titleH = 8.0;
        $lineH = 4.5;
        $padding = 4.0;
        $boxH = $padding * 2 + $titleH + count($lines) * $lineH;

        $marginRight = 12.0;
        $marginBottom = 12.0;
        $x = $pageW - $boxW - $marginRight;
        $y = $pageH - $boxH - $marginBottom;

        // Rahmen
        $pdf->SetDrawColor($color['r'], $color['g'], $color['b']);
        $pdf->SetLineWidth(0.6);
        $pdf->Rect($x, $y, $boxW, $boxH);
        // Innerer Rahmen für "Stempel"-Effekt
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x + 1.2, $y + 1.2, $boxW - 2.4, $boxH - 2.4);

        // Titel
        $pdf->SetTextColor($color['r'], $color['g'], $color['b']);
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetXY($x + $padding, $y + $padding - 1);
        // FPDF kennt kein UTF-8 - wir konvertieren in ISO-8859-1 mit Fallback
        $pdf->Cell($boxW - 2 * $padding, $titleH, self::toLatin1($title), 0, 1, 'C');

        // Zeilen
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $cursorY = $y + $padding + $titleH + 1;
        foreach ($lines as $line) {
            $pdf->SetXY($x + $padding, $cursorY);
            $pdf->Cell($boxW - 2 * $padding, $lineH - 0.5, self::toLatin1($line), 0, 0, 'L');
            $cursorY += $lineH;
        }
    }

    /**
     * FPDF/FPDI verlangt Latin-1 — alles ausserhalb wird durch '?' ersetzt.
     * Deutsche Umlaute funktionieren so problemlos.
     */
    private static function toLatin1(string $s): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        return $converted !== false ? $converted : $s;
    }
}
