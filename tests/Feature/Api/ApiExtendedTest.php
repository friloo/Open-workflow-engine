<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\AppNotification;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\DocumentCase;
use App\Models\LookupList;
use App\Models\LookupListEntry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiExtendedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->token = ApiToken::generate($this->admin, 'test', ['*'])['plain'];
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json'];
    }

    public function test_contracts_index_and_create(): void
    {
        Contract::create(['name' => 'V1', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $this->admin->id]);

        $this->get('/api/v1/contracts', $this->headers())
            ->assertOk()->assertJsonPath('data.0.name', 'V1');

        $this->post('/api/v1/contracts', [
            'name' => 'Neuer Vertrag', 'notice_period_days' => 60,
        ], $this->headers())->assertCreated()->assertJsonPath('name', 'Neuer Vertrag');
    }

    public function test_contracts_show_returns_403_without_visibility(): void
    {
        $type = ContractType::create(['name' => 'X', 'slug' => 'x', 'default_notice_period_days' => 90]);
        $c = Contract::create(['name' => 'V', 'contract_type_id' => $type->id, 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $this->admin->id]);

        $outsider = User::factory()->create();
        $outsider->assignRole('employee');
        $t2 = ApiToken::generate($outsider, 't2', ['*'])['plain'];
        $this->get("/api/v1/contracts/{$c->id}", ['Authorization' => 'Bearer '.$t2, 'Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_contracts_upload_attachment(): void
    {
        Storage::fake('local');
        $c = Contract::create(['name' => 'V', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $this->admin->id]);
        $pdf = UploadedFile::fake()->createWithContent('vertrag.pdf', "%PDF-1.4\nx-unique\n%%EOF");

        $this->post("/api/v1/contracts/{$c->id}/attachments", ['file' => $pdf, 'label' => 'Hauptvertrag'], $this->headers())
            ->assertCreated()->assertJsonPath('name', 'vertrag.pdf');
        $this->assertSame(1, $c->fresh()->attachments()->count());
    }

    public function test_cases_index_show_and_attach(): void
    {
        $case = DocumentCase::create(['name' => 'Kunden-Akte X', 'created_by' => $this->admin->id]);
        $c = Contract::create(['name' => 'V', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $this->admin->id]);

        $this->get('/api/v1/cases', $this->headers())->assertOk()
            ->assertJsonPath('data.0.name', 'Kunden-Akte X');

        $this->post("/api/v1/cases/{$case->id}/contracts", ['contract_id' => $c->id], $this->headers())
            ->assertCreated();
        $this->assertTrue($case->fresh()->contracts->contains($c->id));

        $this->post("/api/v1/cases/{$case->id}/notes", ['body' => 'API-Notiz'], $this->headers())
            ->assertCreated()->assertJsonPath('body', 'API-Notiz');
    }

    public function test_reports_kpis_endpoint(): void
    {
        $this->get('/api/v1/reports/kpis?days=14', $this->headers())
            ->assertOk()
            ->assertJsonStructure([
                'range_days', 'since',
                'volume' => ['total', 'completed', 'running', 'cancelled', 'failed', 'completionRate'],
                'lead_times', 'sla_violations', 'slowest_steps', 'top_assignees', 'daily_volume',
            ]);
    }

    public function test_audit_logs_endpoint(): void
    {
        // Erzeuge mind. 1 Audit-Eintrag durch Vertragsanlage
        $this->post('/api/v1/contracts', ['name' => 'AuditCheck', 'notice_period_days' => 90], $this->headers());

        $this->get('/api/v1/audit-logs?event=contract.&per_page=10', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.event', 'contract.created');
    }

    public function test_users_endpoint(): void
    {
        $this->get('/api/v1/users?per_page=5', $this->headers())
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total']]);
    }

    public function test_lists_endpoints(): void
    {
        $list = LookupList::create(['name' => 'Kostenstellen', 'slug' => 'kostenstellen', 'columns' => [
            ['name' => 'name', 'label' => 'Name', 'role' => 'key'],
        ], 'created_by' => $this->admin->id]);

        $this->get('/api/v1/lists', $this->headers())->assertOk()
            ->assertJsonPath('data.0.slug', 'kostenstellen');

        $this->post('/api/v1/lists/kostenstellen/entries', [
            'key' => 'K001', 'data' => ['name' => 'Vertrieb'],
        ], $this->headers())->assertCreated()->assertJsonPath('key', 'K001');

        $this->get('/api/v1/lists/kostenstellen/entries', $this->headers())->assertOk()
            ->assertJsonPath('data.0.key', 'K001');
    }

    public function test_notifications_endpoint(): void
    {
        AppNotification::create([
            'user_id' => $this->admin->id, 'type' => 'task.assigned',
            'title' => 'Test', 'body' => 'Body',
        ]);

        $this->get('/api/v1/notifications?unread_only=1', $this->headers())->assertOk()
            ->assertJsonPath('data.0.title', 'Test');

        $this->post('/api/v1/notifications/read-all', [], $this->headers())->assertOk();
        $this->assertNotNull(AppNotification::first()->read_at);
    }

    public function test_notifications_mark_read_blocks_foreign_notification(): void
    {
        $other = User::factory()->create();
        $n = AppNotification::create([
            'user_id' => $other->id, 'type' => 't', 'title' => 'X',
        ]);
        $this->post("/api/v1/notifications/{$n->id}/read", [], $this->headers())
            ->assertForbidden();
    }

    public function test_search_endpoint(): void
    {
        Contract::create(['name' => 'Mietvertrag Schillerstraße', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $this->admin->id]);

        $resp = $this->get('/api/v1/search?q=Schiller', $this->headers())->assertOk();
        $this->assertNotEmpty($resp->json('contracts'));
    }

    public function test_search_validates_min_length(): void
    {
        $this->get('/api/v1/search?q=a', $this->headers())->assertStatus(422);
    }
}
