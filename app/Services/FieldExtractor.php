<?php

namespace App\Services;

use App\Models\Attachment;
use App\Support\DocumentFieldSchema;

/**
 * Extrahiert strukturierte Felder aus OCR-Text + Metadaten.
 *
 * Reihenfolge pro Feld:
 *   1. Heuristik / Regex / KI (laut Schema-Eintrag)
 *   2. wenn leer und `ki_fallback` gesetzt UND KI verfügbar -> KI
 *
 * KI ist immer optional. Ohne konfigurierte KI läuft alles via Heuristik.
 */
class FieldExtractor
{
    public function __construct(
        private readonly ?AIFieldExtractor $ai = null,
        private readonly ?ZugferdParser $zugferd = null,
    ) {}

    /**
     * Liest die Schema-Felder aus, führt Extraktion durch und persistiert
     * sie in `attachments.indexed_fields`. Liefert die ermittelten Felder.
     */
    public function extractFor(Attachment $att): array
    {
        $type = $att->document_type;
        if (! $type) return [];

        $schema = DocumentFieldSchema::forType($type);
        if (empty($schema)) return [];

        $text = (string) ($att->ocr_text ?? '');
        $context = trim($att->original_name.' '.$att->label.' '.$text);

        // ZUGFeRD nur einmal parsen falls mind. ein Feld es will.
        $zugferdNeeded = collect($schema)->contains(fn ($f) => str_starts_with($f['extractor'], 'zugferd:'));
        $zugferdData = ($zugferdNeeded && $this->zugferd) ? ($this->zugferd->parse($att) ?? []) : [];

        $aiNeeded = [];
        $result = [];

        foreach ($schema as $field) {
            $value = $this->extractOne($field, $context, $text, $zugferdData);
            if ($value !== null && $value !== '') {
                $result[$field['key']] = $value;
                continue;
            }
            if ($field['ki_fallback'] || $field['extractor'] === 'ki') {
                $aiNeeded[] = $field;
            }
        }

        if ($aiNeeded && $this->ai && $this->ai->isAvailable()) {
            $aiResult = $this->ai->extract($aiNeeded, $context);
            foreach ($aiResult as $k => $v) {
                if ($v !== null && $v !== '') {
                    $result[$k] = $v;
                }
            }
        }

        $att->forceFill([
            'indexed_fields' => $result,
            'indexed_at' => now(),
        ])->save();

        return $result;
    }

    private function extractOne(array $field, string $context, string $text, array $zugferdData = []): ?string
    {
        $ex = $field['extractor'];
        if ($ex === 'manual') return null;
        if ($ex === 'ki') return null; // wird gebündelt verarbeitet

        if (str_starts_with($ex, 'zugferd:')) {
            $key = substr($ex, strlen('zugferd:'));
            $v = $zugferdData[$key] ?? null;
            return $v !== null && $v !== '' ? (string) $v : null;
        }
        if ($ex === 'regex') {
            return $this->runRegex((string) ($field['pattern'] ?? ''), $context);
        }
        if ($ex === 'lookup') {
            return $this->findInLookup((string) ($field['pattern'] ?? ''), $context);
        }
        if (str_starts_with($ex, 'heuristic:')) {
            $kind = substr($ex, strlen('heuristic:'));
            return match ($kind) {
                'iban' => $this->findIban($context),
                'email' => $this->findEmail($context),
                'date_de' => $this->findGermanDate($context),
                'currency_eur' => $this->findCurrency($context),
                'invoice_number' => $this->findInvoiceNumber($text),
                'vat_id_de' => $this->findGermanVatId($context),
                default => null,
            };
        }
        return null;
    }

    private function runRegex(string $pattern, string $text): ?string
    {
        if ($pattern === '') return null;
        // Erkennen ob der Anwender selbst Delimiter (/.../i) gesetzt hat. Wenn ja,
        // direkt benutzen. Wenn nein, in /.../i einwickeln.
        $delimiters = ['/', '#', '~', '@', '%'];
        $first = $pattern[0] ?? '';
        $isDelimited = in_array($first, $delimiters, true)
            && preg_match('/^.(.+).([a-zA-Z]*)$/s', $pattern)
            && @preg_match($pattern, '') !== false;
        if (! $isDelimited) {
            // # als Delimiter, damit Slashes im Muster nicht escaped werden müssen.
            $pattern = '#'.str_replace('#', '\\#', $pattern).'#i';
            if (@preg_match($pattern, '') === false) return null;
        }
        if (@preg_match($pattern, $text, $m) === 1) {
            return trim($m[1] ?? $m[0]);
        }
        return null;
    }

