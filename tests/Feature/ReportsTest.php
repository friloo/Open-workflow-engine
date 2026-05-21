<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\ReportsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_open_reports(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_admin_can_open_reports_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('reports.index'))->assertOk()
            ->assertSeeText('Volumen-Trend')
            ->assertSeeText('Top-Empfänger');
    }

    public function test_reports_service_computes_volume_summary(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $workflow = Workflow::create(['name' => 'WF', 'slug' => 'wf', 'status' => 'active', 'created_by' => $admin->id]);
        $version = WorkflowVersion::create(['workflow_id' => $workflow->id, 'version_number' => 1, 'definition' => ['drawflow' => ['Home' => ['data' => []]]]]);
        // 2 completed, 1 running
        WorkflowInstance::create(['workflow_id' => $workflow->id, 'workflow_version_id' => $version->id, 'data' => [], 'status' => 'completed', 'started_at' => now()->subDays(5), 'completed_at' => now()->subDays(4)]);
        WorkflowInstance::create(['workflow_id' => $workflow->id, 'workflow_version_id' => $version->id, 'data' => [], 'status' => 'completed', 'started_at' => now()->subDays(3), 'completed_at' => now()->subDays(2)]);
        WorkflowInstance::create(['workflow_id' => $workflow->id, 'workflow_version_id' => $version->id, 'data' => [], 'status' => 'running',   'started_at' => now()->subDays(1)]);

        $svc = new ReportsService();
        $vol = $svc->volumeSummary();
        $this->assertSame(3, $vol['total']);
        $this->assertSame(2, $vol['completed']);
        $this->assertSame(1, $vol['running']);
        $this->assertEqualsWithDelta(66.7, $vol['completionRate'], 0.1);
    }

    public function test_reports_service_computes_lead_times(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $w = Workflow::create(['name' => 'WF', 'slug' => 'wf', 'status' => 'active', 'created_by' => $admin->id]);
        $v = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1, 'definition' => ['drawflow' => ['Home' => ['data' => []]]]]);
        WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id, 'data' => [], 'status' => 'completed', 'started_at' => now()->subDays(2), 'completed_at' => now()->subDays(2)->addHours(2)]);
        WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id, 'data' => [], 'status' => 'completed', 'started_at' => now()->subDays(1), 'completed_at' => now()->subDays(1)->addHours(4)]);

        $rows = (new ReportsService())->leadTimePerWorkflow();
        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['n']);
        $this->assertEqualsWithDelta(3.0, $rows[0]['median_h'], 0.1); // (2+4)/2 = 3
        $this->assertEqualsWithDelta(3.0, $rows[0]['avg_h'], 0.1);
    }

    public function test_reports_service_finds_sla_violations(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $w = Workflow::create(['name' => 'WF', 'slug' => 'wf', 'status' => 'active', 'created_by' => $admin->id]);
        $v = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1, 'definition' => ['drawflow' => ['Home' => ['data' => []]]]]);
        $i = WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id, 'data' => [], 'status' => 'running', 'started_at' => now()->subDays(5)]);

        // overdue step
        WorkflowStepExecution::create(['workflow_instance_id' => $i->id, 'step_key' => 'n1', 'step_type' => 'approval', 'assigned_to_user_id' => $admin->id, 'due_at' => now()->subDay()]);
        // not yet due
        WorkflowStepExecution::create(['workflow_instance_id' => $i->id, 'step_key' => 'n2', 'step_type' => 'approval', 'assigned_to_user_id' => $admin->id, 'due_at' => now()->addDay()]);

        $rows = (new ReportsService())->slaViolations();
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['overdue']);
    }

    public function test_reports_service_computes_top_assignees(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u1 = User::factory()->create(['name' => 'Alice']);
        $u2 = User::factory()->create(['name' => 'Bob']);
        $w = Workflow::create(['name' => 'WF', 'slug' => 'wf', 'status' => 'active', 'created_by' => $u1->id]);
        $v = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1, 'definition' => ['drawflow' => ['Home' => ['data' => []]]]]);
        $i = WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id, 'data' => [], 'status' => 'running', 'started_at' => now()]);

        WorkflowStepExecution::create(['workflow_instance_id' => $i->id, 'step_key' => 'a', 'step_type' => 'approval', 'assigned_to_user_id' => $u1->id]);
        WorkflowStepExecution::create(['workflow_instance_id' => $i->id, 'step_key' => 'b', 'step_type' => 'approval', 'assigned_to_user_id' => $u1->id, 'due_at' => now()->subDay()]);
        WorkflowStepExecution::create(['workflow_instance_id' => $i->id, 'step_key' => 'c', 'step_type' => 'approval', 'assigned_to_user_id' => $u2->id]);

        $rows = (new ReportsService())->topAssignees();
        $alice = $rows->firstWhere('name', 'Alice');
        $bob = $rows->firstWhere('name', 'Bob');
        $this->assertSame(2, $alice['open']);
        $this->assertSame(1, $alice['overdue']);
        $this->assertSame(1, $bob['open']);
        $this->assertSame(0, $bob['overdue']);
    }
}
