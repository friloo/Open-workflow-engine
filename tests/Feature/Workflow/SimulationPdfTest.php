<?php

namespace Tests\Feature\Workflow;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_pdf_downloads_for_valid_workflow(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $workflow = Workflow::create(['name' => 'Test-Workflow', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'data' => ['label' => 'Start'],
                       'outputs' => ['output_1' => ['connections' => [['node' => 'e']]]]],
                'e' => ['id' => 'e', 'class' => 'end', 'data' => ['label' => 'Ende', 'result' => 'completed'],
                       'outputs' => []],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $resp = $this->actingAs($admin)->post(route('workflows.simulate.pdf', $workflow), [
            'data' => ['feld_a' => 'wert'],
        ]);

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/pdf');
        $resp->assertHeader('content-disposition');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'workflow.simulation_exported',
        ]);
    }
}
