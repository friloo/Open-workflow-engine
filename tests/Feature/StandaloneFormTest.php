<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandaloneFormTest extends TestCase
{
    use RefreshDatabase;

    private function designer(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('workflow-designer');
        return $u;
    }

    public function test_designer_can_create_and_persist_a_form(): void
    {
        $u = $this->designer();
        $this->actingAs($u)->post(route('forms.store'), [
            'name' => 'Krankmeldung',
            'description' => 'kurze Beschreibung',
            'is_public' => '1',
            'public_slug' => 'krankmeldung',
            'schema' => [
                ['key' => 'name', 'label' => 'Vor- und Nachname', 'type' => 'text', 'required' => '1'],
                ['key' => 'tage', 'label' => 'Krankheitstage', 'type' => 'number', 'required' => '1'],
            ],
        ])->assertRedirect();

        $form = Form::firstOrFail();
        $this->assertSame('krankmeldung', $form->public_slug);
        $this->assertCount(2, $form->schema);
        $this->assertTrue($form->is_public);
    }

    public function test_public_form_submission_stores_data(): void
    {
        $this->designer();
        $form = Form::create([
            'name' => 'Krankmeldung', 'slug' => 'krankmeldung', 'public_slug' => 'krankmeldung',
            'is_public' => true, 'schema' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ],
        ]);

        $this->get('/formular/krankmeldung')->assertOk()->assertSee('Krankmeldung');
        $this->post('/formular/krankmeldung', ['name' => 'Max'])->assertRedirect('/formular/krankmeldung/danke');

        $this->assertDatabaseHas('form_submissions', ['form_id' => $form->id]);
    }

    public function test_form_submission_starts_linked_workflow(): void
    {
        $this->designer();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'end','class'=>'end','data'=>['result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create([
            'name' => 'Krankmeldung-WF', 'slug' => 'krankmeldung-wf',
            'trigger_type' => 'form', 'status' => 'active',
            'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $v = WorkflowVersion::create(['workflow_id' => $wf->id, 'version_number' => 1, 'definition' => $def, 'created_by' => $admin->id]);
        $wf->forceFill(['current_version_id' => $v->id])->save();

        $form = Form::create([
            'name' => 'Krankmeldung', 'slug' => 'krank', 'public_slug' => 'krank',
            'is_public' => true, 'workflow_id' => $wf->id,
            'schema' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]],
        ]);

        $this->post('/formular/krank', ['name' => 'Max'])->assertRedirect();
        $this->assertSame(1, $wf->instances()->count());
        $this->assertSame('completed', $wf->instances()->first()->status);
    }

    public function test_internal_only_form_is_not_public(): void
    {
        $this->designer();
        Form::create([
            'name' => 'Geheim', 'slug' => 'geheim', 'public_slug' => null,
            'is_public' => false, 'schema' => [],
        ]);

        $this->get('/formular/geheim')->assertNotFound();
    }
}
