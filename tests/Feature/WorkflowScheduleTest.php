<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowSchedule;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function activeWorkflow(User $admin): Workflow
    {
        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>['label'=>'Pruefen','recipient_type'=>'supervisor_of_initiator','grace_value'=>1,'grace_unit'=>'days'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];
        $wf = Workflow::create([
            'name' => 'Führerschein-Check', 'slug' => 'führerschein-check',
            'trigger_type' => 'recurring', 'status' => 'active',
            'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $v = WorkflowVersion::create(['workflow_id' => $wf->id, 'version_number' => 1, 'definition' => $def, 'form_schema' => [], 'created_by' => $admin->id]);
        $wf->forceFill(['current_version_id' => $v->id])->save();
        return $wf;
    }

    public function test_due_schedule_starts_an_instance_and_advances(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create(['supervisor_id' => $admin->id]);
        $employee->assignRole('employee');

        $workflow = $this->activeWorkflow($admin);
        $schedule = WorkflowSchedule::create([
            'workflow_id' => $workflow->id,
            'subject_user_id' => $employee->id,
            'subject_label' => 'Führerschein Klasse B',
            'interval_value' => 6, 'interval_unit' => 'months',
            'next_run_at' => now()->subDay(),
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->artisan('workflow:run-schedules')->assertSuccessful();

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertGreaterThan(now()->addMonths(5), $schedule->next_run_at);
        $this->assertSame(1, $workflow->instances()->count());

        $instance = $workflow->instances()->first();
        $this->assertSame($employee->id, $instance->started_by);
        $this->assertSame('Führerschein Klasse B', $instance->data['subject_label']);
        $this->assertSame($employee->id, $instance->data['subject_user_id']);

        // Resulting task should be assigned to the supervisor.
        $this->assertSame($admin->id, $instance->stepExecutions()->first()->assigned_to_user_id);
    }

    public function test_inactive_schedule_is_skipped(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $workflow = $this->activeWorkflow($admin);
        WorkflowSchedule::create([
            'workflow_id' => $workflow->id,
            'interval_value' => 1, 'interval_unit' => 'days',
            'next_run_at' => now()->subDay(),
            'is_active' => false,
            'created_by' => $admin->id,
        ]);

        $this->artisan('workflow:run-schedules')->assertSuccessful();
        $this->assertSame(0, $workflow->instances()->count());
    }
}