    private function findIban(string $text): ?string
    {
        // 1) Kompakte Form: DE89370400440532013000
        if (preg_match('/\b([A-Z]{2}\d{2}[A-Z0-9]{11,30})\b/', $text, $m)) {
            $iban = strtoupper($m[1]);
            if (strlen($iban) >= 15 && strlen($iban) <= 34) return $iban;
        }
        // 2) 4er-Gruppen mit einzelnem Leerzeichen: DE89 3704 0044 0532 0130 00
        if (preg_match('/\b([A-Z]{2}\d{2}(?: [A-Z0-9]{2,4}){3,8})\b/', $text, $m)) {
            $iban = strtoupper(str_replace(' ', '', $m[1]));
            if (strlen($iban) >= 15 && strlen($iban) <= 34) return $iban;
        }
        return null;
    }

    private function findEmail(string $text): ?string
    {
        if (! preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $m)) {
            return null;
        }
        return strtolower($m[0]);
    }

    private function findGermanDate(string $text): ?string
    {
        // Bevorzugt Datum in der Nähe von "Datum", "Rechnungsdatum", etc.
        if (preg_match('/(?:rechnungs-?datum|leistungsdatum|datum|date)[^\d]{0,12}(\d{1,2}\.\s?\d{1,2}\.\s?\d{2,4})/iu', $text, $m)) {
            return $this->normalizeGermanDate($m[1]);
        }
        if (preg_match('/\b(\d{1,2}\.\s?\d{1,2}\.\s?\d{4})\b/', $text, $m)) {
            return $this->normalizeGermanDate($m[1]);
        }
        return null;
    }

    private function normalizeGermanDate(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $parts = explode('.', $s);
        if (count($parts) !== 3) return $s;
        [$d, $mo, $y] = $parts;
        if (strlen($y) === 2) $y = (int) $y >= 70 ? '19'.$y : '20'.$y;
        return sprintf('%04d-%02d-%02d', (int) $y, (int) $mo, (int) $d);
    }

    private function findCurrency(string $text): ?string
    {
        // Bevorzugt Betrag in der Nähe von Brutto/Gesamt; sonst größter Wert.
        if (preg_match('/(?:gesamt(?:summe)?|brutto|zu zahlen|rechnungs?betrag)[^\d]{0,20}(\d{1,3}(?:[.\s]\d{3})*[.,]\d{2})\s*(?:€|EUR)?/iu', $text, $m)) {
            return $this->normalizeAmount($m[1]);
        }
        if (preg_match_all('/(\d{1,3}(?:[.\s]\d{3})*[.,]\d{2})\s*(?:€|EUR)\b/u', $text, $m)) {
            $values = array_map(fn ($v) => (float) $this->normalizeAmount($v), $m[1]);
            $idx = array_keys($values, max($values))[0];
            return $this->normalizeAmount($m[1][$idx]);
        }
        return null;
    }

    private function normalizeAmount(string $s): string
    {
        // 1.234,56 -> 1234.56 ; 1,234.56 -> 1234.56
        $s = trim(preg_replace('/\s+/', '', $s));
        // Wenn Komma als Dezimaltrenner (deutsche Notation)
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $s)) {
            return str_replace([',', '.'], ['.', ''], $s) === '' ? $s : strtr($s, ['.' => '', ',' => '.']);
        }
        // Englische Notation 1,234.56
        if (preg_match('/^\d{1,3}(,\d{3})*\.\d{2}$/', $s)) {
            return str_replace(',', '', $s);
        }
        // Schlicht "12.34" oder "12,34"
        return str_replace(',', '.', $s);
    }

    private function findInvoiceNumber(string $text): ?string
    {
        $patterns = [
            '/rechnungs?-?(?:nr|nummer)\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{2,29})/i',
            '/invoice\s*(?:no|number|#)?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{2,29})/i',
            '/beleg-?nr\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{2,29})/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }

    private function findGermanVatId(string $text): ?string
    {
        if (preg_match('/\b(DE\s?\d{9})\b/i', $text, $m)) {
            return strtoupper(str_replace(' ', '', $m[1]));
        }
        return null;
    }

    /**
     * Sucht den OCR-Text nach Schlüsseln aus einer Lookup-Liste. Damit
     * lernt das System z. B. Kostenstellen-Codes ohne KI: der Anwender
     * pflegt die Liste, der Extraktor findet die Werte im Dokument.
     * Bei mehreren Treffern gewinnt der längste (spezifischste).
     */
    private function findInLookup(string $listSlug, string $text): ?string
    {
        $listSlug = trim($listSlug);
        if ($listSlug === '') return null;

        $list = \App\Models\LookupList::where('slug', $listSlug)->first();
        if (! $list) return null;

        $keys = $list->entries()->pluck('key_value')->filter()->all();
        if (empty($keys)) return null;

        // Längste zuerst, damit "K-2026-01" vor "K-2026" gewinnt.
        usort($keys, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        foreach ($keys as $k) {
            $k = (string) $k;
            if ($k === '') continue;
            // Exakt mit Wortgrenzen (case-insensitive)
            $pattern = '/\b'.preg_quote($k, '/').'\b/i';
            if (preg_match($pattern, $text, $m)) {
                return $k;
            }
        }
        return null;
    }
}
