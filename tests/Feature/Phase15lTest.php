<?php

namespace Tests\Feature;

use App\Models\LookupList;
use App\Models\LookupListEntry;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase15lTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    private function userWith(string $roleSlug): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole($roleSlug);
        return $u;
    }

    // ─── Per-Liste-Berechtigungen ──────────────────────────────────────

    public function test_list_visible_to_admin_always(): void
    {
        $admin = $this->admin();
        $list = LookupList::create(['name' => 'Geheim', 'slug' => 'g', 'columns' => [
            ['key' => 'k', 'label' => 'K', 'role' => 'key'],
        ]]);
        $this->assertTrue($list->visibleForUser($admin));
        $this->assertTrue($list->editableByUser($admin));
    }

    public function test_list_without_role_perms_visible_to_all_with_view(): void
    {
        $employee = $this->userWith('employee');
        $list = LookupList::create(['name' => 'Public', 'slug' => 'p', 'columns' => [
            ['key' => 'k', 'label' => 'K', 'role' => 'key'],
        ]]);
        // employee hat lists.view nicht standardmaessig
        $this->assertFalse($list->visibleForUser($employee));
        // Designer schon
        $designer = $this->userWith('workflow-designer');
        $this->assertTrue($list->visibleForUser($designer));
    }

    public function test_list_with_role_perms_only_visible_to_those_roles(): void
    {
        $designer = $this->userWith('workflow-designer');
        $designerRole = Role::where('slug', 'workflow-designer')->first();

        // Eigene Rolle "buchhaltung" mit lists.view/manage
        $buchRole = Role::create(['slug' => 'buchhaltung', 'name' => 'Buchhaltung',
            'description' => '', 'is_system' => false]);
        $buchRole->permissions()->sync(\App\Models\Permission::whereIn('slug',
            ['lists.view', 'lists.manage'])->pluck('id'));

        $list = LookupList::create(['name' => 'Restricted', 'slug' => 'r', 'columns' => [
            ['key' => 'k', 'label' => 'K', 'role' => 'key'],
        ]]);
        $list->roles()->sync([$buchRole->id => ['can_edit' => true]]);

        // Designer ist nicht in buchhaltung-Rolle -> nein
        $this->assertFalse($list->visibleForUser($designer));

        // User mit buchhaltung-Rolle -> ja
        $buch = User::factory()->create();
        $buch->roles()->attach($buchRole->id);
        $this->assertTrue($list->visibleForUser($buch));
        $this->assertTrue($list->editableByUser($buch));
    }

    public function test_list_index_filters_by_visibility(): void
    {
        $designer = $this->userWith('workflow-designer');
        $designerRole = Role::where('slug', 'workflow-designer')->first();
        $employeeRole = Role::where('slug', 'employee')->first();

        $public = LookupList::create(['name' => 'A-Public', 'slug' => 'a', 'columns' => [['key' => 'k', 'label' => 'K', 'role' => 'key']]]);
        $restricted = LookupList::create(['name' => 'Z-Restricted', 'slug' => 'z', 'columns' => [['key' => 'k', 'label' => 'K', 'role' => 'key']]]);
        $restricted->roles()->sync([$employeeRole->id => ['can_edit' => false]]);

        $resp = $this->actingAs($designer)->get(route('lists.index'));
        $resp->assertOk();
        $resp->assertSee('A-Public');
        $resp->assertDontSee('Z-Restricted');
    }

    // ─── Wait + Set-Field ──────────────────────────────────────────────

    public function test_wait_node_pauses_and_resumes(): void
    {
        $admin = $this->admin();
        $workflow = Workflow::create(['name' => 'W', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'w']]]]],
                'w' => ['id' => 'w', 'class' => 'wait',
                    'data' => ['label' => 'Pause', 'wait_value' => 1, 'wait_unit' => 'days'],
                    'outputs' => ['output_1' => ['connections' => [['node' => 'e']]]]],
                'e' => ['id' => 'e', 'class' => 'end', 'data' => ['result' => 'completed'], 'outputs' => []],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $instance = app(WorkflowEngine::class)->start($workflow, [], $admin);
        $this->assertSame(WorkflowInstance::STATUS_RUNNING, $instance->status);

        $step = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->where('step_type', 'wait')->firstOrFail();
        $this->assertNotNull($step->due_at);
        $this->assertTrue($step->due_at->greaterThan(now()->addHours(23)));

        // Faelligkeit vorziehen + check-due ausfuehren
        $step->forceFill(['due_at' => now()->subMinute()])->save();
        $this->artisan('workflow:check-due')->assertSuccessful();

        $instance->refresh();
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    public function test_set_field_node_writes_to_instance_data(): void
    {
        $admin = $this->admin();
        $workflow = Workflow::create(['name' => 'W', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'sf']]]]],
                'sf' => ['id' => 'sf', 'class' => 'set_field',
                    'data' => ['label' => 'Setze', 'assignments' => [
                        ['field' => 'kostenstelle_kopie', 'value' => '{{ kostenstelle }}', 'as_number' => false],
                        ['field' => 'fester_wert', 'value' => 'static', 'as_number' => false],
                    ]],
                    'outputs' => ['output_1' => ['connections' => [['node' => 'e']]]]],
                'e' => ['id' => 'e', 'class' => 'end', 'data' => ['result' => 'completed'], 'outputs' => []],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $instance = app(WorkflowEngine::class)->start($workflow, ['kostenstelle' => 'K-1'], $admin);
        $instance->refresh();
        $this->assertSame('K-1', $instance->data['kostenstelle_kopie']);
        $this->assertSame('static', $instance->data['fester_wert']);
    }

    // ─── ZUGFeRD-Parser (Mock-XML, ohne Binary) ────────────────────────

    public function test_zugferd_parser_extracts_from_xml_via_pdf_fallback(): void
    {
        // Minimaler PDF-Stream mit eingebettetem CII-XML, der vom Fallback
        // erkannt wird (regex auf "<?xml ... <rsm:CrossIndustryInvoice ...>").
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
    xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
    xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
  <rsm:ExchangedDocument>
    <ram:ID>R-2026-00042</ram:ID>
    <ram:IssueDateTime>
      <udt:DateTimeString format="102">20260515</udt:DateTimeString>
    </ram:IssueDateTime>
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:SellerTradeParty>
        <ram:Name>ACME GmbH</ram:Name>
        <ram:SpecifiedTaxRegistration>
          <ram:ID>DE123456789</ram:ID>
        </ram:SpecifiedTaxRegistration>
      </ram:SellerTradeParty>
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:LineTotalAmount currencyID="EUR">100.00</ram:LineTotalAmount>
        <ram:TaxBasisTotalAmount currencyID="EUR">100.00</ram:TaxBasisTotalAmount>
        <ram:TaxTotalAmount currencyID="EUR">19.00</ram:TaxTotalAmount>
        <ram:GrandTotalAmount currencyID="EUR">119.00</ram:GrandTotalAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;
        // "PDF": dummy header + embedded XML (Fallback-Regex matched darin)
        $fakePdf = "%PDF-1.7\n".$xml."\n%%EOF\n";

        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->admin();
        $att = app(\App\Services\AttachmentStorage::class)->storeBytes(
            $fakePdf, 'rechnung.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung'
        );

        $parser = app(\App\Services\ZugferdParser::class);
        $fields = $parser->parse($att);

        $this->assertNotNull($fields);
        $this->assertSame('R-2026-00042', $fields['invoice_number']);
        $this->assertSame('2026-05-15', $fields['invoice_date']);
        $this->assertSame('119.00', $fields['amount_gross']);
        $this->assertSame('100.00', $fields['amount_net']);
        $this->assertSame('19.00', $fields['amount_tax']);
        $this->assertSame('EUR', $fields['currency']);
        $this->assertSame('ACME GmbH', $fields['vendor_name']);
        $this->assertSame('DE123456789', $fields['vendor_vat_id']);
    }

    public function test_zugferd_fields_in_schema_get_populated_from_xml(): void
    {
        \App\Support\Settings::set('attachments.document_types', ['Rechnung']);
        \App\Support\Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'nr', 'label' => 'Nr', 'type' => 'string',
                 'extractor' => 'zugferd:invoice_number', 'pattern' => null, 'ki_fallback' => false],
                ['key' => 'brutto', 'label' => 'Brutto', 'type' => 'currency',
                 'extractor' => 'zugferd:amount_gross', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);

        $xml = <<<XML
<?xml version="1.0"?>
<rsm:CrossIndustryInvoice xmlns:rsm="x" xmlns:ram="x" xmlns:udt="x">
  <rsm:ExchangedDocument><ram:ID>R-99</ram:ID></rsm:ExchangedDocument>
  <ram:GrandTotalAmount currencyID="EUR">42.00</ram:GrandTotalAmount>
</rsm:CrossIndustryInvoice>
XML;
        $fakePdf = "%PDF-1.7\n".$xml."\n%%EOF\n";

        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->admin();
        $att = app(\App\Services\AttachmentStorage::class)->storeBytes(
            $fakePdf, 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung'
        );
        $att->refresh();

        $this->assertSame('R-99', $att->indexed_fields['nr'] ?? null);
        $this->assertSame('42.00', $att->indexed_fields['brutto'] ?? null);
    }
}
