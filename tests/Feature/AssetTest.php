<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AssetTest extends TestCase
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
        return compact('admin', 'employee');
    }

    public function test_due_asset_starts_associated_workflow(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();

        $def = ['drawflow' => ['Home' => ['data' => [
            '1' => ['id'=>1,'name'=>'start','class'=>'start','data'=>[],'inputs'=>[],'outputs'=>['output_1'=>['connections'=>[['node'=>'2','output'=>'input_1']]]]],
            '2' => ['id'=>2,'name'=>'approval','class'=>'approval','data'=>['label'=>'Pruefen','recipient_type'=>'supervisor_of_initiator'],'inputs'=>['input_1'=>[]],'outputs'=>['output_1'=>['connections'=>[]],'output_2'=>['connections'=>[]]]],
        ]]]];
        $wf = Workflow::create(['name'=>'Fuehrerschein','slug'=>'fs','trigger_type'=>'recurring','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>$def,'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        $asset = Asset::create([
            'name' => 'Fuehrerschein Klasse B', 'type' => 'fuehrerschein',
            'user_id' => $employee->id,
            'valid_until' => now()->addDays(15)->toDateString(),
            'lead_time_days' => 30,
            'status' => 'active',
            'workflow_id' => $wf->id,
        ]);

        $this->artisan('asset:check-due')->assertSuccessful();

        $this->assertSame(1, $wf->instances()->count());
        $instance = $wf->instances()->first();
        $this->assertSame($employee->id, $instance->started_by);
        $this->assertSame($asset->id, $instance->data['asset_id']);

        // Assignee = supervisor (admin)
        $this->assertSame($admin->id, $instance->stepExecutions()->first()->assigned_to_user_id);

        // Re-run does not duplicate
        $this->artisan('asset:check-due');
        $this->assertSame(1, $wf->instances()->count());
    }

    public function test_far_future_asset_is_not_due(): void
    {
        ['admin' => $admin, 'employee' => $employee] = $this->bootstrap();
        $wf = Workflow::create(['name'=>'X','slug'=>'x','trigger_type'=>'recurring','status'=>'active','created_by'=>$admin->id,'updated_by'=>$admin->id]);
        $v = WorkflowVersion::create(['workflow_id'=>$wf->id,'version_number'=>1,'definition'=>['drawflow'=>['Home'=>['data'=>[]]]],'created_by'=>$admin->id]);
        $wf->forceFill(['current_version_id'=>$v->id])->save();

        Asset::create([
            'name' => 'X', 'type' => 'x', 'user_id' => $employee->id,
            'valid_until' => now()->addYear()->toDateString(),
            'lead_time_days' => 30,
            'workflow_id' => $wf->id, 'status' => 'active',
        ]);

        $this->artisan('asset:check-due')->assertSuccessful();
        $this->assertSame(0, $wf->instances()->count());
    }

    public function test_csv_import_creates_assets(): void
    {
        ['employee' => $employee, 'admin' => $admin] = $this->bootstrap();
        $u2 = User::factory()->create(['email' => 'alice@example.com']);

        $csv = "user_email;name;type;valid_until;lead_time_days\n"
             . "{$employee->email};Fuehrerschein;fuehrerschein;2030-01-15;30\n"
             . "alice@example.com;Stapler;ladevorrichtung;2027-06-01;14\n"
             . "unknown@example.com;Ghost;ghost;2030-01-01;30\n";
        $file = UploadedFile::fake()->createWithContent('assets.csv', $csv);

        $this->actingAs($admin)
            ->post(route('assets.import'), ['csv' => $file, 'delimiter' => ';'])
            ->assertRedirect();

        $this->assertSame(2, Asset::count());
        $this->assertNotNull(Asset::where('user_id', $employee->id)->where('type', 'fuehrerschein')->first());
        $this->assertNotNull(Asset::where('user_id', $u2->id)->where('type', 'ladevorrichtung')->first());
    }
}
