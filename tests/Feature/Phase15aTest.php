<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15aTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_retention_settings_can_be_saved(): void
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->post(route('admin.settings.retention.update'), [
            'rules' => [
                ['document_type' => 'Rechnung', 'min_years' => 10, 'max_years' => 11, 'on_expiry' => 'archive'],
                ['document_type' => 'Bewerbung', 'min_years' => 0, 'max_years' => 1, 'on_expiry' => 'delete'],
            ],
        ]);
        $resp->assertRedirect();
        $rules = (array) Settings::get('attachments.retention', []);
        $this->assertArrayHasKey('Rechnung', $rules);
        $this->assertSame('archive', $rules['Rechnung']['on_expiry']);
        $this->assertSame(11, $rules['Rechnung']['max_years']);
    }

    public function test_retention_command_archives_expired_documents(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $a = app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('alt.pdf', 'OLD')->mimeType('application/pdf'),
            null, null, $admin->id, 'Bewerbung',
        );
        Attachment::whereKey($a->id)->update(['created_at' => now()->subYears(5)]);

        Settings::set('attachments.retention', [
            'Bewerbung' => ['min_years' => 0, 'max_years' => 1, 'on_expiry' => 'archive'],
        ]);

        $this->artisan('documents:retention-check')->assertSuccessful();

        $this->assertSoftDeleted('attachments', ['id' => $a->id]);
    }

    public function test_attachment_storage_can_store_bytes(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $att = app(AttachmentStorage::class)->storeBytes(
            'hello world', 'test.pdf', 'application/pdf', null, 'Label', $admin->id, 'Workflow-Beweis'
        );
        $this->assertSame('Workflow-Beweis', $att->document_type);
        $this->assertSame(hash('sha256', 'hello world'), $att->content_hash);
        $this->assertTrue(Storage::disk('local')->exists($att->path));
        $this->assertSame('hello world', Storage::disk('local')->get($att->path));
    }
}
