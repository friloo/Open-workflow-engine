<?php

namespace Tests\Feature\Workflow;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowStats;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProcessMiningTest extends TestCase
{
    use RefreshDatabase;

    private function mkWorkflowWithApproval(User $admin): Workflow
    {
        $wf = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $v = WorkflowVersion::create([
            'workflow_id' => $wf->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'a1' => ['id' => 'a1', 'class' => 'approval', 'data' => ['label' => 'Freigabe A']],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $wf->update(['current_version_id' => $v->id]);
        return $wf;
    }

    private function mkStep(Workflow $wf, string $stepKey, ?string $decision, ?Carbon $assigned, ?Carbon $completed, ?Carbon $due = null, ?int $completedBy = null): WorkflowStepExecution
    {
        $instance = WorkflowInstance::create([
            'workflow_id' => $wf->id,
            'workflow_version_id' => $wf->current_version_id,
            'status' => 'completed',
            'started_at' => $assigned ?: now(),
            'completed_at' => $completed,
        ]);
        return WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => $stepKey,
            'step_type' => 'approval',
            'assigned_at' => $assigned ?: now(),
            'completed_at' => $completed,
            'due_at' => $due,
            'decision' => $decision,
            'completed_by' => $completedBy,
        ]);
    }

    public function test_sla_metrics_count_on_time_vs_late(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $wf = $this->mkWorkflowWithApproval($admin);

        // 3 on-time, 2 late
        for ($i = 0; $i < 3; $i++) {
            $this->mkStep($wf, 'a1', 'approved', now()->subDays(2), now()->subDays(1), now()->subHours(12));
        }
        for ($i = 0; $i < 2; $i++) {
            $this->mkStep($wf, 'a1', 'approved', now()->subDays(5), now()->subDay(), now()->subDays(3));
        }

        $stats = app(WorkflowStats::class)->forWorkflow($wf);
        $this->assertSame(5, $stats['sla']['n']);
        $this->assertSame(3, $stats['sla']['on_time']);
        $this->assertSame(2, $stats['sla']['late']);
        $this->assertSame(60, $stats['sla']['on_time_pct']);
    }

    public function test_decision_distribution_groups_by_step_and_decision(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $wf = $this->mkWorkflowWithApproval($admin);

        $this->mkStep($wf, 'a1', 'approved', now()->subDay(), now());
        $this->mkStep($wf, 'a1', 'approved', now()->subDay(), now());
        $this->mkStep($wf, 'a1', 'rejected', now()->subDay(), now());

        $stats = app(WorkflowStats::class)->forWorkflow($wf);
        $this->assertCount(1, $stats['decisions']);
        $this->assertSame(2, $stats['decisions'][0]['approved']);
        $this->assertSame(1, $stats['decisions'][0]['rejected']);
        $this->assertSame(3, $stats['decisions'][0]['total']);
    }

    public function test_suggestions_warn_on_high_rejection_rate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $wf = $this->mkWorkflowWithApproval($admin);

        // 11 von 20 abgelehnt -> sollte Hinweis ausloesen (>= 50%)
        for ($i = 0; $i < 11; $i++) $this->mkStep($wf, 'a1', 'rejected', now()->subDay(), now());
        for ($i = 0; $i < 9; $i++) $this->mkStep($wf, 'a1', 'approved', now()->subDay(), now());

        $stats = app(WorkflowStats::class)->forWorkflow($wf);
        $rejectionHints = array_filter($stats['suggestions'], fn ($h) => str_contains($h['text'], 'abgelehnt'));
        $this->assertNotEmpty($rejectionHints);
    }

    public function test_stats_view_renders_new_sections(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $wf = $this->mkWorkflowWithApproval($admin);

        $this->mkStep($wf, 'a1', 'approved', now()->subDay(), now(), now()->addHour(), $admin->id);

        $this->actingAs($admin)->get(route('workflows.stats.show', $wf))
            ->assertOk()
            ->assertSee('SLA-Quote')
            ->assertSee('Approval-Entscheidungen')
            ->assertSee('Top-Bearbeiter');
    }
}
