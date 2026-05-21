<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\DocumentCase;
use App\Models\Tag;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15mTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    private function makeAttachment(User $admin, string $name = 'a.pdf', ?string $type = 'Rechnung'): Attachment
    {
        Storage::fake('local');
        // Unique-Inhalt pro Aufruf damit der Duplikat-Hash-Check nicht greift.
        $bytes = $name.'_'.uniqid('', true).str_repeat('x', 100);
        return app(AttachmentStorage::class)->storeBytes(
            $bytes, $name, 'application/pdf', null, null, $admin->id, $type
        );
    }

    public function test_tag_crud(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('tags.store'), [
            'name' => 'Dringend', 'color' => '#ef4444',
        ])->assertRedirect();

        $tag = Tag::firstOrFail();
        $this->assertSame('Dringend', $tag->name);
        $this->assertSame('dringend', $tag->slug);

        $this->actingAs($admin)->delete(route('tags.destroy', $tag))->assertRedirect();
        $this->assertSame(0, Tag::count());
    }

    public function test_case_create_and_show(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('cases.store'), [
            'name' => 'Akte Mueller GmbH', 'reference' => 'KD-007',
        ])->assertRedirect();

        $case = DocumentCase::firstOrFail();
        $this->actingAs($admin)->get(route('cases.show', $case))
            ->assertOk()->assertSee('Akte Mueller GmbH')->assertSee('KD-007');
    }

    public function test_case_close_and_reopen(): void
    {
        $admin = $this->admin();
        $case = DocumentCase::create(['name' => 'X', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('cases.close', $case));
        $this->assertNotNull($case->fresh()->closed_at);

        $this->actingAs($admin)->post(route('cases.close', $case));
        $this->assertNull($case->fresh()->closed_at);
    }

    public function test_bulk_add_tag_to_multiple_documents(): void
    {
        $admin = $this->admin();
        $a = $this->makeAttachment($admin, 'a.pdf');
        $b = $this->makeAttachment($admin, 'b.pdf');
        $tag = Tag::create(['name' => 'Dringend', 'color' => '#ef4444']);

        $this->actingAs($admin)->post(route('documents.bulk_action'), [
            'attachment_ids' => [$a->id, $b->id],
            'action' => 'add_tag',
            'tag_id' => $tag->id,
        ])->assertRedirect();

        $this->assertTrue($a->fresh()->tags->contains($tag->id));
        $this->assertTrue($b->fresh()->tags->contains($tag->id));
    }

    public function test_bulk_set_type_changes_document_type(): void
    {
        $admin = $this->admin();
        Settings::set('attachments.document_types', ['Vertrag', 'Rechnung']);
        $a = $this->makeAttachment($admin, 'a.pdf', 'Rechnung');

        $this->actingAs($admin)->post(route('documents.bulk_action'), [
            'attachment_ids' => [$a->id],
            'action' => 'set_type',
            'document_type' => 'Vertrag',
        ])->assertRedirect();

        $this->assertSame('Vertrag', $a->fresh()->document_type);
    }

    public function test_bulk_add_case_attaches(): void
    {
        $admin = $this->admin();
        $a = $this->makeAttachment($admin, 'a.pdf');
        $b = $this->makeAttachment($admin, 'b.pdf');
        $case = DocumentCase::create(['name' => 'X', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('documents.bulk_action'), [
            'attachment_ids' => [$a->id, $b->id],
            'action' => 'add_case',
            'case_id' => $case->id,
        ])->assertRedirect();

        $this->assertSame(2, $case->fresh()->attachments()->count());
    }

    public function test_bulk_archive_soft_deletes(): void
    {
        $admin = $this->admin();
        $a = $this->makeAttachment($admin, 'a.pdf');

        $this->actingAs($admin)->post(route('documents.bulk_action'), [
            'attachment_ids' => [$a->id],
            'action' => 'archive',
        ])->assertRedirect();

        $this->assertSoftDeleted('attachments', ['id' => $a->id]);
    }

    public function test_document_show_renders_tags_and_cases_block(): void
    {
        $admin = $this->admin();
        $a = $this->makeAttachment($admin, 'foo.pdf');
        $tag = Tag::create(['name' => 'Wichtig', 'color' => '#dc2626']);
        $case = DocumentCase::create(['name' => 'Projekt Alpha', 'created_by' => $admin->id]);
        $a->tags()->attach($tag);
        $a->cases()->attach($case);

        $this->actingAs($admin)->get(route('documents.show', $a))
            ->assertOk()
            ->assertSee('Wichtig')
            ->assertSee('Projekt Alpha');
    }
}
