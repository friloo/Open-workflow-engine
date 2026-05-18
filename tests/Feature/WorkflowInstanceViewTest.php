<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowInstanceViewTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrap(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create(['supervisor_id' => $admin->id]);
        $employee->assignRole('employee');

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>['label'=>'Pruefen','recipient_type'=>'supervisor_of_initiator'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]],'output_2'=>['connections'=>[]]]],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create(['name' => 'WF', 'slug' => 'wf', 'trigger_type' => 'manual', 'status' => 'active', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $v = WorkflowVersion::create(['workflow_id' => $wf->id, 'version_number' => 1, 'definition' => $def, 'created_by' => $admin->id]);
        $wf->forceFill(['current_version_id' => $v->id])->save();

        return ['admin' => $admin, 'employee' => $employee, 'workflow' => $wf];
    }

    public function test_admin_can_view_all_instances(): void
    {
        ['employee' => $employee, 'admin' => $admin, 'workflow' => $wf] = $this->bootstrap();
        app(WorkflowEngine::class)->start($wf, ['betrag' => 100], $employee);

        $this->actingAs($admin)->get(route('workflow-instances.index'))
            ->assertOk()
            ->assertSee('Vorgaenge')
            ->assertSee('WF');
    }

    public function test_non_admin_only_sees_own_instances(): void
    {
        ['employee' => $employee, 'workflow' => $wf] = $this->bootstrap();
        $other = User::factory()->create();
        $other->assignRole('employee');

        app(WorkflowEngine::class)->start($wf, [], $employee);

        $this->actingAs($other)->get(route('workflow-instances.index'))
            ->assertOk()
            ->assertDontSee('#1');
    }

    public function test_instance_detail_shows_viewer_payload_and_history(): void
    {
        ['admin' => $admin, 'employee' => $employee, 'workflow' => $wf] = $this->bootstrap();
        $instance = app(WorkflowEngine::class)->start($wf, [], $employee);

        $this->actingAs($admin)->get(route('workflow-instances.show', $instance))
            ->assertOk()
            ->assertSee('viewer-payload')
            ->assertSee('Pruefen')
            ->assertSee('completed_step_keys', false);
    }

    public function test_admin_can_cancel_a_running_instance(): void
    {
        ['admin' => $admin, 'employee' => $employee, 'workflow' => $wf] = $this->bootstrap();
        $instance = app(WorkflowEngine::class)->start($wf, [], $employee);

        $this->actingAs($admin)
            ->post(route('workflow-instances.cancel', $instance), ['reason' => 'Test-Abbruch'])
            ->assertRedirect();

        $instance->refresh();
        $this->assertSame('cancelled', $instance->status);
        $this->assertNotNull($instance->completed_at);

        $openSteps = $instance->stepExecutions()->whereNull('completed_at')->count();
        $this->assertSame(0, $openSteps);
    }

    public function test_employee_cannot_cancel_an_instance(): void
    {
        ['employee' => $employee, 'workflow' => $wf] = $this->bootstrap();
        $instance = app(WorkflowEngine::class)->start($wf, [], $employee);

        $this->actingAs($employee)
            ->post(route('workflow-instances.cancel', $instance), ['reason' => 'nope'])
            ->assertForbidden();

        $this->assertSame('running', $instance->fresh()->status);
    }

    public function test_completed_instance_cannot_be_cancelled_again(): void
    {
        ['admin' => $admin, 'workflow' => $wf] = $this->bootstrap();
        $instance = WorkflowInstance::create([
            'workflow_id' => $wf->id,
            'workflow_version_id' => $wf->current_version_id,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('workflow-instances.cancel', $instance))
            ->assertSessionHasErrors('cancel');
    }
}
