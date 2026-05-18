<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Webhook;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase9Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_http_node_calls_external_api_and_maps_response(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $emp = User::factory()->create(['supervisor_id' => $admin->id]);
        $emp->assignRole('employee');

        Http::fake([
            'tickets.example.com/*' => Http::response(['id' => 4711, 'key' => 'IT-4711'], 201),
        ]);

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'http','class'=>'http','data'=>[
                'label'=>'Ticket','method'=>'POST',
                'url'=>'https://tickets.example.com/api/tickets',
                'auth_type'=>'bearer','auth_token'=>'abc-secret',
                'headers'=>[['key'=>'X-Source','value'=>'OWE']],
                'body_type'=>'json',
                'body_template'=>'{"title":"WF #{{ instance_id }}","desc":"{{ beschreibung }}","email":"{{ initiator_email }}"}',
                'response_mapping'=>[['path'=>'id','save_as'=>'ticket_id'],['path'=>'key','save_as'=>'ticket_key']],
                'timeout_seconds'=>10, 'continue_on_error'=>false,
            ], 'inputs'=>['input_1'=>[]], 'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]],'output_2'=>['connections'=>[]]]],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create(['name'=>'X','slug'=>'x','trigger_type'=>'form','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id])
            ->forceFill([])->save();
        $wf->forceFill(['current_version_id'=>$wf->versions()->first()->id])->save();

        $instance = app(WorkflowEngine::class)->start($wf, ['beschreibung' => 'Drucker-Toner leer'], $emp);

        $instance->refresh();
        $this->assertSame('completed', $instance->status);
        $this->assertSame(4711, $instance->data['ticket_id']);
        $this->assertSame('IT-4711', $instance->data['ticket_key']);

        Http::assertSent(function ($req) use ($emp) {
            $body = json_decode($req->body(), true);
            return $req->url() === 'https://tickets.example.com/api/tickets'
                && $req->method() === 'POST'
                && $req->hasHeader('Authorization', 'Bearer abc-secret')
                && $req->hasHeader('X-Source', 'OWE')
                && $body['email'] === $emp->email
                && str_contains($body['title'], '#');
        });
    }

    public function test_http_node_fails_to_error_branch_when_4xx(): void
    {
        Mail::fake();
        $admin = $this->admin();
        Http::fake(['*' => Http::response(['error' => 'auth'], 403)]);

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'http','class'=>'http','data'=>[
                'method'=>'POST','url'=>'https://x.test/y','body_type'=>'json','body_template'=>'{}','continue_on_error'=>true,
                'response_mapping'=>[],'headers'=>[],
            ], 'inputs'=>['input_1'=>[]], 'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[['node'=>'3','output'=>'input_1']]]]],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['result'=>'rejected'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create(['name'=>'X','slug'=>'xe','trigger_type'=>'manual','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $instance = app(WorkflowEngine::class)->start($wf, [], $admin);
        $this->assertSame('completed', $instance->fresh()->status);
    }

    public function test_webhook_fires_on_instance_started_with_hmac(): void
    {
        Mail::fake();
        $admin = $this->admin();
        Http::fake();

        Webhook::create([
            'name' => 'CRM', 'url' => 'https://hooks.example.com/owe',
            'events' => [Webhook::EVENT_INSTANCE_STARTED],
            'secret' => 'super-secret', 'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'end','class'=>'end','data'=>['result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create(['name'=>'W','slug'=>'w','trigger_type'=>'manual','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        app(WorkflowEngine::class)->start($wf, ['foo' => 'bar'], $admin);

        Http::assertSent(function ($req) {
            if ($req->url() !== 'https://hooks.example.com/owe') return false;
            $sig = $req->header('X-OWE-Signature');
            $expected = 'sha256='.hash_hmac('sha256', $req->body(), 'super-secret');
            return $sig && in_array($expected, $sig, true);
        });
    }

    public function test_custom_user_fields_are_persisted_and_available_in_context(): void
    {
        Mail::fake();
        $admin = $this->admin();
        Settings::set('users.custom_fields', [
            ['key' => 'kostenstelle', 'label' => 'KSt', 'type' => 'text', 'options' => []],
        ]);

        // Admin updates user with custom field
        $emp = User::factory()->create(['supervisor_id' => $admin->id]);
        $emp->assignRole('employee');

        $this->actingAs($admin)->put(route('admin.users.update', $emp), [
            'name' => $emp->name, 'email' => $emp->email,
            'supervisor_id' => $admin->id, 'is_active' => '1',
            'custom_fields' => ['kostenstelle' => '1000'],
        ])->assertRedirect();

        $emp->refresh();
        $this->assertSame('1000', $emp->custom_fields['kostenstelle']);
    }

    public function test_help_pages_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('help.index'))->assertOk()->assertSee('Open Workflow Engine');
        $this->actingAs($admin)->get(route('help.show', 'http-node'))->assertOk()->assertSee('HTTP-Knoten');
        $this->actingAs($admin)->get(route('help.show', 'invalid'))->assertNotFound();
    }

    public function test_reminder_command_sends_mail_within_24h(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $emp = User::factory()->create(['supervisor_id' => $admin->id]);
        $emp->assignRole('employee');

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>['label'=>'X','recipient_type'=>'supervisor_of_initiator','grace_value'=>2,'grace_unit'=>'days'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];
        $wf = Workflow::create(['name'=>'W','slug'=>'w','trigger_type'=>'manual','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $instance = app(WorkflowEngine::class)->start($wf, [], $emp);
        $step = $instance->stepExecutions()->first();
        // Frist in 12 Stunden (innerhalb 24h-Reminder-Fenster)
        $step->forceFill(['due_at' => now()->addHours(12)])->save();

        Mail::fake(); // reset
        $this->artisan('workflow:check-due')->assertSuccessful();
        $this->assertNotNull($step->fresh()->reminder_sent_at);
        Mail::assertSent(\App\Mail\WorkflowTaskAssignedMail::class);
    }
}
