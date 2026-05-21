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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase10Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    private function configureAI(): void
    {
        Settings::set('ai.provider', 'openai');
        Settings::set('ai.base_url', 'https://api.test/v1');
        Settings::set('ai.model', 'gpt-test');
        Settings::set('ai.api_key', 'sk-test');
    }

    public function test_uploaded_file_gets_sha256_hash_and_verifies(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $holder = User::factory()->create();

        $asset = Asset::create(['name' => 'Führerschein', 'type' => 'fs', 'user_id' => $holder->id, 'status' => 'active', 'lead_time_days' => 30]);

        $content = "PDF-CONTENT-FAKE-".str_repeat('x', 5000);
        $file = UploadedFile::fake()->createWithContent('scan.pdf', $content)->mimeType('application/pdf');

        $att = app(AttachmentStorage::class)->store($file, $asset, 'Vorderseite', $admin->id);

        $this->assertNotEmpty($att->content_hash);
        $this->assertSame(64, strlen($att->content_hash));
        $this->assertSame(hash('sha256', $content), $att->content_hash);
        $this->assertTrue($att->verifyContent());
    }

    public function test_modified_file_fails_integrity_check(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $holder = User::factory()->create();
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $holder->id, 'status' => 'active', 'lead_time_days' => 30]);

        $file = UploadedFile::fake()->createWithContent('s.pdf', 'original')->mimeType('application/pdf');
        $att = app(AttachmentStorage::class)->store($file, $asset, null, $admin->id);

        // Datei auf der Platte manipulieren
        Storage::disk('local')->put($att->path, 'manipulated');

        $this->assertFalse($att->verifyContent());

        $result = app(AttachmentStorage::class)->verifyAll();
        $this->assertSame(1, $result['checked']);
        $this->assertCount(1, $result['broken']);
        $this->assertSame($att->id, $result['broken'][0]['id']);
    }

    public function test_attachment_hash_cannot_be_modified_via_eloquent(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $holder = User::factory()->create();
        $asset = Asset::create(['name' => 'X', 'type' => 'x', 'user_id' => $holder->id, 'status' => 'active', 'lead_time_days' => 30]);
        $file = UploadedFile::fake()->createWithContent('s.pdf', 'data')->mimeType('application/pdf');
        $att = app(AttachmentStorage::class)->store($file, $asset, null, $admin->id);

        $this->expectException(\RuntimeException::class);
        $att->update(['content_hash' => 'tampered']);
    }

    public function test_ai_suggest_workflow_returns_draft_json(): void
    {
        $admin = $this->admin();
        $this->configureAI();

        Http::fake([
            'api.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'form_schema' => [['key' => 'betrag', 'label' => 'Betrag', 'type' => 'number', 'required' => true]],
                    'nodes' => [
                        ['id' => 'n1', 'type' => 'start', 'label' => 'Start', 'data' => []],
                        ['id' => 'n2', 'type' => 'approval', 'label' => 'Pruefen', 'data' => ['recipient_type' => 'supervisor_of_initiator']],
                        ['id' => 'n3', 'type' => 'end', 'label' => 'Ende', 'data' => ['result' => 'completed']],
                    ],
                    'edges' => [
                        ['from' => 'n1', 'from_output' => 1, 'to' => 'n2', 'to_input' => 1],
                        ['from' => 'n2', 'from_output' => 1, 'to' => 'n3', 'to_input' => 1],
                    ],
                ])]]],
            ]),
        ]);

        $r = $this->actingAs($admin)->postJson(route('admin.ai.suggest_workflow'), [
            'description' => 'Mitarbeiter beantragt Bestellung, Vorgesetzter genehmigt.',
            'trigger_type' => 'form',
        ]);
        $r->assertOk();
        $draft = $r->json('draft');
        $this->assertCount(3, $draft['nodes']);
        $this->assertCount(2, $draft['edges']);
        $this->assertCount(1, $draft['form_schema']);
    }

    public function test_ai_suggest_workflow_handles_bad_response(): void
    {
        $admin = $this->admin();
        $this->configureAI();
        Http::fake([
            'api.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'Sorry I cannot help.']]],
            ]),
        ]);

        $r = $this->actingAs($admin)->postJson(route('admin.ai.suggest_workflow'), [
            'description' => 'x', 'trigger_type' => 'form',
        ]);
        $r->assertStatus(422);
    }

    public function test_employee_cannot_call_ai_endpoints(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $emp = User::factory()->create();
        $emp->assignRole('employee');
        $this->actingAs($emp)
            ->postJson(route('admin.ai.suggest_workflow'), ['description' => 'x'])
            ->assertForbidden();
    }
}
