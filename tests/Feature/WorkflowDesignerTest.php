<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowDesignerTest extends TestCase
{
    use RefreshDatabase;

    private function designerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('workflow-designer');
        return $user;
    }

    public function test_designer_can_create_a_workflow(): void
    {
        $user = $this->designerUser();

        $this->actingAs($user)
            ->post('/workflows', [
                'name' => 'Bestellantrag',
                'description' => 'Bestellung freigeben',
                'trigger_type' => 'form',
                'is_public' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workflows', [
            'name' => 'Bestellantrag',
            'slug' => 'bestellantrag',
            'status' => 'draft',
            'trigger_type' => 'form',
        ]);
    }

    public function test_saving_creates_an_immutable_version_chain(): void
    {
        $user = $this->designerUser();
        $workflow = Workflow::create([
            'name' => 'Bestellantrag', 'slug' => 'bestellantrag',
            'trigger_type' => 'form', 'status' => 'draft',
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        $definition = [
            'drawflow' => ['Home' => ['data' => [
                '1' => ['id' => 1, 'name' => 'start', 'data' => ['label' => 'Start'], 'class' => 'start', 'html' => '', 'typenode' => false, 'inputs' => [], 'outputs' => [], 'pos_x' => 0, 'pos_y' => 0],
            ]]],
        ];
        $formSchema = [['key' => 'kat', 'label' => 'Kategorie', 'type' => 'text', 'required' => true]];

        $r = $this->actingAs($user)->postJson("/workflows/{$workflow->id}/design", [
            'definition' => $definition,
            'form_schema' => $formSchema,
            'change_summary' => 'Version 1',
        ]);
        $r->assertOk()->assertJsonPath('version_number', 1);

        // Saving again increments
        $r = $this->actingAs($user)->postJson("/workflows/{$workflow->id}/design", [
            'definition' => $definition,
            'form_schema' => $formSchema,
            'change_summary' => 'Version 2',
        ]);
        $r->assertOk()->assertJsonPath('version_number', 2);

        $this->assertSame(2, $workflow->versions()->count());
        $this->assertSame(2, $workflow->fresh()->currentVersion->version_number);
    }

    public function test_workflow_version_cannot_be_updated(): void
    {
        $user = $this->designerUser();
        $workflow = Workflow::create([
            'name' => 'X', 'slug' => 'x', 'trigger_type' => 'manual',
            'status' => 'draft', 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => []]]],
            'created_by' => $user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $version->update(['change_summary' => 'tamper']);
    }

    public function test_employee_cannot_access_designer(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $this->actingAs($employee)->get('/workflows/create')->assertForbidden();
    }

    public function test_validation_rejects_missing_definition(): void
    {
        $user = $this->designerUser();
        $workflow = Workflow::create([
            'name' => 'X', 'slug' => 'x', 'trigger_type' => 'form',
            'status' => 'draft', 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson("/workflows/{$workflow->id}/design", ['form_schema' => []])
            ->assertStatus(422);
    }

    public function test_activate_requires_a_saved_version(): void
    {
        $user = $this->designerUser();
        $user->assignRole('admin');
        $workflow = Workflow::create([
            'name' => 'X', 'slug' => 'x', 'trigger_type' => 'manual',
            'status' => 'draft', 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from("/workflows/{$workflow->id}/edit")
            ->post("/workflows/{$workflow->id}/activate")
            ->assertRedirect()
            ->assertSessionHasErrors('workflow');

        $this->assertSame('draft', $workflow->fresh()->status);
    }

    public function test_designer_page_includes_payload(): void
    {
        $user = $this->designerUser();
        $workflow = Workflow::create([
            'name' => 'X', 'slug' => 'x', 'trigger_type' => 'form',
            'status' => 'draft', 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get("/workflows/{$workflow->id}/design")
            ->assertOk()
            ->assertSee('designer-payload')
            ->assertSee('"trigger_type":"form"', false);
    }
}
