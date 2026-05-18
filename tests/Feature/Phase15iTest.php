<?php

namespace Tests\Feature;

use App\Mail\TaskReminderMail;
use App\Models\Attachment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\AttachmentStorage;
use App\Services\BackupService;
use App\Services\WorkflowEngine;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15iTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    private function workflowWithStep(User $admin, User $assignee, array $nodeData = []): WorkflowStepExecution
    {
        $workflow = Workflow::create(['name' => 'W', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'a' => ['id' => 'a', 'class' => 'approval',
                    'data' => array_merge(['label' => 'Pruefen'], $nodeData),
                    'outputs' => ['output_1' => ['connections' => []], 'output_2' => ['connections' => []]]],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);
        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'started_by' => $admin->id, 'status' => WorkflowInstance::STATUS_RUNNING, 'started_at' => now(),
        ]);
        return WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id, 'step_key' => 'a', 'step_type' => 'approval',
            'assigned_to_user_id' => $assignee->id, 'assigned_at' => now()->subDays(5),
        ]);
    }

    // 1. Reminder
    public function test_reminder_command_sends_mail_for_stale_tasks(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $assignee = User::factory()->create(['email_notifications_enabled' => true]);
        $step = $this->workflowWithStep($admin, $assignee);

        $this->artisan('tasks:remind')->assertSuccessful();

        Mail::assertSent(TaskReminderMail::class, fn ($m) => $m->hasTo($assignee->email));
        $this->assertNotNull($step->fresh()->last_reminded_at);
    }

    public function test_reminder_respects_interval(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $assignee = User::factory()->create(['email_notifications_enabled' => true]);
        $step = $this->workflowWithStep($admin, $assignee);
        $step->forceFill(['last_reminded_at' => now()->subDay()])->save();

        Settings::set('tasks.reminder_interval_days', 3);
        $this->artisan('tasks:remind')->assertSuccessful();
        Mail::assertNothingSent();
    }

    // 2. Bulk-Postkorb
    public function test_bulk_inbox_starts_workflow_per_attachment(): void
    {
        $admin = $this->admin();
        Storage::fake('local');
        $a = app(AttachmentStorage::class)->storeBytes('1', '1.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $b = app(AttachmentStorage::class)->storeBytes('2', '2.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');

        $workflow = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => Workflow::STATUS_ACTIVE]);
        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                's' => ['id' => 's', 'class' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'e']]]]],
                'e' => ['id' => 'e', 'class' => 'end', 'data' => ['result' => 'completed'], 'outputs' => []],
            ]]]],
            'created_by' => $admin->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $this->actingAs($admin)->post(route('documents.inbox.bulk_start'), [
            'workflow_id' => $workflow->id,
            'attachment_ids' => [$a->id, $b->id],
        ])->assertRedirect();

        $this->assertSame(2, WorkflowInstance::count());
        $this->assertNotNull($a->fresh()->attachable_id);
        $this->assertNotNull($b->fresh()->attachable_id);
    }

    // 3. CSV-Export
    public function test_csv_export_streams_with_indexed_fields(): void
    {
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Rechnung']);
        Settings::set('attachments.field_schemas', [
            'Rechnung' => [
                ['key' => 'nummer', 'label' => 'Nr', 'type' => 'string',
                 'extractor' => 'manual', 'pattern' => null, 'ki_fallback' => false],
            ],
        ]);
        Storage::fake('local');
        $att = app(AttachmentStorage::class)->storeBytes('a', 'a.pdf', 'application/pdf', null, null, $admin->id, 'Rechnung');
        $att->forceFill(['indexed_fields' => ['nummer' => 'R-77']])->save();

        $resp = $this->actingAs($admin)->get(route('documents.export_csv', ['type' => 'Rechnung']));
        $resp->assertOk();
        $body = $resp->streamedContent();
        $this->assertStringContainsString('a.pdf', $body);
        $this->assertStringContainsString('feld_nummer', $body);
        $this->assertStringContainsString('R-77', $body);
    }

    // 4. Bedingte Pflichtfelder
    public function test_decide_requires_comment_on_rejection_when_configured(): void
    {
        $admin = $this->admin();
        $step = $this->workflowWithStep($admin, $admin, ['require_comment_on_rejection' => true]);

        $this->actingAs($admin)
            ->post(route('tasks.decide', $step), ['decision' => 'rejected'])
            ->assertSessionHasErrors('comment');

        $this->actingAs($admin)
            ->post(route('tasks.decide', $step), ['decision' => 'rejected', 'comment' => 'Doppelte Lieferung.'])
            ->assertRedirect();
        $this->assertSame('rejected', $step->fresh()->decision);
    }

    public function test_decide_without_required_flag_accepts_empty_comment(): void
    {
        $admin = $this->admin();
        $step = $this->workflowWithStep($admin, $admin);
        $this->actingAs($admin)
            ->post(route('tasks.decide', $step), ['decision' => 'rejected'])
            ->assertRedirect();
        $this->assertSame('rejected', $step->fresh()->decision);
    }

    // 5. Backup
    public function test_backup_service_creates_and_lists_zip(): void
    {
        $admin = $this->admin();
        $service = app(BackupService::class);

        // Storage muss attachements-Pfad haben
        @mkdir(storage_path('app/attachments'), 0775, true);

        $path = $service->create($admin->id);
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        $list = $service->list();
        $this->assertNotEmpty($list);
        $this->assertSame(basename($path), $list[0]['file']);

        // Inhalt: manifest.json muss da sein
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->getFromName('manifest.json'));
        $zip->close();

        // Aufraeumen
        @unlink($path);
    }

    public function test_backup_admin_ui_lists_and_creates(): void
    {
        $admin = $this->admin();
        @mkdir(storage_path('app/attachments'), 0775, true);

        $this->actingAs($admin)->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Backups');

        $resp = $this->actingAs($admin)->post(route('admin.backups.store'))->assertRedirect();
        $list = app(BackupService::class)->list();
        $this->assertNotEmpty($list);

        // Aufraeumen
        foreach ($list as $b) {
            @unlink(app(BackupService::class)->path($b['file']));
        }
    }
}
