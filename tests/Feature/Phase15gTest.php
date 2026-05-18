<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\LookupList;
use App\Models\LookupListEntry;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\AttachmentStorage;
use App\Services\FieldExtractor;
use App\Services\WorkflowEngine;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15gTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    private function kostenstellenListe(User $admin, User $verantwortlich): LookupList
    {
        $list = LookupList::create([
            'name' => 'Kostenstellen',
            'slug' => 'kostenstellen',
            'columns' => [
                ['key' => 'kostenstelle', 'label' => 'Kostenstelle', 'role' => LookupList::ROLE_KEY],
                ['key' => 'verantwortlicher', 'label' => 'Verantwortlich', 'role' => LookupList::ROLE_RESPONSIBLE],
            ],
            'created_by' => $admin->id,
        ]);
        LookupListEntry::create([
            'lookup_list_id' => $list->id,
            'key_value' => 'K-2026-01',
            'data' => ['kostenstelle' => 'K-2026-01', 'verantwortlicher' => $verantwortlich->email],
        ]);
        LookupListEntry::create([
            'lookup_list_id' => $list->id,
            'key_value' => 'K-2026-02',
            'data' => ['kostenstelle' => 'K-2026-02', 'verantwortlicher' => 'unbekannt@example.com'],
        ]);
        return $list;
    }

    public function test_lookup_extractor_finds_kostenstelle_in_text(): void
    {
        $admin = $this->admin();
        $maria = User::factory()->create();
        $list = $this->kostenstellenListe($admin, $maria);

        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'kostenstelle', 'label' => 'Kostenstelle', 'type' => 'string',
                 'extractor' => 'lookup', 'pattern' => 'kostenstellen', 'ki_fallback' => false],
            ],
        ]);

        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('dummy', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $att->forceFill(['ocr_text' => "Rechnung\nBezug: Kostenstelle K-2026-01 \nGesamt 119,00 EUR"])->save();

        $res = app(FieldExtractor::class)->extractFor($att);
        $this->assertSame('K-2026-01', $res['kostenstelle']);
    }

    public function test_lookup_prefers_longer_match(): void
    {
        $admin = $this->admin();
        $list = LookupList::create([
            'name' => 'Codes', 'slug' => 'codes',
            'columns' => [['key' => 'code', 'label' => 'Code', 'role' => LookupList::ROLE_KEY]],
            'created_by' => $admin->id,
        ]);
        LookupListEntry::create(['lookup_list_id' => $list->id, 'key_value' => 'K-2026', 'data' => ['code' => 'K-2026']]);
        LookupListEntry::create(['lookup_list_id' => $list->id, 'key_value' => 'K-2026-001', 'data' => ['code' => 'K-2026-001']]);

        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'code', 'label' => 'C', 'type' => 'string',
                 'extractor' => 'lookup', 'pattern' => 'codes', 'ki_fallback' => false],
            ],
        ]);
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('dummy', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $att->forceFill(['ocr_text' => 'Code: K-2026-001 fuer Auftrag'])->save();

        $res = app(FieldExtractor::class)->extractFor($att);
        $this->assertSame('K-2026-001', $res['code']);
    }

    public function test_doc_context_available_via_dot_notation_in_approval(): void
    {
        $admin = $this->admin();
        $maria = User::factory()->create();
        $list = $this->kostenstellenListe($admin, $maria);

        // Workflow mit Approval-Knoten, der ueber doc.indexed_fields.kostenstelle
        // den Verantwortlichen aus der Liste sucht.
        $workflow = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'a']]]]],
                'a' => ['id' => 'a', 'class' => 'approval',
                    'data' => [
                        'label' => 'Pruefen',
                        'recipient_type' => 'list_lookup',
                        'list_id' => $list->id,
                        'lookup_source' => 'doc.indexed_fields.kostenstelle',
                    ],
                    'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        // Anhang mit indexed_fields
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('dummy', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $att->forceFill(['indexed_fields' => ['kostenstelle' => 'K-2026-01']])->save();

        $instance = app(WorkflowEngine::class)->start(
            $workflow,
            ['doc_attachment_id' => $att->id, 'kostenstelle' => 'K-2026-01'],
            $admin,
        );

        $step = WorkflowStepExecution::where('workflow_instance_id', $instance->id)->firstOrFail();
        $this->assertSame($maria->id, $step->assigned_to_user_id);
    }

    public function test_list_lookup_fallback_when_no_value(): void
    {
        $admin = $this->admin();
        $maria = User::factory()->create();
        $list = $this->kostenstellenListe($admin, $maria);
        $einkauf = Role::where('slug', 'employee')->first();

        $workflow = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'a']]]]],
                'a' => ['id' => 'a', 'class' => 'approval',
                    'data' => [
                        'label' => 'Pruefen',
                        'recipient_type' => 'list_lookup',
                        'list_id' => $list->id,
                        'lookup_source' => 'doc.indexed_fields.kostenstelle',
                        'fallback_role_id' => $einkauf->id,
                    ],
                    'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        // Anhang ohne Kostenstelle
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('dummy', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');

        $instance = app(WorkflowEngine::class)->start(
            $workflow,
            ['doc_attachment_id' => $att->id],
            $admin,
        );

        $step = WorkflowStepExecution::where('workflow_instance_id', $instance->id)->firstOrFail();
        $this->assertNull($step->assigned_to_user_id);
        $this->assertSame($einkauf->id, $step->assigned_to_role_id);
    }

    public function test_inbox_lists_orphan_documents(): void
    {
        $admin = $this->admin();
        Storage::fake('local');
        app(AttachmentStorage::class)->storeBytes('a', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');

        $this->actingAs($admin)->get(route('documents.inbox'))
            ->assertOk()
            ->assertSee('Postkorb')
            ->assertSee('r.pdf');
    }

    public function test_start_workflow_from_inbox_attaches_doc_and_uses_indexed_fields(): void
    {
        $admin = $this->admin();
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('a', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $att->forceFill(['indexed_fields' => ['rechnungsnummer' => 'R-77']])->save();

        $workflow = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'e']]]]],
                'e' => ['id' => 'e', 'class' => 'end', 'data' => ['result' => 'completed'], 'outputs' => []],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $resp = $this->actingAs($admin)->post(route('documents.start_workflow', $att), [
            'workflow_id' => $workflow->id,
        ])->assertRedirect();

        $att->refresh();
        $this->assertSame(WorkflowInstance::class, $att->attachable_type);
        $instance = WorkflowInstance::findOrFail($att->attachable_id);
        $this->assertSame($att->id, $instance->data['doc_attachment_id']);
        $this->assertSame('R-77', $instance->data['rechnungsnummer']);
    }

    public function test_manual_field_correction_persists_only_schema_keys(): void
    {
        $admin = $this->admin();
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'nummer', 'label' => 'N', 'type' => 'string',
                 'extractor' => 'heuristic:invoice_number', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('a', 'r.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');

        $this->actingAs($admin)->post(route('documents.fields.update', $att), [
            'fields' => ['nummer' => 'R-99', 'evil' => 'sollte rausfliegen'],
        ])->assertRedirect();

        $att->refresh();
        $this->assertSame(['nummer' => 'R-99'], $att->indexed_fields);
    }
}
