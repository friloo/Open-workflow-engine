<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Extrahiert strukturierte Felder aus ZUGFeRD-/Factur-X-/XRechnung-PDFs.
 *
 * Strategie:
 *   1. PDF mit `pdfdetach -saveall` (poppler-utils) entpacken — wir suchen
 *      eine Datei wie factur-x.xml / ZUGFeRD-invoice.xml / xrechnung.xml.
 *   2. Fallback: PHP-eigene Suche nach eingebetteten Streams (heuristisch).
 *   3. XML parsen mit DOMXPath + local-name(), damit alle Namespace-
 *      Varianten (UN/CEFACT CII, EN 16931) abgedeckt sind.
 *
 * Liefert ein Mapping von Feld-Kuerzeln auf erkannte Werte. Wird gecached
 * pro Attachment (parsing ist nicht ganz billig).
 */
class ZugferdParser
{
    /** @var array<string, ?array> */
    private array $cache = [];

    /** Welche Felder die Heuristiken auslesen koennen. */
    public const FIELDS = [
        'invoice_number' => 'Rechnungsnummer',
        'invoice_date' => 'Rechnungsdatum (YYYY-MM-DD)',
        'amount_net' => 'Netto-Summe',
        'amount_tax' => 'USt-Betrag',
        'amount_gross' => 'Brutto-/Gesamtbetrag',
        'currency' => 'Waehrung (z.B. EUR)',
        'vendor_name' => 'Lieferanten-Name',
        'vendor_vat_id' => 'USt-IdNr des Lieferanten',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'buyer_reference' => 'Leitweg-ID / Kaeufer-Referenz',
    ];

