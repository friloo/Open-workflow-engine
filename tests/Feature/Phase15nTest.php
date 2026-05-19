<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\FolderInbox;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use App\Services\FolderInboxScanner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15nTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    private function inboxDir(): string
    {
        $d = sys_get_temp_dir().'/owe-folder-test-'.bin2hex(random_bytes(4));
        @mkdir($d, 0775, true);
        return $d;
    }

    public function test_scanner_imports_pdf_and_deletes_source(): void
    {
        $admin = $this->admin();
        Storage::fake('local');
        $dir = $this->inboxDir();
        file_put_contents($dir.'/rechnung-001.pdf', '%PDF-1.7 fake content');

        $inbox = FolderInbox::create([
            'name' => 'Scanner', 'path' => $dir, 'use_storage_disk' => false,
            'document_type' => 'Rechnung', 'is_active' => true,
            'after_import' => 'delete', 'created_by' => $admin->id,
        ]);

        $stats = app(FolderInboxScanner::class)->scan($inbox);

        $this->assertSame(1, $stats['found']);
        $this->assertSame(1, $stats['imported']);
        $this->assertSame(0, $stats['failed']);
        $this->assertFileDoesNotExist($dir.'/rechnung-001.pdf');
        $this->assertSame(1, Attachment::where('document_type', 'Rechnung')->count());

        @rmdir($dir);
    }

    public function test_scanner_moves_to_subfolder_when_configured(): void
    {
        $admin = $this->admin();
        Storage::fake('local');
        $dir = $this->inboxDir();
        file_put_contents($dir.'/x.pdf', '%PDF-1.7 test');

        $inbox = FolderInbox::create([
            'name' => 'Scanner', 'path' => $dir, 'use_storage_disk' => false,
            'is_active' => true, 'after_import' => 'move', 'processed_subfolder' => 'done',
            'created_by' => $admin->id,
        ]);

        app(FolderInboxScanner::class)->scan($inbox);

        $this->assertFileDoesNotExist($dir.'/x.pdf');
        $moved = glob($dir.'/done/*.pdf');
        $this->assertNotEmpty($moved);
        $this->assertStringContainsString('x.pdf', basename($moved[0]));

        // Cleanup
        foreach ($moved as $f) @unlink($f);
        @rmdir($dir.'/done');
        @rmdir($dir);
    }

    public function test_scanner_starts_workflow_with_doc_context(): void
    {
        $admin = $this->admin();
        Storage::fake('local');
        $dir = $this->inboxDir();
        file_put_contents($dir.'/r.pdf', '%PDF-1.7 dummy');

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

        $inbox = FolderInbox::create([
            'name' => 'Scan', 'path' => $dir, 'use_storage_disk' => false,
            'workflow_id' => $workflow->id, 'document_type' => 'Rechnung',
            'is_active' => true, 'after_import' => 'delete', 'created_by' => $admin->id,
        ]);

        app(FolderInboxScanner::class)->scan($inbox);

        $instance = WorkflowInstance::firstOrFail();
        $this->assertSame('folder_inbox', $instance->data['source']);
        $this->assertNotNull($instance->data['doc_attachment_id']);

        @rmdir($dir);
    }

    public function test_admin_ui_lists_and_creates_inbox(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.folder-inboxes.index'))->assertOk()
            ->assertSee('Folder-Inboxen');

        $this->actingAs($admin)->post(route('admin.folder-inboxes.store'), [
            'name' => 'Scanner',
            'path' => '/tmp/owe-test',
            'after_import' => 'delete',
        ])->assertRedirect();

        $this->assertSame(1, FolderInbox::count());
    }
}
