<?php

namespace Tests\Feature;

use App\Models\LookupList;
use App\Models\LookupListEntry;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LookupListTest extends TestCase
{
    use RefreshDatabase;

    private function designer(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('workflow-designer');
        return $u;
    }

    private function kostenstellenListe(): LookupList
    {
        return LookupList::create([
            'name' => 'Kostenstellen', 'slug' => 'kostenstellen',
            'columns' => [
                ['key' => 'kostenstelle', 'label' => 'Kostenstelle', 'type' => 'text', 'role' => 'key'],
                ['key' => 'responsible_email', 'label' => 'Verantwortlich', 'type' => 'email', 'role' => 'responsible'],
                ['key' => 'escalation_email', 'label' => 'Eskalation', 'type' => 'email', 'role' => 'escalation'],
            ],
        ]);
    }

    public function test_designer_can_create_a_list_with_columns(): void
    {
        $u = $this->designer();
        $this->actingAs($u)->post(route('lists.store'), [
            'name' => 'Kostenstellen',
            'description' => 'Verantwortliche je KSt',
            'columns' => [
                ['key' => 'kostenstelle', 'label' => 'KSt', 'type' => 'text', 'role' => 'key'],
                ['key' => 'responsible_email', 'label' => 'Verantw.', 'type' => 'email', 'role' => 'responsible'],
                ['key' => 'escalation_email', 'label' => 'Eskal.', 'type' => 'email', 'role' => 'escalation'],
            ],
        ])->assertRedirect();

        $list = LookupList::firstOrFail();
        $this->assertSame('Kostenstellen', $list->name);
        $this->assertCount(3, $list->columns);
    }

    public function test_csv_import_creates_and_updates_entries(): void
    {
        $u = $this->designer();
        $list = $this->kostenstellenListe();

        $csv = "kostenstelle;responsible_email;escalation_email\n"
             . "1000;chef-it@example.com;cio@example.com\n"
             . "2000;chef-office@example.com;coo@example.com\n";
        $file = UploadedFile::fake()->createWithContent('kst.csv', $csv);

        $this->actingAs($u)
            ->post(route('lists.entries.import', $list), ['csv' => $file, 'delimiter' => ';'])
            ->assertRedirect();

        $this->assertSame(2, $list->entries()->count());
        $this->assertSame('chef-it@example.com', $list->lookup('1000')['responsible_email']);

        // Re-import with update
        $csv2 = "kostenstelle;responsible_email;escalation_email\n"
              . "1000;new-it@example.com;cio@example.com\n";
        $file2 = UploadedFile::fake()->createWithContent('kst2.csv', $csv2);
        $this->actingAs($u)
            ->post(route('lists.entries.import', $list), ['csv' => $file2])
            ->assertRedirect();

        $this->assertSame(2, $list->entries()->count());
        $this->assertSame('new-it@example.com', $list->fresh()->lookup('1000')['responsible_email']);
    }

    public function test_engine_resolves_recipient_via_list_lookup(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create(['supervisor_id' => $admin->id]);
        $employee->assignRole('employee');
        $costCenterLead = User::factory()->create(['email' => 'chef-it@example.com']);
        $costCenterLead->assignRole('employee');
        $escalationLead = User::factory()->create(['email' => 'cio@example.com']);

        $list = $this->kostenstellenListe();
        LookupListEntry::create([
            'lookup_list_id' => $list->id, 'key_value' => '1000',
            'data' => [
                'kostenstelle' => '1000',
                'responsible_email' => 'chef-it@example.com',
                'escalation_email' => 'cio@example.com',
            ],
        ]);

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>[
                    'label'=>'Genehmigen',
                    'recipient_type'=>'list_lookup',
                    'list_id' => $list->id,
                    'lookup_source' => 'kostenstelle',
                    'grace_value' => 1, 'grace_unit' => 'hours',
                    'escalation_type' => 'list_lookup',
                ],
                'inputs'=>['input_1'=>[]],
                'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];

        $wf = Workflow::create(['name'=>'KSt-WF','slug'=>'kst-wf','trigger_type'=>'form','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'form_schema'=>[['key'=>'kostenstelle','label'=>'KSt','type'=>'text']],'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $instance = app(WorkflowEngine::class)->start($wf, ['kostenstelle' => '1000'], $employee);

        $this->assertSame(1, $instance->stepExecutions()->count());
        $step = $instance->stepExecutions()->first();
        $this->assertSame($costCenterLead->id, $step->assigned_to_user_id);

        // Trigger escalation
        $step->forceFill(['due_at' => now()->subHours(2)])->save();
        $this->artisan('workflow:check-due')->assertSuccessful();

        $instance->refresh();
        $newStep = $instance->stepExecutions()->orderByDesc('id')->first();
        $this->assertSame($escalationLead->id, $newStep->assigned_to_user_id);
    }

    public function test_unknown_key_results_in_empty_assignee(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $list = $this->kostenstellenListe(); // empty

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>[
                    'label'=>'X','recipient_type'=>'list_lookup',
                    'list_id' => $list->id, 'lookup_source' => 'kst',
                ],
                'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];
        $wf = Workflow::create(['name'=>'X','slug'=>'x','trigger_type'=>'form','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'form_schema'=>[['key'=>'kst','label'=>'KSt','type'=>'text']],'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $instance = app(WorkflowEngine::class)->start($wf, ['kst' => '9999'], $employee);
        // Step is created but with no assignee
        $step = $instance->stepExecutions()->first();
        $this->assertNotNull($step);
        $this->assertNull($step->assigned_to_user_id);
        $this->assertNull($step->assigned_to_role_id);
    }
}
