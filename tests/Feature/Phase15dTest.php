<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Phase15dTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_health_page_renders_for_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.health.index'))
            ->assertOk()
            ->assertSee('System-Health')
            ->assertSee('Datenbank');
    }

    public function test_health_json_includes_overall_status(): void
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->getJson(route('admin.health.json'));
        $resp->assertOk()
            ->assertJsonStructure(['status', 'checks' => [['name', 'status', 'message']]]);
    }

    public function test_mail_approval_completes_step_via_signed_link(): void
    {
        $admin = $this->admin();
        $approver = User::factory()->create();
        $approver->assignRole('employee');

        $workflow = Workflow::create([
            'name' => 'Test', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE,
        ]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'n1' => ['id' => 'n1', 'class' => 'approval',
                    'data' => ['label' => 'Genehmigung'],
                    'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'started_by' => $admin->id,
            'status' => WorkflowInstance::STATUS_RUNNING,
            'started_at' => now(),
            'current_step_key' => 'n1',
        ]);
        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => 'n1',
            'step_type' => 'approval',
            'assigned_to_user_id' => $approver->id,
            'assigned_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('mail-approval.show', now()->addDays(3), [
            'step' => $step->id, 'user' => $approver->id, 'decision' => 'approved',
        ]);
        $this->get($url)->assertOk()->assertSee('Genehmigung bestaetigen');

        // POST: same URL, signature preserved in query string.
        $this->post($url, ['comment' => 'OK'])->assertOk()->assertSee('Genehmigt');

        $step->refresh();
        $this->assertNotNull($step->completed_at);
        $this->assertSame('approved', $step->decision);
        $this->assertSame($approver->id, $step->completed_by);
    }

    public function test_mail_approval_rejects_tampered_signature(): void
    {
        $admin = $this->admin();
        $approver = User::factory()->create();

        $workflow = Workflow::create([
            'name' => 'X', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE,
        ]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => []]]],
            'created_by' => $admin->id,
        ]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'started_by' => $admin->id, 'status' => WorkflowInstance::STATUS_RUNNING, 'started_at' => now(),
        ]);
        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'n1', 'step_type' => 'approval',
            'assigned_to_user_id' => $approver->id, 'assigned_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('mail-approval.show', now()->addDays(3), [
            'step' => $step->id, 'user' => $approver->id, 'decision' => 'approved',
        ]);
        $tampered = preg_replace('/signature=[a-f0-9]+/i', 'signature=deadbeef', $url);
        $this->get($tampered)->assertStatus(403);
    }
}
