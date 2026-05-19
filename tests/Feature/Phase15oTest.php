<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Services\ZugferdParser;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15oTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_zugferd_xml_can_be_parsed_directly(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="x" xmlns:ram="x" xmlns:udt="x">
  <rsm:ExchangedDocument><ram:ID>R-123</ram:ID></rsm:ExchangedDocument>
  <ram:GrandTotalAmount currencyID="EUR">238.00</ram:GrandTotalAmount>
  <ram:SellerTradeParty><ram:Name>Beispiel KG</ram:Name></ram:SellerTradeParty>
</rsm:CrossIndustryInvoice>
XML;
        $fields = app(ZugferdParser::class)->parseXmlBytes($xml);
        $this->assertSame('R-123', $fields['invoice_number']);
        $this->assertSame('238.00', $fields['amount_gross']);
        $this->assertSame('EUR', $fields['currency']);
        $this->assertSame('Beispiel KG', $fields['vendor_name']);
    }

    public function test_zugferd_viewer_renders_on_document_detail(): void
    {
        $admin = $this->admin();
        Storage::fake('local');
        // Wir bauen ein "PDF" das die ZUGFeRD-XML enthaelt — der Parser
        // findet sie ueber den Regex-Fallback.
        $xml = <<<XML
<?xml version="1.0"?>
<rsm:CrossIndustryInvoice xmlns:rsm="x" xmlns:ram="x" xmlns:udt="x">
  <rsm:ExchangedDocument><ram:ID>R-VIEW</ram:ID></rsm:ExchangedDocument>
  <ram:GrandTotalAmount currencyID="EUR">99.00</ram:GrandTotalAmount>
  <ram:SellerTradeParty><ram:Name>Anzeige GmbH</ram:Name></ram:SellerTradeParty>
</rsm:CrossIndustryInvoice>
XML;
        $pdf = "%PDF-1.7\n".$xml."\n%%EOF\n";
        $att = app(AttachmentStorage::class)->storeBytes(
            $pdf, 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung'
        );

        $resp = $this->actingAs($admin)->get(route('documents.show', $att));
        $resp->assertOk();
        $resp->assertSee('ZUGFeRD');
        $resp->assertSee('R-VIEW');
        $resp->assertSee('Anzeige GmbH');
    }

    public function test_mailbox_fetcher_attaches_xrechnung_xml_to_pdf(): void
    {
        // Simuliert die Mailbox-Logik direkt: PDF + XML kommen separat,
        // XML-Daten landen am PDF-Attachment in indexed_fields._zugferd.
        $admin = $this->admin();
        Storage::fake('local');

        // 1. PDF-Datei (ohne eingebettete XML — die Sicht-PDF)
        $att = app(AttachmentStorage::class)->storeBytes(
            'normaler PDF-Inhalt der Rechnung', 'rechnung.pdf', 'application/pdf',
            null, null, $admin->id, 'Rechnung',
        );

        // 2. Separate XML in derselben Mail — extrahieren + ankleben
        $xml = <<<XML
<?xml version="1.0"?>
<rsm:CrossIndustryInvoice xmlns:rsm="x" xmlns:ram="x">
  <rsm:ExchangedDocument><ram:ID>R-FROM-XML</ram:ID></rsm:ExchangedDocument>
  <ram:GrandTotalAmount currencyID="EUR">238.00</ram:GrandTotalAmount>
</rsm:CrossIndustryInvoice>
XML;
        $parsed = app(\App\Services\ZugferdParser::class)->parseXmlBytes($xml);
        $fields = (array) ($att->indexed_fields ?? []);
        $fields['_zugferd'] = $parsed;
        foreach ($parsed as $k => $v) {
            if (! array_key_exists($k, $fields)) $fields[$k] = $v;
        }
        $att->forceFill(['indexed_fields' => $fields])->save();

        // Workflow-Bedingung sieht die Werte direkt
        $att->refresh();
        $this->assertSame('R-FROM-XML', $att->indexed_fields['invoice_number']);
        $this->assertSame('238.00', $att->indexed_fields['amount_gross']);

        // Im Viewer wird die ZUGFeRD-Karte angezeigt
        $resp = $this->actingAs($admin)->get(route('documents.show', $att));
        $resp->assertOk();
        $resp->assertSee('ZUGFeRD');
        $resp->assertSee('R-FROM-XML');
    }

    public function test_zugferd_data_can_be_attached_to_pdf_attachment(): void
    {
        // Wenn das XML separat angeliefert wird (Mail mit PDF + XML), sollen
        // die strukturierten Daten ans PDF gehaengt werden koennen.
        $admin = $this->admin();
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes(
            'normaler PDF-Inhalt', 'sicht.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung'
        );

        $xml = <<<XML
<?xml version="1.0"?>
<rsm:CrossIndustryInvoice xmlns:rsm="x" xmlns:ram="x" xmlns:udt="x">
  <rsm:ExchangedDocument><ram:ID>R-XML-77</ram:ID></rsm:ExchangedDocument>
  <ram:GrandTotalAmount currencyID="EUR">59.50</ram:GrandTotalAmount>
</rsm:CrossIndustryInvoice>
XML;
        $parsed = app(ZugferdParser::class)->parseXmlBytes($xml);
        $att->forceFill([
            'indexed_fields' => ['_zugferd' => $parsed, 'invoice_number' => $parsed['invoice_number']],
        ])->save();

        $resp = $this->actingAs($admin)->get(route('documents.show', $att));
        $resp->assertOk();
        $resp->assertSee('R-XML-77');
        $resp->assertSee('ZUGFeRD');
    }
}
