<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcalFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rotate_ical_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.ical.rotate'))->assertRedirect();
        $this->assertNotEmpty($user->fresh()->ical_token);
        $this->assertSame(48, strlen($user->fresh()->ical_token));
    }

    public function test_user_can_revoke_ical_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['ical_token' => 'foo']);

        $this->actingAs($user)->post(route('profile.ical.revoke'))->assertRedirect();
        $this->assertNull($user->fresh()->ical_token);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get('/ical/no-such-token.ics')->assertNotFound();
    }

    public function test_inactive_user_token_returns_404(): void
    {
        $user = User::factory()->create(['ical_token' => 'abcdef1234', 'is_active' => false]);
        $this->get('/ical/abcdef1234.ics')->assertNotFound();
    }

    public function test_feed_returns_calendar_with_tasks_and_contracts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['ical_token' => 'tok123456']);
        $user->assignRole('employee');

        // 1 Aufgabe mit due_at
        $w = Workflow::create(['name' => 'Rechnungseingang', 'slug' => 're', 'status' => 'active', 'created_by' => $user->id]);
        $v = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1, 'definition' => ['drawflow' => ['Home' => ['data' => []]]]]);
        $i = WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id, 'data' => [], 'status' => 'running', 'started_at' => now()]);
        WorkflowStepExecution::create([
            'workflow_instance_id' => $i->id, 'step_key' => 'n1', 'step_type' => 'approval',
            'assigned_to_user_id' => $user->id, 'due_at' => now()->addDays(3),
        ]);

        // 1 Vertrag mit Ende
        Contract::create([
            'name' => 'Wartung Heizung', 'end_date' => now()->addMonths(6),
            'notice_period_days' => 30, 'status' => 'active', 'owner_user_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $resp = $this->get('/ical/tok123456.ics');
        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $body = $resp->getContent();
        $this->assertStringStartsWith('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:[OWE] Aufgabe: Rechnungseingang', $body);
        $this->assertStringContainsString('Wartung Heizung', $body);
        // Token darf nicht im Body sein (Sicherheits-Check)
        $this->assertStringNotContainsString('tok123456', $body);
    }
}
