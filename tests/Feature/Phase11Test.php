<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Role;
use App\Models\User;
use App\Services\AttachmentStorage;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase11Test extends TestCase
{
    use RefreshDatabase;

    public function test_role_based_document_type_filtering(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Buchhaltungs-Rolle anlegen und Typ "Rechnung" zuordnen
        $accounting = Role::create(['name' => 'Buchhaltung', 'slug' => 'buchhaltung', 'is_system' => false]);
        $accounting->permissions()->attach(\App\Models\Permission::whereIn('slug', ['documents.search'])->pluck('id'));

        Settings::set('attachments.document_types', ['Rechnung', 'Vertrag', 'Fuehrerschein']);
        Settings::set('attachments.role_document_types', [
            'buchhaltung' => ['Rechnung'],
            'hr' => ['Fuehrerschein'],
        ]);

        $bUser = User::factory()->create();
        $bUser->assignRole('buchhaltung');

        // Asset gehoert einem ANDEREN User, damit der Owner-Bypass in
        // Attachment::visibleTo() das Test-Setup nicht umgeht.
        $otherUser = User::factory()->create();

        Storage::fake('local');
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $otherUser->id, 'status' => 'active', 'lead_time_days' => 30]);
        $storage = app(AttachmentStorage::class);
        $a1 = $storage->store(UploadedFile::fake()->createWithContent('r.pdf', 'A')->mimeType('application/pdf'), $asset, null, $otherUser->id, 'Rechnung');
        $a2 = $storage->store(UploadedFile::fake()->createWithContent('v.pdf', 'B')->mimeType('application/pdf'), $asset, null, $otherUser->id, 'Vertrag');
        $a3 = $storage->store(UploadedFile::fake()->createWithContent('f.pdf', 'C')->mimeType('application/pdf'), $asset, null, $otherUser->id, 'Fuehrerschein');

        $r = $this->actingAs($bUser)->get(route('documents.index'));
        $r->assertOk()
          ->assertSee('r.pdf')
          ->assertDontSee('v.pdf')
          ->assertDontSee('f.pdf');

        $this->actingAs($bUser)->get(route('documents.show', $a1))->assertOk();
        $this->actingAs($bUser)->get(route('documents.show', $a2))->assertForbidden();
    }

    public function test_workflow_assignee_can_open_attached_document_without_type_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Settings::set('attachments.document_types', ['Rechnung']);
        // Bewusst LEER — die Buchhaltung darf den Typ 'Rechnung' NICHT generell sehen.
        Settings::set('attachments.role_document_types', []);

        $accounting = Role::create(['name' => 'Buchhaltung', 'slug' => 'buchhaltung', 'is_system' => false]);
        $accounting->permissions()->attach(\App\Models\Permission::whereIn('slug', ['documents.search'])->pluck('id'));

        $approver = User::factory()->create();
        $approver->assignRole('buchhaltung');

        // Workflow-Instanz mit angehaengter Rechnung
        $workflow = \App\Models\Workflow::create(['name' => 'Test', 'slug' => 'test', 'status' => 'active', 'created_by' => $approver->id]);
        $version = \App\Models\WorkflowVersion::create(['workflow_id' => $workflow->id, 'version_number' => 1, 'definition' => ['drawflow' => ['Home' => ['data' => []]]]]);
        $instance = \App\Models\WorkflowInstance::create([
            'workflow_id' => $workflow->id, 'workflow_version_id' => $version->id,
            'data' => [], 'status' => 'running',
            'started_at' => now(), 'started_by' => $approver->id,
        ]);

        Storage::fake('local');
        $invoice = app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('rechnung.pdf', 'X')->mimeType('application/pdf'),
            $instance, null, $approver->id, 'Rechnung',
        );

        // Schritt direkt dem Approver zuweisen
        \App\Models\WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => 'node-1',
            'step_type' => 'approval',
            'assigned_to_user_id' => $approver->id,
        ]);

        // Ohne Type-Rechte allein per Rolle: 403 auf documents.show
        $other = User::factory()->create();
        $other->assignRole('buchhaltung');
        $this->actingAs($other)->get(route('documents.show', $invoice))->assertForbidden();

        // Aber als Assignee: 200 — kein Type-Recht noetig
        $this->actingAs($approver)->get(route('documents.show', $invoice))->assertOk();
    }

    public function test_admin_sees_all_document_types(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Settings::set('attachments.document_types', ['Rechnung', 'Vertrag']);
        Settings::set('attachments.role_document_types', ['admin' => []]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Storage::fake('local');
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $admin->id, 'status' => 'active', 'lead_time_days' => 30]);
        $storage = app(AttachmentStorage::class);
        $storage->store(UploadedFile::fake()->createWithContent('r.pdf', 'A')->mimeType('application/pdf'), $asset, null, $admin->id, 'Rechnung');
        $storage->store(UploadedFile::fake()->createWithContent('v.pdf', 'B')->mimeType('application/pdf'), $asset, null, $admin->id, 'Vertrag');

        $this->actingAs($admin)->get(route('documents.index'))
            ->assertOk()->assertSee('r.pdf')->assertSee('v.pdf');
    }

    public function test_full_text_search_finds_ocr_match(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Settings::set('attachments.document_types', ['Vertrag']);
        Settings::set('attachments.role_document_types', ['admin' => ['Vertrag']]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Storage::fake('local');
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $admin->id, 'status' => 'active', 'lead_time_days' => 30]);
        $storage = app(AttachmentStorage::class);
        $att = $storage->store(UploadedFile::fake()->createWithContent('test.pdf', 'data')->mimeType('application/pdf'), $asset, null, $admin->id, 'Vertrag');

        // OCR-Text manuell setzen (sonst koennten Test-Server keine OCR-Tools haben)
        $att->forceFill([
            'ocr_text' => 'Hier steht ein wichtiger Vertrag ueber Druckerwartung mit Kundennummer 4711.',
            'ocr_status' => 'done',
        ])->save();

        $r = $this->actingAs($admin)->get(route('documents.index', ['q' => 'Druckerwartung']));
        $r->assertOk()->assertSee('test.pdf');
    }

    public function test_document_types_without_role_assignment_are_invisible_to_non_admins(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Settings::set('attachments.document_types', ['Geheim']);
        // Keine Rolle hat Zugriff
        Settings::set('attachments.role_document_types', []);

        $accounting = Role::create(['name' => 'Buchhaltung', 'slug' => 'buchhaltung', 'is_system' => false]);
        $accounting->permissions()->attach(\App\Models\Permission::where('slug', 'documents.search')->pluck('id'));

        $user = User::factory()->create();
        $user->assignRole('buchhaltung');

        Storage::fake('local');
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $user->id, 'status' => 'active', 'lead_time_days' => 30]);
        app(AttachmentStorage::class)->store(
            UploadedFile::fake()->createWithContent('s.pdf', 'A')->mimeType('application/pdf'),
            $asset, null, $user->id, 'Geheim',
        );

        $this->actingAs($user)->get(route('documents.index'))
            ->assertOk()->assertDontSee('s.pdf');
    }
}
