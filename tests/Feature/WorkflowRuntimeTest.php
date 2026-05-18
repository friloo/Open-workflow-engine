<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrap(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $admin = User::factory()->create(['email' => 'boss@example.com']);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['email' => 'employee@example.com', 'supervisor_id' => $admin->id]);
        $employee->assignRole('employee');

        return compact('admin', 'employee');
    }

    private function createWorkflow(array $definition, array $formSchema = [], bool $public = false, string $slug = 'bestellantrag'): Workflow
    {
        $wf = Workflow::create([
            'name' => 'Bestellantrag',
            'slug' => $slug,
            'trigger_type' => 'form',
            'status' => Workflow::STATUS_ACTIVE,
            'created_by' => 1,
            'updated_by' => 1,
        ]);
        $version = WorkflowVersion::create([
            'workflow_id' => $wf->id,
            'version_number' => 1,
            'definition' => $definition,
            'form_schema' => $formSchema,
            'created_by' => 1,
        ]);
        $wf->forceFill(['current_version_id' => $version->id])->save();

        if ($public) {
            \App\Models\Form::create([
                'name' => $wf->name, 'slug' => 'public-'.$slug, 'public_slug' => $slug,
                'is_public' => true, 'workflow_id' => $wf->id,
                'schema' => $formSchema,
            ]);
        }
        return $wf->fresh('currentVersion');
    }

    private function definition(array $nodes): array
    {
        return ['drawflow' => ['Home' => ['data' => $nodes]]];
    }

    public function test_simple_approval_flow_creates_a_task_for_supervisor(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();

        $def = $this->definition([
            '1' => ['id' => 1, 'name' => 'start', 'class' => 'start', 'data' => ['label' => 'Start'],
                'inputs' => [], 'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]]],
            '2' => ['id' => 2, 'name' => 'approval', 'class' => 'approval',
                'data' => ['label' => 'Vorgesetzter pruefen', 'recipient_type' => 'supervisor_of_initiator', 'grace_value' => 2, 'grace_unit' => 'days'],
                'inputs' => ['input_1' => []], 'outputs' => [
                    'output_1' => ['connections' => [['node' => '3', 'output' => 'input_1']]],
                    'output_2' => ['connections' => [['node' => '3', 'output' => 'input_1']]],
                ]],
            '3' => ['id' => 3, 'name' => 'end', 'class' => 'end', 'data' => ['result' => 'completed'],
                'inputs' => ['input_1' => []], 'outputs' => []],
        ]);
        $wf = $this->createWorkflow($def);

        $instance = app(WorkflowEngine::class)->start($wf, ['betrag' => 100], $employee);

        $this->assertSame('running', $instance->status);
        $this->assertSame(1, $instance->stepExecutions()->count());
        $task = $instance->stepExecutions()->first();
        $this->assertSame($admin->id, $task->assigned_to_user_id);
        $this->assertNotNull($task->due_at);

        Mail::assertSent(\App\Mail\WorkflowTaskAssignedMail::class);
    }

    public function test_conditional_branch_routes_by_form_value(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();
        $itLead = User::factory()->create(['email' => 'it@example.com']);
        $officeLead = User::factory()->create(['email' => 'office@example.com']);

        $def = $this->definition([
            '1' => ['id' => 1, 'name' => 'start', 'class' => 'start', 'data' => ['label' => 'Start'],
                'inputs' => [], 'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]]],
            '2' => ['id' => 2, 'name' => 'condition', 'class' => 'condition',
                'data' => ['label' => 'Nach Kategorie verzweigen', 'branches' => [
                    ['field' => 'kategorie', 'operator' => 'eq', 'value' => 'IT'],
                    ['field' => 'kategorie', 'operator' => 'eq', 'value' => 'Office'],
                ]],
                'inputs' => ['input_1' => []], 'outputs' => [
                    'output_1' => ['connections' => [['node' => '3', 'output' => 'input_1']]],
                    'output_2' => ['connections' => [['node' => '4', 'output' => 'input_1']]],
                    'output_3' => ['connections' => [['node' => '5', 'output' => 'input_1']]],
                ]],
            '3' => ['id' => 3, 'name' => 'approval', 'class' => 'approval',
                'data' => ['label' => 'IT', 'recipient_type' => 'user', 'recipient_user_id' => $itLead->id],
                'inputs' => ['input_1' => []], 'outputs' => ['output_1' => ['connections' => []]]],
            '4' => ['id' => 4, 'name' => 'approval', 'class' => 'approval',
                'data' => ['label' => 'Office', 'recipient_type' => 'user', 'recipient_user_id' => $officeLead->id],
                'inputs' => ['input_1' => []], 'outputs' => ['output_1' => ['connections' => []]]],
            '5' => ['id' => 5, 'name' => 'end', 'class' => 'end', 'data' => ['result' => 'completed'],
                'inputs' => ['input_1' => []], 'outputs' => []],
        ]);
        $wf = $this->createWorkflow($def, [
            ['key' => 'kategorie', 'type' => 'select', 'label' => 'Kategorie', 'options' => ['IT', 'Office', 'Marketing']],
        ]);

        $engine = app(WorkflowEngine::class);
        $instance = $engine->start($wf, ['kategorie' => 'IT'], $employee);
        $this->assertSame($itLead->id, $instance->stepExecutions()->first()->assigned_to_user_id);

        $instance2 = $engine->start($wf, ['kategorie' => 'Office'], $employee);
        $this->assertSame($officeLead->id, $instance2->stepExecutions()->first()->assigned_to_user_id);

        $instance3 = $engine->start($wf, ['kategorie' => 'Marketing'], $employee);
        // No branch matched → else (output_3) → end → completed without task
        $this->assertSame('completed', $instance3->status);
        $this->assertSame(0, $instance3->stepExecutions()->count());
    }

    public function test_approval_continues_after_decision(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();
        $def = $this->definition([
            '1' => ['id' => 1, 'name' => 'start', 'class' => 'start', 'data' => [],
                'inputs' => [], 'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]]],
            '2' => ['id' => 2, 'name' => 'approval', 'class' => 'approval',
                'data' => ['label' => 'Pruefen', 'recipient_type' => 'supervisor_of_initiator'],
                'inputs' => ['input_1' => []], 'outputs' => [
                    'output_1' => ['connections' => [['node' => '3', 'output' => 'input_1']]],
                    'output_2' => ['connections' => [['node' => '4', 'output' => 'input_1']]],
                ]],
            '3' => ['id' => 3, 'name' => 'end', 'class' => 'end', 'data' => ['result' => 'completed'],
                'inputs' => ['input_1' => []], 'outputs' => []],
            '4' => ['id' => 4, 'name' => 'end', 'class' => 'end', 'data' => ['result' => 'rejected'],
                'inputs' => ['input_1' => []], 'outputs' => []],
        ]);
        $wf = $this->createWorkflow($def);
        $engine = app(WorkflowEngine::class);

        $instance = $engine->start($wf, [], $employee);
        $step = $instance->stepExecutions()->first();
        $engine->completeStep($step, 'approved', 'OK', $admin->id);

        $this->assertSame('completed', $instance->fresh()->status);
        $this->assertSame('approved', $step->fresh()->decision);
    }

    public function test_overdue_escalation_creates_a_new_step_for_the_target_role(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();
        $escalationRole = Role::where('slug', 'admin')->first();

        $def = $this->definition([
            '1' => ['id' => 1, 'name' => 'start', 'class' => 'start', 'data' => [],
                'inputs' => [], 'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]]],
            '2' => ['id' => 2, 'name' => 'approval', 'class' => 'approval',
                'data' => [
                    'label' => 'Pruefen',
                    'recipient_type' => 'user',
                    'recipient_user_id' => $admin->id,
                    'grace_value' => 1, 'grace_unit' => 'hours',
                    'escalation_type' => 'role', 'escalation_role_id' => $escalationRole->id,
                ],
                'inputs' => ['input_1' => []], 'outputs' => ['output_1' => ['connections' => []]]],
        ]);
        $wf = $this->createWorkflow($def);
        $engine = app(WorkflowEngine::class);

        $instance = $engine->start($wf, [], $employee);
        $step = $instance->stepExecutions()->first();
        $step->forceFill(['due_at' => now()->subHours(2)])->save();

        $this->artisan('workflow:check-due')->assertSuccessful();

        $instance->refresh();
        $steps = $instance->stepExecutions()->orderBy('id')->get();
        $this->assertSame(2, $steps->count());
        $this->assertSame('escalated', $steps[0]->decision);
        $this->assertSame($escalationRole->id, $steps[1]->assigned_to_role_id);
        $this->assertSame($steps[0]->id, $steps[1]->escalated_from_step_id);
    }

    public function test_public_form_can_be_submitted_without_login(): void
    {
        $this->bootstrap();
        $def = $this->definition([
            '1' => ['id' => 1, 'name' => 'start', 'class' => 'start', 'data' => [],
                'inputs' => [], 'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]]],
            '2' => ['id' => 2, 'name' => 'end', 'class' => 'end', 'data' => ['result' => 'completed'],
                'inputs' => ['input_1' => []], 'outputs' => []],
        ]);
        $wf = $this->createWorkflow(
            $def,
            [['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true]],
            public: true,
            slug: 'bestellung-extern',
        );

        $this->get('/formular/bestellung-extern')->assertOk()->assertSee('Bestellantrag');
        $this->post('/formular/bestellung-extern', ['name' => 'Anonyme Anfrage'])
            ->assertRedirect('/formular/bestellung-extern/danke');

        $this->assertDatabaseHas('workflow_instances', ['workflow_id' => $wf->id]);
        $this->assertDatabaseHas('form_submissions', ['workflow_instance_id' => WorkflowInstance::first()->id]);
    }

    public function test_user_can_complete_their_own_task_via_http(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();
        $def = $this->definition([
            '1' => ['id' => 1, 'name' => 'start', 'class' => 'start', 'data' => [],
                'inputs' => [], 'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]]],
            '2' => ['id' => 2, 'name' => 'approval', 'class' => 'approval',
                'data' => ['label' => 'Pruefen', 'recipient_type' => 'supervisor_of_initiator'],
                'inputs' => ['input_1' => []], 'outputs' => [
                    'output_1' => ['connections' => [['node' => '3', 'output' => 'input_1']]],
                    'output_2' => ['connections' => []],
                ]],
            '3' => ['id' => 3, 'name' => 'end', 'class' => 'end', 'data' => ['result' => 'completed'],
                'inputs' => ['input_1' => []], 'outputs' => []],
        ]);
        $wf = $this->createWorkflow($def);
        $instance = app(WorkflowEngine::class)->start($wf, [], $employee);
        $step = $instance->stepExecutions()->first();

        $this->actingAs($admin)
            ->post(route('tasks.decide', $step), ['decision' => 'approved', 'comment' => 'OK'])
            ->assertRedirect(route('tasks.index'));

        $this->assertSame('completed', $instance->fresh()->status);
    }

    public function test_user_cannot_decide_task_assigned_to_others(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();
        $other = User::factory()->create();

        $def = $this->definition([
            '1' => ['id' => 1, 'name' => 'start', 'class' => 'start', 'data' => [],
                'inputs' => [], 'outputs' => ['output_1' => ['connections' => [['node' => '2', 'output' => 'input_1']]]]],
            '2' => ['id' => 2, 'name' => 'approval', 'class' => 'approval',
                'data' => ['label' => 'X', 'recipient_type' => 'user', 'recipient_user_id' => $admin->id],
                'inputs' => ['input_1' => []], 'outputs' => ['output_1' => ['connections' => []]]],
        ]);
        $wf = $this->createWorkflow($def);
        $instance = app(WorkflowEngine::class)->start($wf, [], $employee);
        $step = $instance->stepExecutions()->first();

        $this->actingAs($other)
            ->post(route('tasks.decide', $step), ['decision' => 'approved'])
            ->assertForbidden();
    }
}
