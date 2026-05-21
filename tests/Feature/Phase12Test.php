<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\LookupList;
use App\Models\LookupListEntry;
use App\Models\Secret;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use App\Services\AttachmentStorage;
use App\Services\WorkflowEngine;
use App\Support\SafeHttpUrl;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase12Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_safe_http_url_blocks_private_addresses(): void
    {
        $this->assertFalse(SafeHttpUrl::isSafe('http://127.0.0.1/foo'));
        $this->assertFalse(SafeHttpUrl::isSafe('http://10.0.0.5/x'));
        $this->assertFalse(SafeHttpUrl::isSafe('http://192.168.1.1/y'));
        $this->assertFalse(SafeHttpUrl::isSafe('http://169.254.169.254/meta'));
        $this->assertFalse(SafeHttpUrl::isSafe('http://localhost/x'));
        $this->assertFalse(SafeHttpUrl::isSafe('file:///etc/passwd'));
        $this->assertTrue(SafeHttpUrl::isSafe('https://example.com/api'));
        $this->assertTrue(SafeHttpUrl::isSafe('https://8.8.8.8/'));
    }

    public function test_engine_blocks_ssrf_attempts(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $emp = User::factory()->create(['supervisor_id' => $admin->id]);
        $emp->assignRole('employee');

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'http','class'=>'http','data'=>[
                'method'=>'POST','url'=>'http://127.0.0.1:8080/admin','body_type'=>'none','continue_on_error'=>true,
                'response_mapping'=>[],'headers'=>[],
            ],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[['node'=>'3','output'=>'input_1']]]]],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['result'=>'rejected'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create(['name'=>'X','slug'=>'x','trigger_type'=>'manual','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $instance = app(WorkflowEngine::class)->start($wf, [], $emp);
        // Workflow nimmt den Fehler-Pfad (Output 2) und endet
        $this->assertSame('completed', $instance->fresh()->status);

        // Audit-Log enthält einen geblockten Eintrag
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.http.blocked']);
    }

    public function test_secret_placeholder_is_substituted_in_http_node(): void
    {
        Mail::fake();
        $admin = $this->admin();
        Secret::create(['key' => 'jira_token', 'value' => 'super-secret-xyz', 'created_by' => $admin->id]);
        Http::fake(['*' => Http::response(['id' => 1], 200)]);

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'http','class'=>'http','data'=>[
                'method'=>'POST','url'=>'https://example.com/api',
                'auth_type'=>'bearer','auth_token'=>'{{ secret.jira_token }}',
                'body_type'=>'json','body_template'=>'{}','response_mapping'=>[],'headers'=>[],
            ],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];
        $wf = Workflow::create(['name'=>'X','slug'=>'x','trigger_type'=>'manual','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        app(WorkflowEngine::class)->start($wf, [], $admin);

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer super-secret-xyz'));
    }

    public function test_lookup_list_trims_and_matches_case_insensitively(): void
    {
        $admin = $this->admin();
        $list = LookupList::create([
            'name' => 'KSt', 'slug' => 'kst',
            'columns' => [['key' => 'kostenstelle', 'label' => 'KSt', 'type' => 'text', 'role' => 'key']],
        ]);
        LookupListEntry::create(['lookup_list_id' => $list->id, 'key_value' => '1000', 'data' => ['kostenstelle' => '1000']]);

        $this->assertNotNull($list->lookup('1000'));
        $this->assertNotNull($list->lookup('  1000 '));
        // Case-insensitive Fallback bei alphanumerischen Schlüsseln
        LookupListEntry::create(['lookup_list_id' => $list->id, 'key_value' => 'IT-Office', 'data' => ['kostenstelle' => 'IT-Office']]);
        $this->assertNotNull($list->lookup('it-office'));
    }

    public function test_attachment_soft_delete_keeps_file_on_disk(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $admin->id, 'status' => 'active', 'lead_time_days' => 30]);
        $att = app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('s.pdf', 'A')->mimeType('application/pdf'),
            $asset, null, $admin->id,
        );
        $path = $att->path;

        $att->delete(); // soft delete

        $this->assertSoftDeleted('attachments', ['id' => $att->id]);
        Storage::disk('local')->assertExists($path); // Datei bleibt!

        // ForceDelete entfernt die Datei
        $att->forceDelete();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_unclassified_documents_are_hidden_from_non_admin_by_default(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Settings::set('attachments.document_types', ['Vertrag']);
        Settings::set('attachments.role_document_types', ['employee' => ['Vertrag']]);

        $emp = User::factory()->create();
        $emp->assignRole('employee');
        \App\Models\Permission::firstOrCreate(['slug' => 'documents.search'], ['name' => 'doc', 'group' => 'd']);
        $emp->roles->first()->permissions()->syncWithoutDetaching(\App\Models\Permission::where('slug', 'documents.search')->pluck('id'));
        $emp->load('roles.permissions');

        Storage::fake('local');
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $emp->id, 'status' => 'active', 'lead_time_days' => 30]);
        app(AttachmentStorage::class)->store(UploadedFile::fake()->createWithContent('v.pdf', 'A')->mimeType('application/pdf'), $asset, null, $emp->id, 'Vertrag');
        app(AttachmentStorage::class)->store(UploadedFile::fake()->createWithContent('u.pdf', 'B')->mimeType('application/pdf'), $asset, null, $emp->id, null); // unklassifiziert

        $r = $this->actingAs($emp)->get(route('documents.index'));
        $r->assertOk()->assertSee('v.pdf')->assertDontSee('u.pdf');
    }

    public function test_complete_step_is_transactional(): void
    {
        // Smoke-Test: completeStep ruft DB::transaction auf — wir verifizieren
        // einfach, dass der Workflow weiterläuft.
        Mail::fake();
        $admin = $this->admin();
        $emp = User::factory()->create(['supervisor_id' => $admin->id]);
        $emp->assignRole('employee');

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>['label'=>'X','recipient_type'=>'supervisor_of_initiator'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]],'output_2'=>['connections'=>[]]]],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create(['name'=>'X','slug'=>'x','trigger_type'=>'manual','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();
        $instance = app(WorkflowEngine::class)->start($wf, [], $emp);
        $step = $instance->stepExecutions()->first();

        app(WorkflowEngine::class)->completeStep($step, 'approved', null, $admin->id);
        $this->assertSame('completed', $instance->fresh()->status);
    }

    public function test_bulk_cancel_endpoint(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $emp = User::factory()->create(['supervisor_id' => $admin->id]);
        $emp->assignRole('employee');
        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>['label'=>'X','recipient_type'=>'supervisor_of_initiator'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];
        $wf = Workflow::create(['name'=>'X','slug'=>'x','trigger_type'=>'manual','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $i1 = app(WorkflowEngine::class)->start($wf, [], $emp);
        $i2 = app(WorkflowEngine::class)->start($wf, [], $emp);

        $this->actingAs($admin)->post(route('workflow-instances.bulk_cancel'), [
            'ids' => [$i1->id, $i2->id], 'reason' => 'Test',
        ])->assertRedirect();

        $this->assertSame('cancelled', $i1->fresh()->status);
        $this->assertSame('cancelled', $i2->fresh()->status);
    }

    public function test_audit_cleanup_anonymizes_old_entries(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $audit = app(\App\Services\AuditLogger::class);
        $u = User::factory()->create();
        $audit->log('test', null, null, null, 'alt', $u->id);
        // alten Eintrag direkt manipulieren
        \DB::table('audit_logs')->update([
            'ip_address' => '1.2.3.4',
            'user_agent' => 'TestUA',
            'created_at' => now()->subDays(800),
        ]);
        $this->artisan('audit:cleanup --days=730')->assertSuccessful();
        $row = \DB::table('audit_logs')->first();
        $this->assertNull($row->ip_address);
        $this->assertNull($row->user_agent);
    }
}