    /**
     * @return array<string, string>|null  oder null wenn kein ZUGFeRD/Factur-X
     */
    public function parse(Attachment $att): ?array
    {
        // Cache nach content_hash, damit identische Bytes nur einmal geparst
        // werden — aber unterschiedliche Anhaenge nicht kollidieren.
        $cacheKey = (string) ($att->content_hash ?? 'no-hash-'.$att->id);
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if ($att->mime_type !== 'application/pdf') {
            return $this->cache[$cacheKey] = null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk($att->disk);
        if (! $disk->exists($att->path)) {
            return $this->cache[$cacheKey] = null;
        }

        $tmpPdf = tempnam(sys_get_temp_dir(), 'owe-zf-').'.pdf';
        @file_put_contents($tmpPdf, $disk->get($att->path));

        $xml = $this->extractEmbeddedXml($tmpPdf);
        @unlink($tmpPdf);

        if ($xml === null) {
            return $this->cache[$cacheKey] = null;
        }

        $fields = $this->parseInvoiceXml($xml);
        return $this->cache[$cacheKey] = $fields;
    }

    private function extractEmbeddedXml(string $pdfPath): ?string
    {
        // 1. Bevorzugt: pdfdetach (poppler-utils)
        if ($this->canExec('pdfdetach')) {
            $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'owe-zf-'.bin2hex(random_bytes(6));
            @mkdir($dir, 0755, true);
            $process = new Process(['pdfdetach', '-saveall', '-o', $dir, $pdfPath], null, null, null, 30);
            try {
                $process->run();
            } catch (\Throwable $e) {
                $this->rrmdir($dir);
                return null;
            }
            $xml = null;
            foreach (glob($dir.'/*.xml') ?: [] as $file) {
                $name = strtolower(basename($file));
                if (str_contains($name, 'factur-x') || str_contains($name, 'zugferd') ||
                    str_contains($name, 'xrechnung') || str_contains($name, 'invoice')) {
                    $xml = (string) file_get_contents($file);
                    break;
                }
            }
            // Fallback: irgendeine XML-Datei
            if ($xml === null) {
                foreach (glob($dir.'/*.xml') ?: [] as $file) {
                    $xml = (string) file_get_contents($file);
                    break;
                }
            }
            $this->rrmdir($dir);
            if ($xml !== null && $xml !== '') return $xml;
        }

        // 2. Fallback: PDF-Stream durchsuchen nach eingebetteter XML
        $content = (string) file_get_contents($pdfPath);
        if ($content === '') return null;

        // Suche nach XML-Header + Root-Element (CrossIndustryInvoice |
        // Invoice | UBLDocument). Backreference auf die gleiche Tag-Form
        // damit wir bei verschachtelten ram:-Tags nicht zu frueh schliessen.
        $pattern = '/<\?xml[^>]*\?>\s*<((?:[a-zA-Z]+:)?(?:CrossIndustryInvoice|Invoice|UBLDocument))[^>]*>.*<\/\1\s*>/is';
        if (preg_match($pattern, $content, $m)) {
            return $m[0];
        }
        return null;
    }

    /**
     * Public entry point fuer rohes XML (z. B. wenn die XRechnung als
     * separater XML-Anhang in einer Mail kommt).
     *
     * @return array<string,string>|null
     */
    public function parseXmlBytes(string $xml): ?array
    {
        $xml = trim($xml);
        if ($xml === '') return null;
        $result = $this->parseInvoiceXml($xml);
        return $result ?: null;
    }

    private function parseInvoiceXml(string $xml): array
    {
        $doc = new \DOMDocument();
        @$doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $doc->documentElement) return [];

        $xpath = new \DOMXPath($doc);
        $get = fn (string $local, ?string $contextLocal = null): ?string => $this->firstText($xpath, $local, $contextLocal);

        // Rechnungsnummer: meist /rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID
        // oder /Invoice/cbc:ID (UBL)
        $number = $this->firstByXpath($xpath, "//*[local-name()='ExchangedDocument']//*[local-name()='ID']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='Invoice']/*[local-name()='ID']");

        // Datum: ram:IssueDateTime/udt:DateTimeString oder cbc:IssueDate
        $date = $this->firstByXpath($xpath, "//*[local-name()='IssueDateTime']//*[local-name()='DateTimeString']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='Invoice']/*[local-name()='IssueDate']");
        if ($date) $date = $this->normalizeDate($date);

        // Beträge
        $gross = $this->firstByXpath($xpath, "//*[local-name()='GrandTotalAmount']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='LegalMonetaryTotal']/*[local-name()='PayableAmount']");
        $net = $this->firstByXpath($xpath, "//*[local-name()='LineTotalAmount']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='TaxBasisTotalAmount']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='LegalMonetaryTotal']/*[local-name()='LineExtensionAmount']");
        $tax = $this->firstByXpath($xpath, "//*[local-name()='TaxTotalAmount']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='TaxTotal']/*[local-name()='TaxAmount']");
        $currency = $this->attrByXpath($xpath, "//*[local-name()='GrandTotalAmount']", 'currencyID')
            ?? $this->firstByXpath($xpath, "//*[local-name()='DocumentCurrencyCode']");

        // Lieferant
        $vendor = $this->firstByXpath($xpath, "//*[local-name()='SellerTradeParty']/*[local-name()='Name']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='AccountingSupplierParty']//*[local-name()='Party']/*[local-name()='PartyName']/*[local-name()='Name']");
        $vendorVatId = $this->firstByXpath($xpath, "//*[local-name()='SellerTradeParty']//*[local-name()='SpecifiedTaxRegistration']/*[local-name()='ID']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='AccountingSupplierParty']//*[local-name()='PartyTaxScheme']/*[local-name()='CompanyID']");

        // Bank
        $iban = $this->firstByXpath($xpath, "//*[local-name()='PayeePartyCreditorFinancialAccount']/*[local-name()='IBANID']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='PaymentMeans']//*[local-name()='PayeeFinancialAccount']/*[local-name()='ID']");
        $bic = $this->firstByXpath($xpath, "//*[local-name()='PayeeSpecifiedCreditorFinancialInstitution']/*[local-name()='BICID']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='FinancialInstitutionBranch']/*[local-name()='ID']");

        // Leitweg-ID / Buyer Reference
        $buyerRef = $this->firstByXpath($xpath, "//*[local-name()='BuyerReference']")
            ?? $this->firstByXpath($xpath, "//*[local-name()='Invoice']/*[local-name()='BuyerReference']");

        return array_filter([
            'invoice_number' => $number,
            'invoice_date' => $date,
            'amount_net' => $net ? $this->normalizeNumber($net) : null,
            'amount_tax' => $tax ? $this->normalizeNumber($tax) : null,
            'amount_gross' => $gross ? $this->normalizeNumber($gross) : null,
            'currency' => $currency,
            'vendor_name' => $vendor,
            'vendor_vat_id' => $vendorVatId,
            'iban' => $iban ? strtoupper(preg_replace('/\s+/', '', $iban)) : null,
            'bic' => $bic,
            'buyer_reference' => $buyerRef,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function firstByXpath(\DOMXPath $xp, string $query): ?string
    {
        $nodes = @$xp->query($query);
        if (! $nodes || $nodes->length === 0) return null;
        return trim((string) $nodes->item(0)->textContent);
    }

    private function attrByXpath(\DOMXPath $xp, string $query, string $attr): ?string
    {
        $nodes = @$xp->query($query);
        if (! $nodes || $nodes->length === 0) return null;
        $value = $nodes->item(0)->attributes?->getNamedItem($attr)?->nodeValue;
        return $value ? trim($value) : null;
    }

    private function firstText(\DOMXPath $xp, string $local, ?string $context = null): ?string
    {
        // not used directly anymore — kept for compatibility
        $query = $context
            ? "//*[local-name()='{$context}']//*[local-name()='{$local}']"
            : "//*[local-name()='{$local}']";
        return $this->firstByXpath($xp, $query);
    }

    private function normalizeDate(string $s): string
    {
        $s = preg_replace('/\D/', '', $s);
        if (strlen($s) === 8) {
            return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
        }
        return $s;
    }

    private function normalizeNumber(string $s): string
    {
        // ZUGFeRD nutzt Punkt als Dezimaltrenner
        return trim($s);
    }

    private function canExec(string $bin): bool
    {
        if (! function_exists('proc_open')) return false;
        try {
            $process = new Process(['which', $bin], null, null, null, 5);
            $process->run();
            return $process->isSuccessful() && trim($process->getOutput()) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) return;
        foreach (glob($dir.'/*') ?: [] as $f) @unlink($f);
        @rmdir($dir);
    }
}
