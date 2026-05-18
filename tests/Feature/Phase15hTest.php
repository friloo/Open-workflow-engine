<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\AttachmentStorage;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15hTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_documents_index_filters_by_indexed_field_contains(): void
    {
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Rechnung']);
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'rechnungsnummer', 'label' => 'Nr', 'type' => 'string',
                 'extractor' => 'heuristic:invoice_number', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);
        Storage::fake('local');
        $a = app(AttachmentStorage::class)->storeBytes('a', 'a.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $a->forceFill(['indexed_fields' => ['rechnungsnummer' => 'R-2026-001']])->save();
        $b = app(AttachmentStorage::class)->storeBytes('b', 'b.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $b->forceFill(['indexed_fields' => ['rechnungsnummer' => 'X-9999']])->save();

        $this->actingAs($admin)
            ->get(route('documents.index', ['type' => 'Rechnung', 'fields' => ['rechnungsnummer' => 'R-2026']]))
            ->assertOk()
            ->assertSee('a.pdf')
            ->assertDontSee('b.pdf');
    }

    public function test_documents_index_filters_by_indexed_field_range(): void
    {
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Rechnung']);
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'datum', 'label' => 'Datum', 'type' => 'date',
                 'extractor' => 'heuristic:date_de', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);
        Storage::fake('local');
        $a = app(AttachmentStorage::class)->storeBytes('a', 'a.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $a->forceFill(['indexed_fields' => ['datum' => '2026-03-15']])->save();
        $b = app(AttachmentStorage::class)->storeBytes('b', 'b.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $b->forceFill(['indexed_fields' => ['datum' => '2026-06-15']])->save();

        $this->actingAs($admin)
            ->get(route('documents.index', [
                'type' => 'Rechnung',
                'fields' => ['datum' => ['from' => '2026-01-01', 'to' => '2026-04-30']],
            ]))
            ->assertOk()
            ->assertSee('a.pdf')
            ->assertDontSee('b.pdf');
    }

    public function test_dashboard_shows_my_open_tasks_and_inbox_count(): void
    {
        $admin = $this->admin();
        // Aufgabe an admin
        $workflow = Workflow::create(['name' => 'W', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'n' => ['id' => 'n', 'class' => 'approval', 'data' => ['label' => 'Pruefen Rechnung'], 'outputs' => []],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'started_by' => $admin->id, 'status' => WorkflowInstance::STATUS_RUNNING,
            'started_at' => now(),
        ]);
        WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'n', 'step_type' => 'approval',
            'assigned_to_user_id' => $admin->id, 'assigned_at' => now(),
            'due_at' => now()->addDays(2),
        ]);

        Storage::fake('local');
        app(AttachmentStorage::class)->storeBytes('x', 'orphan.pdf', 'application/pdf', null, null, $admin->id, null);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pruefen Rechnung')
            ->assertSee('Postkorb');
    }

    public function test_workflow_template_index_lists_builtins(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('workflows.templates.index'))
            ->assertOk()
            ->assertSee('Rechnungseingang')
            ->assertSee('Urlaubsantrag')
            ->assertSee('Bestellfreigabe');
    }

    public function test_workflow_template_import_creates_draft(): void
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->post(route('workflows.templates.import.store'), [
            'source' => 'builtin',
            'builtin' => 'rechnungseingang',
        ])->assertRedirect();

        $workflow = Workflow::where('name', 'Rechnungseingang')->firstOrFail();
        $this->assertSame(Workflow::STATUS_DRAFT, $workflow->status);
        $version = $workflow->currentVersion()->first();
        $this->assertNotNull($version);
        $this->assertArrayHasKey('drawflow', $version->definition);
    }

    public function test_workflow_template_import_rejects_bad_json(): void
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->post(route('workflows.templates.import.store'), [
            'source' => 'paste',
            'json' => '{"not_a_template": true}',
        ]);
        $resp->assertSessionHasErrors();
    }

    public function test_workflow_template_export_returns_json(): void
    {
        $admin = $this->admin();
        $workflow = Workflow::create(['name' => 'Foo', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_DRAFT]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => []]]],
            'form_schema' => [],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $resp = $this->actingAs($admin)->get(route('workflows.templates.export', $workflow));
        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/json');
        $body = json_decode($resp->streamedContent(), true);
        $this->assertSame(1, $body['owe_workflow_template']);
        $this->assertSame('Foo', $body['name']);
    }
}
