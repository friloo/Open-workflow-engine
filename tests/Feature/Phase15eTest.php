<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine;
use App\Services\WorkflowStats;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase15eTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_active_delegate_returns_user_in_range(): void
    {
        $this->admin();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $a->update([
            'delegate_user_id' => $b->id,
            'delegate_from' => now()->subDay()->toDateString(),
            'delegate_to' => now()->addDay()->toDateString(),
        ]);
        $this->assertNotNull($a->fresh()->activeDelegate());
        $this->assertSame($b->id, $a->fresh()->activeDelegate()->id);
    }

    public function test_active_delegate_is_null_outside_range(): void
    {
        $this->admin();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $a->update([
            'delegate_user_id' => $b->id,
            'delegate_from' => now()->addDays(2)->toDateString(),
            'delegate_to' => now()->addDays(10)->toDateString(),
        ]);
        $this->assertNull($a->fresh()->activeDelegate());
    }

    public function test_active_delegate_breaks_cycle(): void
    {
        $this->admin();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $today = now()->toDateString();
        $window = ['delegate_from' => $today, 'delegate_to' => $today];
        $a->update(['delegate_user_id' => $b->id] + $window);
        $b->update(['delegate_user_id' => $a->id] + $window);
        // Kein Endlos-Loop; nach drei Hops geben wir auf.
        $this->assertNotNull($a->fresh()->activeDelegate());
    }

    public function test_workflow_engine_reroutes_task_to_delegate(): void
    {
        $admin = $this->admin();
        $assignee = User::factory()->create();
        $delegate = User::factory()->create();
        $today = now()->toDateString();
        $assignee->update([
            'delegate_user_id' => $delegate->id,
            'delegate_from' => $today, 'delegate_to' => $today,
        ]);

        $workflow = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'a']]]]],
                'a' => ['id' => 'a', 'class' => 'approval',
                    'data' => ['label' => 'Pruefen', 'recipient_type' => 'user', 'recipient_user_id' => $assignee->id],
                    'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $instance = app(WorkflowEngine::class)->start($workflow, [], $admin);

        $step = WorkflowStepExecution::where('workflow_instance_id', $instance->id)->firstOrFail();
        $this->assertSame($delegate->id, $step->assigned_to_user_id);
    }

    public function test_delegation_form_saves(): void
    {
        $a = $this->admin();
        $b = User::factory()->create();
        $this->actingAs($a)->post(route('profile.delegation.update'), [
            'delegate_user_id' => $b->id,
            'delegate_from' => now()->toDateString(),
            'delegate_to' => now()->addDays(7)->toDateString(),
            'delegate_reason' => 'Urlaub',
        ])->assertRedirect();
        $a->refresh();
        $this->assertSame($b->id, $a->delegate_user_id);
    }

    public function test_workflow_stats_service_computes_metrics(): void
    {
        $admin = $this->admin();
        $workflow = Workflow::create(['name' => 'X', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => []]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'started_by' => $admin->id, 'status' => WorkflowInstance::STATUS_COMPLETED,
            'started_at' => now()->subHours(2), 'completed_at' => now()->subHour(),
        ]);
        WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'started_by' => $admin->id, 'status' => WorkflowInstance::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $stats = app(WorkflowStats::class)->forWorkflow($workflow);
        $this->assertSame(1, $stats['instances']['running']);
        $this->assertSame(1, $stats['instances']['completed']);
        $this->assertNotNull($stats['duration']['avg']);
        $this->assertCount(12, $stats['throughput']);
    }

    public function test_stats_pages_render(): void
    {
        $admin = $this->admin();
        $w = Workflow::create(['name' => 'X', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $this->actingAs($admin)->get(route('workflows.stats.index'))->assertOk()->assertSee('Throughput');
        $this->actingAs($admin)->get(route('workflows.stats.show', $w))->assertOk()->assertSee('Engpässe');
    }
}
