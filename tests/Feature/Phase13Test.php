<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase13Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_first_upload_initializes_version_chain(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $asset = Asset::create(['name'=>'X','type'=>'x','user_id'=>$admin->id,'status'=>'active','lead_time_days'=>30]);
        $a = app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('v1.pdf', 'AAA')->mimeType('application/pdf'),
            $asset, null, $admin->id, 'Vertrag',
        );
        $this->assertNotNull($a->version_chain_id);
        $this->assertSame(1, $a->version_number);
        $this->assertTrue($a->is_current_version);
    }

    public function test_new_version_increments_and_marks_previous_as_old(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $asset = Asset::create(['name'=>'X','type'=>'x','user_id'=>$admin->id,'status'=>'active','lead_time_days'=>30]);
        $storage = app(AttachmentStorage::class);

        $v1 = $storage->store(UploadedFile::fake()->createWithContent('v1.pdf','A')->mimeType('application/pdf'), $asset, null, $admin->id, 'Vertrag');
        $v2 = $storage->store(UploadedFile::fake()->createWithContent('v2.pdf','B')->mimeType('application/pdf'), $asset, null, $admin->id, null, $v1);

        $this->assertSame($v1->version_chain_id, $v2->version_chain_id);
        $this->assertSame(2, $v2->version_number);
        $this->assertTrue($v2->is_current_version);
        $this->assertFalse($v1->fresh()->is_current_version);
        // document_type übernommen, da nicht explizit gesetzt
        $this->assertSame('Vertrag', $v2->document_type);
    }

    public function test_documents_index_only_shows_current_version(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Vertrag']);
        Settings::set('attachments.role_document_types', ['admin' => ['Vertrag']]);
        $asset = Asset::create(['name'=>'X','type'=>'x','user_id'=>$admin->id,'status'=>'active','lead_time_days'=>30]);
        $storage = app(AttachmentStorage::class);

        $v1 = $storage->store(UploadedFile::fake()->createWithContent('v1.pdf','A')->mimeType('application/pdf'), $asset, null, $admin->id, 'Vertrag');
        $v2 = $storage->store(UploadedFile::fake()->createWithContent('v2.pdf','B')->mimeType('application/pdf'), $asset, null, $admin->id, null, $v1);

        $r = $this->actingAs($admin)->get(route('documents.index'));
        $r->assertOk()
          ->assertSee('v2.pdf')
          ->assertDontSee('v1.pdf');

        // Detailseite zeigt alle Versionen
        $detail = $this->actingAs($admin)->get(route('documents.show', $v2));
        $detail->assertOk()->assertSee('v1.pdf')->assertSee('v2.pdf');
    }

    public function test_new_version_upload_via_http(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Vertrag']);
        Settings::set('attachments.role_document_types', ['admin' => ['Vertrag']]);
        $asset = Asset::create(['name'=>'X','type'=>'x','user_id'=>$admin->id,'status'=>'active','lead_time_days'=>30]);
        $v1 = app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('v1.pdf','A')->mimeType('application/pdf'),
            $asset, null, $admin->id, 'Vertrag',
        );

        $r = $this->actingAs($admin)->post(route('documents.new_version', $v1), [
            'file' => UploadedFile::fake()->createWithContent('v2.pdf','BBBB')->mimeType('application/pdf'),
        ]);
        $r->assertRedirect();

        $this->assertSame(2, Attachment::where('version_chain_id', $v1->version_chain_id)->count());
        $current = Attachment::where('version_chain_id', $v1->version_chain_id)
            ->where('is_current_version', true)->first();
        $this->assertSame(2, $current->version_number);
    }

    public function test_inline_preview_sends_content_disposition_inline(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Vertrag']);
        Settings::set('attachments.role_document_types', ['admin' => ['Vertrag']]);
        $asset = Asset::create(['name'=>'X','type'=>'x','user_id'=>$admin->id,'status'=>'active','lead_time_days'=>30]);
        $att = app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('s.pdf','data')->mimeType('application/pdf'),
            $asset, null, $admin->id, 'Vertrag',
        );

        $r = $this->actingAs($admin)->get(route('documents.preview', $att));
        $r->assertOk();
        $disp = $r->headers->get('content-disposition');
        $this->assertStringStartsWith('inline', strtolower($disp));
    }

    public function test_bulk_upload_creates_standalone_documents(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Rechnung']);

        $r = $this->actingAs($admin)->post(route('documents.bulk.store'), [
            'files' => [
                UploadedFile::fake()->createWithContent('r1.pdf','A')->mimeType('application/pdf'),
                UploadedFile::fake()->createWithContent('r2.pdf','B')->mimeType('application/pdf'),
            ],
            'document_type' => 'Rechnung',
        ]);
        $r->assertRedirect();

        $atts = Attachment::all();
        $this->assertSame(2, $atts->count());
        foreach ($atts as $a) {
            $this->assertNull($a->attachable_type);
            $this->assertNull($a->attachable_id);
            $this->assertSame('Rechnung', $a->document_type);
            $this->assertSame(1, $a->version_number);
        }
    }
}
