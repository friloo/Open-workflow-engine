<?php

namespace App\Services;

use App\Models\Attachment;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Exportiert klassifizierte Belege (Standard: Doku-Typ 'Rechnung') als
 * DATEV-Buchungsstapel-CSV plus die Beleg-PDFs in einem ZIP.
 *
 * Der Output ist ein vereinfachter Buchungsstapel im DATEV-Format:
 * - eine Header-Zeile mit den Spalten-Namen
 * - eine Datenzeile pro Beleg
 * - Belege liegen als PDFs im Unterordner 'belege/' im ZIP
 *
 * Mapping Indexfeld -> DATEV-Spalte ist konfigurierbar via Settings
 * (key 'datev.field_map'). Default-Mapping deckt die ueblichen
 * Rechnungs-Felder ab (betrag, datum, rechnungsnummer, kostenstelle).
 *
 * Wichtige Hinweise:
 * - Spalten sind im Standard-DATEV-Buchungsstapel-Format (Semikolon-
 *   getrennt, Zahlen mit Komma-Dezimal, Datum dd.mm.yyyy).
 * - Sachkonto / Gegenkonto / BU-Schluessel kommen aus der Konfig oder
 *   sind hart auf Defaults (70000 Kreditoren / 4400 Aufwand / leer BU).
 * - Wer ein 'EXTF'-Header braucht (Stammdaten-Block in Zeile 1) muss
 *   das im Export-Tool nachruesten; die Daten-Spalten sind passend.
 */
class DatevExporter
{
    public const DEFAULT_FIELD_MAP = [
        'belegdatum' => 'datum',          // Beleg-Datum
        'rechnungsnummer' => 'rechnungsnummer',
        'betrag_brutto' => 'betrag_brutto',
        'betrag_netto' => 'betrag_netto',
        'kostenstelle' => 'kostenstelle',
        'ustid' => 'ustid',
        'iban' => 'iban',
        'lieferant' => 'lieferant',
    ];

