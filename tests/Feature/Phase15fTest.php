<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Services\FieldExtractor;
use App\Support\DocumentFieldSchema;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15fTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    private function rechnungsAttachment(string $text): Attachment
    {
        $admin = $this->admin();
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('dummy', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $att->forceFill(['ocr_text' => $text, 'ocr_status' => 'done'])->save();
        return $att;
    }

    public function test_heuristic_invoice_number(): void
    {
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'nummer', 'label' => 'Nummer', 'type' => 'string',
                 'extractor' => 'heuristic:invoice_number', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);
        $att = $this->rechnungsAttachment("ACME GmbH\nRechnungsnr.: R-2026-00042\nDatum 15.05.2026\nGesamt 119,00 EUR");

        $res = app(FieldExtractor::class)->extractFor($att);
        $this->assertSame('R-2026-00042', $res['nummer']);
        $this->assertSame('R-2026-00042', $att->fresh()->indexed_fields['nummer']);
    }

    public function test_heuristic_date_iban_currency_vat(): void
    {
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'datum', 'label' => 'Datum', 'type' => 'date',
                 'extractor' => 'heuristic:date_de', 'pattern' => null, 'ki_fallback' => false],
                ['key' => 'iban', 'label' => 'IBAN', 'type' => 'iban',
                 'extractor' => 'heuristic:iban', 'pattern' => null, 'ki_fallback' => false],
                ['key' => 'betrag', 'label' => 'Brutto', 'type' => 'currency',
                 'extractor' => 'heuristic:currency_eur', 'pattern' => null, 'ki_fallback' => false],
                ['key' => 'ustid', 'label' => 'USt-ID', 'type' => 'string',
                 'extractor' => 'heuristic:vat_id_de', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);
        $att = $this->rechnungsAttachment(
            "Lieferant GmbH\nUSt-IdNr.: DE123456789\nRechnungsdatum: 03.04.2026\n".
            "IBAN: DE89 3704 0044 0532 0130 00\nNetto 100,00 EUR\nGesamt 119,00 EUR\n"
        );

        $res = app(FieldExtractor::class)->extractFor($att);
        $this->assertSame('2026-04-03', $res['datum']);
        $this->assertSame('DE89370400440532013000', $res['iban']);
        $this->assertSame('119.00', $res['betrag']);
        $this->assertSame('DE123456789', $res['ustid']);
    }

    public function test_regex_extractor_with_loose_pattern(): void
    {
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'auftrag', 'label' => 'Auftrag', 'type' => 'string',
                 'extractor' => 'regex',
                 'pattern' => 'Auftrag\s*Nr\.?\s*([A-Z0-9\-/]+)',
                 'ki_fallback' => false],
            ],
        ]);
        $att = $this->rechnungsAttachment('Bezug: Auftrag Nr. AX-99/2026, vielen Dank.');
        $res = app(FieldExtractor::class)->extractFor($att);
        $this->assertSame('AX-99/2026', $res['auftrag']);
    }

    public function test_extraction_skipped_when_no_schema(): void
    {
        $att = $this->rechnungsAttachment('hat keinen Plan');
        $res = app(FieldExtractor::class)->extractFor($att);
        $this->assertSame([], $res);
        $this->assertNull($att->fresh()->indexed_fields);
    }

    public function test_admin_can_save_schema(): void
    {
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Rechnung']);

        $resp = $this->actingAs($admin)
            ->put(route('admin.document_schemas.update', 'Rechnung'), [
                'fields' => [
                    ['key' => 'rechnungsnummer', 'label' => 'Rechnungsnummer',
                     'type' => 'string', 'extractor' => 'heuristic:invoice_number',
                     'pattern' => '', 'ki_fallback' => '1'],
                ],
            ])->assertRedirect();

        $stored = DocumentFieldSchema::forType('Rechnung');
        $this->assertCount(1, $stored);
        $this->assertSame('rechnungsnummer', $stored[0]['key']);
        $this->assertTrue($stored[0]['ki_fallback']);
    }

    public function test_reindex_command_fills_indexed_fields(): void
    {
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'nummer', 'label' => 'N', 'type' => 'string',
                 'extractor' => 'heuristic:invoice_number', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);
        $att = $this->rechnungsAttachment('Rechnungs-Nr. R-77 vom 1.1.2026');
        // indexed_fields auf null setzen, damit reindex sie neu schreibt
        $att->forceFill(['indexed_fields' => null, 'indexed_at' => null])->save();

        $this->artisan('documents:reindex --type=Rechnung')->assertSuccessful();
        $this->assertSame('R-77', $att->fresh()->indexed_fields['nummer']);
    }
}
