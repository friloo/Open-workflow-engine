<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Verwaltet die Felder-Schemata pro Dokumenttyp. Quelle: Settings-Key
 * `attachments.field_schemas` — ein Mapping
 *   document_type => [ ['key'=>..., 'label'=>..., 'type'=>..., 'extractor'=>..., 'pattern'=>..., 'ki_fallback'=>bool], ... ].
 */
class DocumentFieldSchema
{
    public const FIELD_TYPES = [
        'string' => 'Text',
        'number' => 'Zahl',
        'date' => 'Datum',
        'currency' => 'Betrag (EUR)',
        'iban' => 'IBAN',
        'email' => 'E-Mail',
    ];

    public const EXTRACTORS = [
        'manual' => 'Nur manuell (kein Auto-Lesen)',
        'heuristic:invoice_number' => 'Heuristik: Rechnungsnummer',
        'heuristic:date_de' => 'Heuristik: Datum (deutsch)',
        'heuristic:currency_eur' => 'Heuristik: EUR-Betrag',
        'heuristic:iban' => 'Heuristik: IBAN',
        'heuristic:vat_id_de' => 'Heuristik: USt-IdNr. (DE)',
        'heuristic:email' => 'Heuristik: E-Mail-Adresse',
        'zugferd:invoice_number' => 'ZUGFeRD/XRechnung: Rechnungsnummer (XML)',
        'zugferd:invoice_date' => 'ZUGFeRD/XRechnung: Datum (XML)',
        'zugferd:amount_net' => 'ZUGFeRD/XRechnung: Netto (XML)',
        'zugferd:amount_tax' => 'ZUGFeRD/XRechnung: USt-Betrag (XML)',
        'zugferd:amount_gross' => 'ZUGFeRD/XRechnung: Brutto (XML)',
        'zugferd:currency' => 'ZUGFeRD/XRechnung: Waehrung (XML)',
        'zugferd:vendor_name' => 'ZUGFeRD/XRechnung: Lieferant (XML)',
        'zugferd:vendor_vat_id' => 'ZUGFeRD/XRechnung: USt-IdNr. Lieferant (XML)',
        'zugferd:iban' => 'ZUGFeRD/XRechnung: IBAN (XML)',
        'zugferd:bic' => 'ZUGFeRD/XRechnung: BIC (XML)',
        'zugferd:buyer_reference' => 'ZUGFeRD/XRechnung: Leitweg-ID (XML)',
        'lookup' => 'Lookup-Liste (anlernen ueber Listen-Eintraege)',
        'regex' => 'Eigener Regex (Capture-Group 1)',
        'ki' => 'KI (OpenAI-kompatibel)',
    ];

    /** @return array<int, array{key:string,label:string,type:string,extractor:string,pattern:?string,ki_fallback:bool}> */
    public static function forType(string $documentType): array
    {
        $all = (array) Settings::get('attachments.field_schemas', []);
        $raw = (array) ($all[$documentType] ?? []);
        $out = [];
        foreach ($raw as $f) {
            if (! is_array($f) || empty($f['key'])) continue;
            $out[] = [
                'key' => (string) $f['key'],
                'label' => (string) ($f['label'] ?? $f['key']),
                'type' => in_array($f['type'] ?? '', array_keys(self::FIELD_TYPES), true) ? $f['type'] : 'string',
                'extractor' => array_key_exists($f['extractor'] ?? '', self::EXTRACTORS) ? $f['extractor'] : 'manual',
                'pattern' => $f['pattern'] ?? null,
                'ki_fallback' => ! empty($f['ki_fallback']),
            ];
        }
        return $out;
    }

    /** @return array<string, array> Alle Schemas (key = document_type) */
    public static function all(): array
    {
        $raw = (array) Settings::get('attachments.field_schemas', []);
        $out = [];
        foreach ($raw as $type => $_) {
            $out[(string) $type] = self::forType((string) $type);
        }
        return $out;
    }

    /**
     * Stellt sicher, dass im Schema des Doku-Typs ein Feld mit dem
     * gegebenen Key vorhanden ist. Wenn ja: nichts tun. Wenn nein:
     * anhaengen — mit dem mitgegebenen Label/Typ, Extractor 'manual'.
     *
     * Wird nach einer Approval-Entscheidung aufgerufen, wenn der
     * Genehmiger ein Zusatzfeld ausgefuellt hat und der Anhang einen
     * Doku-Typ hat. Damit erscheint das Feld ab dann automatisch in der
     * Suche und im Detail-Editor — der Designer-Eintrag pflegt sich
     * also selbst ins Schema ein.
     *
     * @return bool true wenn das Schema veraendert wurde
     */
    public static function ensureField(string $documentType, string $key, string $label, string $type): bool
    {
        $documentType = trim($documentType);
        $key = Str::slug($key, '_');
        if ($documentType === '' || $key === '') return false;

        $all = (array) Settings::get('attachments.field_schemas', []);
        $existing = (array) ($all[$documentType] ?? []);
        foreach ($existing as $f) {
            if (($f['key'] ?? null) === $key) return false; // schon da
        }

        $existing[] = [
            'key' => $key,
            'label' => $label !== '' ? $label : $key,
            'type' => in_array($type, array_keys(self::FIELD_TYPES), true) ? $type : 'string',
            'extractor' => 'manual',
            'pattern' => null,
            'ki_fallback' => false,
        ];
        $all[$documentType] = array_values($existing);
        Settings::set('attachments.field_schemas', $all);
        return true;
    }

    public static function save(string $documentType, array $fields): void
    {
        $all = (array) Settings::get('attachments.field_schemas', []);
        $clean = [];
        foreach ($fields as $f) {
            if (empty($f['key'])) continue;
            $clean[] = [
                'key' => Str::slug((string) $f['key'], '_'),
                'label' => (string) ($f['label'] ?? $f['key']),
                'type' => in_array($f['type'] ?? '', array_keys(self::FIELD_TYPES), true) ? $f['type'] : 'string',
                'extractor' => array_key_exists($f['extractor'] ?? '', self::EXTRACTORS) ? $f['extractor'] : 'manual',
                'pattern' => isset($f['pattern']) ? (string) $f['pattern'] : null,
                'ki_fallback' => ! empty($f['ki_fallback']),
            ];
        }
        $all[$documentType] = array_values($clean);
        Settings::set('attachments.field_schemas', $all);
    }

    public static function delete(string $documentType): void
    {
        $all = (array) Settings::get('attachments.field_schemas', []);
        unset($all[$documentType]);
        Settings::set('attachments.field_schemas', $all);
    }
}
