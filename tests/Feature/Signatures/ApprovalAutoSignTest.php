<?php

namespace Tests\Feature\Signatures;

use App\Models\Attachment;
use App\Models\Signature;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\SignatureService;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalAutoSignTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_node_with_ses_override_signs_pdf_on_approval(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Approval-Knoten mit explizitem SES-Override
        $wf = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => 'active']);
        $v = WorkflowVersion::create([
            'workflow_id' => $wf->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'a1' => ['id' => 'a1', 'class' => 'approval', 'data' => [
                    'label' => 'Freigabe',
                    'signature_level_override' => 'ses',
                    'signature_on' => 'approved',
                ]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $wf->update(['current_version_id' => $v->id]);

        $instance = WorkflowInstance::create([
            'workflow_id' => $wf->id, 'workflow_version_id' => $v->id,
            'status' => 'running', 'started_at' => now(),
        ]);

        // PDF-Anhang an die Instance
        $path = 'docs/'.Str::uuid().'.pdf';
        $bytes = '%PDF-1.4 fake content';
        Storage::disk('local')->put($path, $bytes);
        Attachment::create([
            'attachable_type' => $instance->getMorphClass(),
            'attachable_id' => $instance->id,
            'original_name' => 'rechnung.pdf', 'disk' => 'local', 'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => strlen($bytes), 'content_hash' => hash('sha256', $bytes),
            'version_chain_id' => (string) Str::uuid(),
            'version_number' => 1, 'is_current_version' => true,
        ]);

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'a1',
            'step_type' => 'approval', 'assigned_to_user_id' => $admin->id,
            'completed_at' => now(), 'completed_by' => $admin->id, 'decision' => 'approved',
        ]);

        $count = app(SignatureService::class)->maybeSignForStep($step, 'approved');
        $this->assertSame(1, $count);
        $this->assertDatabaseHas('signatures', [
            'workflow_step_execution_id' => $step->id,
            'level' => 'ses',
            'user_id' => $admin->id,
        ]);
    }

    public function test_approval_inherit_falls_back_to_document_type_level(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Settings::set('attachments.signature_levels', ['Rechnung' => 'ses'], null);

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $wf = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => 'active']);
        $v = WorkflowVersion::create([
            'workflow_id' => $wf->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'a1' => ['id' => 'a1', 'class' => 'approval', 'data' => [
                    'label' => 'Freigabe',
                    'signature_level_override' => 'inherit',
                ]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $wf->update(['current_version_id' => $v->id]);

        $instance = WorkflowInstance::create([
            'workflow_id' => $wf->id, 'workflow_version_id' => $v->id,
            'status' => 'running', 'started_at' => now(),
        ]);
        $path = 'docs/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF');
        Attachment::create([
            'attachable_type' => $instance->getMorphClass(),
            'attachable_id' => $instance->id,
            'original_name' => 'r.pdf', 'disk' => 'local', 'path' => $path,
            'mime_type' => 'application/pdf', 'size' => 4,
            'content_hash' => hash('sha256', '%PDF'),
            'document_type' => 'Rechnung',
            'version_chain_id' => (string) Str::uuid(),
            'version_number' => 1, 'is_current_version' => true,
        ]);

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'a1',
            'step_type' => 'approval', 'assigned_to_user_id' => $admin->id,
            'completed_at' => now(), 'completed_by' => $admin->id, 'decision' => 'approved',
        ]);

        app(SignatureService::class)->maybeSignForStep($step, 'approved');
        $this->assertDatabaseHas('signatures', ['level' => 'ses']);
    }

    public function test_approval_with_none_override_skips_signing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        // Doku-Typ fordert AES, aber Knoten override = none
        Settings::set('attachments.signature_levels', ['Rechnung' => 'aes'], null);

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $wf = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => 'active']);
        $v = WorkflowVersion::create([
            'workflow_id' => $wf->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'a1' => ['id' => 'a1', 'class' => 'approval', 'data' => [
                    'signature_level_override' => 'none',
                ]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $wf->update(['current_version_id' => $v->id]);

        $instance = WorkflowInstance::create([
            'workflow_id' => $wf->id, 'workflow_version_id' => $v->id,
            'status' => 'running', 'started_at' => now(),
        ]);
        $path = 'docs/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF');
        Attachment::create([
            'attachable_type' => $instance->getMorphClass(),
            'attachable_id' => $instance->id,
            'original_name' => 'r.pdf', 'disk' => 'local', 'path' => $path,
            'mime_type' => 'application/pdf', 'size' => 4,
            'content_hash' => hash('sha256', '%PDF'),
            'document_type' => 'Rechnung',
            'version_chain_id' => (string) Str::uuid(),
            'version_number' => 1, 'is_current_version' => true,
        ]);

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'a1',
            'step_type' => 'approval', 'assigned_to_user_id' => $admin->id,
            'completed_at' => now(), 'completed_by' => $admin->id, 'decision' => 'approved',
        ]);

        $count = app(SignatureService::class)->maybeSignForStep($step, 'approved');
        $this->assertSame(0, $count);
        $this->assertDatabaseMissing('signatures', ['workflow_step_execution_id' => $step->id]);
    }
}
