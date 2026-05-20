<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_support_renders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.settings.support'))->assertOk();
    }

    public function test_support_show_404_when_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('support.show'))->assertNotFound();
    }

    public function test_support_show_200_when_enabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        \App\Support\Settings::set('support.enabled', true);
        \App\Support\Settings::set('support.mode', 'mail');
        \App\Support\Settings::set('support.email', 'help@example.com');

        $emp = User::factory()->create();
        $emp->assignRole('employee');

        $this->actingAs($emp)->get(route('support.show'))->assertOk();
    }

    public function test_documents_index_renders_with_split_layout(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Mindestens 1 Dokument anlegen — sonst greift der Empty-State,
        // und der Split-Layout-Block wird gar nicht gerendert.
        \Illuminate\Support\Facades\Storage::fake('local');
        $att = \App\Models\Attachment::create([
            'disk' => 'local',
            'path' => 'docs/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'content_hash' => str_repeat('a', 64),
            'uploaded_by' => $admin->id,
            'is_current_version' => true,
            'version_chain_id' => \Illuminate\Support\Str::uuid(),
            'version_number' => 1,
        ]);

        $resp = $this->actingAs($admin)->get(route('documents.index'));
        $resp->assertOk();

        $body = $resp->getContent();
        $this->assertStringContainsString('documentsSplit(', $body, 'Alpine-Component-Aufruf fehlt');
        $this->assertStringContainsString('Klick ein Dokument links an', $body, 'Preview-Placeholder fehlt');
    }

    public function test_task_show_zeigt_pdf_preview_iframe(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        \Illuminate\Support\Facades\Storage::fake('local');

        $workflow = \App\Models\Workflow::create(['name' => 'T', 'slug' => 't', 'status' => 'active', 'created_by' => $user->id]);
        $version = \App\Models\WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => []]]],
        ]);
        $instance = \App\Models\WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'data' => [], 'status' => 'running',
            'started_at' => now(), 'started_by' => $user->id,
        ]);

        $att = \App\Models\Attachment::create([
            'disk' => 'local', 'path' => 'docs/r.pdf',
            'original_name' => 'rechnung.pdf', 'mime_type' => 'application/pdf',
            'size' => 1024, 'content_hash' => str_repeat('b', 64),
            'uploaded_by' => $user->id, 'is_current_version' => true,
            'version_chain_id' => \Illuminate\Support\Str::uuid(), 'version_number' => 1,
            'attachable_type' => \App\Models\WorkflowInstance::class,
            'attachable_id' => $instance->id, 'document_type' => null,
        ]);

        $step = \App\Models\WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => 'node-1', 'step_type' => 'approval',
            'assigned_to_user_id' => $user->id,
        ]);

        $resp = $this->actingAs($user)->get(route('tasks.show', $step));
        $resp->assertOk();
        $body = $resp->getContent();
        $this->assertStringContainsString('<iframe', $body, 'PDF-Iframe fehlt im Task-Detail');
        $this->assertStringContainsString('Beleg zur Aufgabe', $body, 'Preview-Card-Header fehlt');
    }
}