    /**
     * @return array{filename:string, path:string, count:int}
     */
    public function export(Carbon $from, Carbon $to, ?string $documentType = 'Rechnung'): array
    {
        $query = Attachment::query()
            ->where('is_current_version', true)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
        if ($documentType) $query->where('document_type', $documentType);

        $belege = $query->orderBy('created_at')->get();
        if ($belege->isEmpty()) {
            throw new \RuntimeException('Keine Belege im gewaehlten Zeitraum / Doku-Typ gefunden.');
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'datev_').'.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP konnte nicht erstellt werden.');
        }

        // CSV als Buchungsstapel
        $csv = $this->buildCsv($belege);
        $zip->addFromString('buchungsstapel.csv', $csv);

        // Belege in Unterordner 'belege/' — laufende Nummer im Dateinamen
        foreach ($belege as $i => $att) {
            try {
                $bytes = Storage::disk($att->disk)->get($att->path);
                $no = str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
                $ext = pathinfo($att->original_name, PATHINFO_EXTENSION) ?: 'pdf';
                $zip->addFromString("belege/{$no}_{$att->id}.{$ext}", $bytes);
            } catch (\Throwable) {
                // Beleg nicht lesbar — ueberspringen
            }
        }

        // README mit Hinweisen ins ZIP
        $zip->addFromString('README.txt', $this->readme($from, $to, $documentType, $belege->count()));
        $zip->close();

        return [
            'filename' => sprintf('DATEV-Export-%s-bis-%s.zip', $from->format('Y-m-d'), $to->format('Y-m-d')),
            'path' => $tmpZip,
            'count' => $belege->count(),
        ];
    }

    /**
     * DATEV-Buchungsstapel als CSV. Spalten in DATEV-Reihenfolge:
     *
     * 1. Umsatz (Soll/Haben-Trennung weiter unten)  → betrag_brutto
     * 2. Soll/Haben-Kennzeichen
     * 3. WKZ Umsatz
     * 4. Kurs
     * 5. Basis-Umsatz
     * 6. WKZ Basis-Umsatz
     * 7. Konto
     * 8. Gegenkonto
     * 9. BU-Schluessel
     * 10. Belegdatum
     * 11. Belegfeld 1 (Rechnungsnummer)
     * 12. Belegfeld 2 (Lieferanten-USt-ID)
     * 13. Skonto
     * 14. Buchungstext (Lieferanten-Name)
     * 15. Postensperre
     * 16. Kostenstelle (KOST1)
     * 17. KOST-Menge
     * 18. EU-Land + USt-IdNr.
     */
    private function buildCsv($belege): string
    {
        $cfg = (array) Settings::get('datev.config', []);
        $kontoLieferant = $cfg['konto_lieferant'] ?? '70000';
        $gegenkonto = $cfg['gegenkonto_aufwand'] ?? '4400';
        $buSchluessel = $cfg['bu_schluessel'] ?? '';
        $map = ((array) Settings::get('datev.field_map', [])) + self::DEFAULT_FIELD_MAP;

        $header = [
            'Umsatz', 'Soll/Haben', 'WKZ Umsatz', 'Kurs', 'Basis-Umsatz', 'WKZ Basis',
            'Konto', 'Gegenkonto', 'BU-Schluessel', 'Belegdatum',
            'Belegfeld 1', 'Belegfeld 2', 'Skonto', 'Buchungstext', 'Postensperre',
            'KOST1', 'KOST-Menge', 'EU-USt-IdNr',
        ];

        $lines = [implode(';', array_map([$this, 'quote'], $header))];

        foreach ($belege as $att) {
            $f = (array) ($att->indexed_fields ?? []);

            $betrag = $this->amount($f[$map['betrag_brutto']] ?? null);
            if ($betrag === '') {
                $betrag = $this->amount($f[$map['betrag_netto']] ?? null);
            }
            $datum = $this->date($f[$map['belegdatum']] ?? $att->created_at?->toDateString());
            $rnr = (string) ($f[$map['rechnungsnummer']] ?? '');
            $ustid = (string) ($f[$map['ustid']] ?? '');
            $kost = (string) ($f[$map['kostenstelle']] ?? '');
            $lief = (string) ($f[$map['lieferant']] ?? '');

            $row = [
                $betrag,            // 1 Umsatz
                'S',                // 2 Soll/Haben (Aufwand)
                'EUR',              // 3 WKZ
                '',                 // 4 Kurs
                '',                 // 5 Basis
                '',                 // 6 WKZ Basis
                $gegenkonto,        // 7 Konto (Aufwand)
                $kontoLieferant,    // 8 Gegenkonto (Kreditor)
                $buSchluessel,      // 9 BU
                $datum,             // 10 Belegdatum
                $rnr,               // 11 Belegfeld 1
                $ustid,             // 12 Belegfeld 2
                '',                 // 13 Skonto
                $lief !== '' ? $lief : ('Rg. '.$rnr),  // 14 Buchungstext
                '',                 // 15 Postensperre
                $kost,              // 16 KOST1
                '',                 // 17 KOST-Menge
                $ustid,             // 18 EU-USt-IdNr
            ];
            $lines[] = implode(';', array_map([$this, 'quote'], $row));
        }

        // BOM fuer Excel-Kompatibilitaet (DATEV-Import will UTF-8 mit BOM)
        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    private function readme(Carbon $from, Carbon $to, ?string $type, int $count): string
    {
        return <<<TXT
DATEV-Export aus OWE
====================

Erstellt:       {$from->copy()->setTimezone(config('app.timezone'))->format('d.m.Y H:i')}
Zeitraum:       {$from->format('d.m.Y')} - {$to->format('d.m.Y')}
Dokument-Typ:   {$type}
Belege:         {$count}

Inhalt:
  buchungsstapel.csv   DATEV-Buchungsstapel im UTF-8-CSV-Format
                        (Semikolon-getrennt, Spalten-Namen siehe Header).
  belege/              PDFs der Original-Belege, fortlaufend nummeriert
                        und mit der OWE-Attachment-ID im Dateinamen.

Hinweis:
  Die Spalten sind im standard-DATEV-Buchungsstapel-Format. Vor dem
  DATEV-Import ggf. ein passendes EXTF-Header anhaengen oder den
  Stapel im DATEV-Unternehmen-Online ueber den Beleg-Import einlesen.

  Konten + BU-Schluessel werden aus den OWE-Settings genommen
  (Settings-Key 'datev.config'). Aktuell gesetzt:
TXT;
    }

    private function quote(string $value): string
    {
        // CSV-Standard: in Quotes wenn Semikolon / Quote / Newline enthalten.
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }
        return $value;
    }

    private function amount($value): string
    {
        if ($value === null || $value === '') return '';
        // Numerisch normalisieren: Punkt+Komma-Mischformen erlauben
        $s = (string) $value;
        $s = str_replace(['€', ' '], '', $s);
        // Komma als Dezimal-Separator akzeptieren
        if (substr_count($s, ',') === 1 && substr_count($s, '.') > 0) {
            $s = str_replace('.', '', $s); // Tausender-Punkte weg
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, ',') === 1 && substr_count($s, '.') === 0) {
            $s = str_replace(',', '.', $s);
        }
        $num = (float) $s;
        return number_format(abs($num), 2, ',', '');
    }

    private function date($value): string
    {
        if (! $value) return '';
        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
