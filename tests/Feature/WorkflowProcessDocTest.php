<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowProcessDocTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_process_doc_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $workflow = Workflow::create(['name' => 'Rechnungseingang', 'slug' => 're', 'status' => 'active', 'created_by' => $admin->id, 'trigger_type' => 'mail']);
        WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'n1' => ['id' => 'n1', 'class' => 'start', 'data' => ['label' => 'Start'],
                        'outputs' => ['output_1' => ['connections' => [['node' => 'n2']]]]],
                'n2' => ['id' => 'n2', 'class' => 'approval', 'data' => [
                    'label' => 'Freigabe', 'recipient_type' => 'supervisor_of_initiator',
                    'grace_value' => 3, 'grace_unit' => 'days',
                    'extra_fields' => [[
                        'key' => 'bemerkung', 'label' => 'Bemerkung',
                        'type' => 'text', 'target' => 'doc',
                    ]],
                ], 'outputs' => ['output_1' => ['connections' => [['node' => 'n3']]]]],
                'n3' => ['id' => 'n3', 'class' => 'end', 'data' => ['label' => 'Ende', 'result' => 'completed']],
            ]]]],
        ]);

        $resp = $this->actingAs($admin)->get(route('workflows.process_doc', $workflow));
        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/pdf');
        $body = $resp->getContent();
        $this->assertGreaterThan(2000, strlen($body), 'PDF sollte mehr als 2 KB sein');
        $this->assertStringStartsWith('%PDF-', $body, 'Content beginnt mit PDF-Magic');

        // Audit
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.process_doc.printed']);
    }

    public function test_process_doc_requires_at_least_one_version(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $workflow = Workflow::create(['name' => 'Leer', 'slug' => 'leer', 'status' => 'draft', 'created_by' => $admin->id]);

        $resp = $this->actingAs($admin)->get(route('workflows.process_doc', $workflow));
        $resp->assertRedirect();
        $resp->assertSessionHasErrors('process_doc');
    }
}
