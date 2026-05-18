<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_pdf_to_an_asset(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $holder = User::factory()->create();

        $asset = Asset::create([
            'name' => 'Fuehrerschein', 'type' => 'fuehrerschein',
            'user_id' => $holder->id, 'lead_time_days' => 30, 'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf');
        $this->actingAs($admin)
            ->post(route('attachments.store', ['type' => 'asset', 'id' => $asset->id]), [
                'file' => $file, 'label' => 'Vorderseite',
            ])
            ->assertRedirect();

        $att = $asset->fresh()->attachments()->first();
        $this->assertNotNull($att);
        $this->assertSame('scan.pdf', $att->original_name);
        $this->assertSame('application/pdf', $att->mime_type);
        $this->assertSame('Vorderseite', $att->label);
        Storage::disk('local')->assertExists($att->path);
    }

    public function test_holder_can_download_their_own_asset_attachment(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $holder = User::factory()->create(); $holder->assignRole('employee');

        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $holder->id, 'status' => 'active', 'lead_time_days' => 30]);
        $file = UploadedFile::fake()->create('s.pdf', 50, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store', ['type' => 'asset', 'id' => $asset->id]), ['file' => $file]);
        $att = $asset->fresh()->attachments()->first();

        $this->actingAs($holder)->get(route('attachments.download', $att))->assertOk();
    }

    public function test_unrelated_user_cannot_download_an_asset_attachment(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $holder = User::factory()->create();
        $stranger = User::factory()->create(); $stranger->assignRole('employee');

        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $holder->id, 'status' => 'active', 'lead_time_days' => 30]);
        $file = UploadedFile::fake()->create('s.pdf', 50, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store', ['type' => 'asset', 'id' => $asset->id]), ['file' => $file]);
        $att = $asset->fresh()->attachments()->first();

        $this->actingAs($stranger)->get(route('attachments.download', $att))->assertForbidden();
    }

    public function test_oversized_or_disallowed_file_is_rejected(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $holder = User::factory()->create();
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $holder->id, 'status' => 'active', 'lead_time_days' => 30]);

        // Disallowed MIME
        $exe = UploadedFile::fake()->createWithContent('virus.exe', 'MZ binary')->mimeType('application/x-msdownload');
        $this->actingAs($admin)
            ->post(route('attachments.store', ['type' => 'asset', 'id' => $asset->id]), ['file' => $exe])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $asset->attachments()->count());
    }

    public function test_due_asset_propagates_attachment_copy_to_workflow_instance(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $holder = User::factory()->create(['supervisor_id' => $admin->id]);
        $holder->assignRole('employee');

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>['label'=>'X','recipient_type'=>'supervisor_of_subject'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];
        $wf = Workflow::create(['name'=>'F','slug'=>'f','trigger_type'=>'recurring','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $asset = Asset::create([
            'name' => 'Fuehrerschein', 'type' => 'fuehrerschein',
            'user_id' => $holder->id, 'valid_until' => now()->addDays(15)->toDateString(),
            'lead_time_days' => 30, 'status' => 'active', 'workflow_id' => $wf->id,
        ]);

        // PDF an Asset haengen
        $file = UploadedFile::fake()->create('fs.pdf', 100, 'application/pdf');
        $this->actingAs($admin)->post(route('attachments.store', ['type' => 'asset', 'id' => $asset->id]), ['file' => $file]);

        $this->artisan('asset:check-due')->assertSuccessful();

        $instance = $wf->instances()->first();
        $this->assertNotNull($instance);
        $this->assertSame(1, $instance->attachments()->count());
        $this->assertStringStartsWith('Asset-Scan:', $instance->attachments()->first()->label);

        // Empfaenger sollte Vorgesetzter des Asset-Holders sein
        $this->assertSame($admin->id, $instance->stepExecutions()->first()->assigned_to_user_id);
    }

    public function test_notify_node_can_target_subject_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $holder = User::factory()->create(['supervisor_id' => $admin->id]);
        $holder->assignRole('employee');

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'notify','class'=>'notify','data'=>['label'=>'Mail an Inhaber','recipient_type'=>'subject_user','subject'=>'Bitte vorzeigen','body'=>'Hallo {{ subject_user_name }}, bitte zeige deinen Fuehrerschein.'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[['node'=>'3','output'=>'input_1']]]]],
            '3' => ['id'=>3,'name'=>'end','class'=>'end','data'=>['result'=>'completed'],'inputs'=>['input_1'=>[]],'outputs'=>[]],
        ]]]];
        $wf = Workflow::create(['name'=>'F','slug'=>'f','trigger_type'=>'recurring','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $asset = Asset::create([
            'name' => 'Fuehrerschein', 'type' => 'fuehrerschein',
            'user_id' => $holder->id, 'valid_until' => now()->addDays(10)->toDateString(),
            'lead_time_days' => 30, 'status' => 'active', 'workflow_id' => $wf->id,
        ]);

        $this->artisan('asset:check-due')->assertSuccessful();

        Mail::assertSent(\App\Mail\WorkflowNotificationMail::class, function ($mail) use ($holder) {
            return $mail->hasTo($holder->email);
        });
    }
}
