<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\AppNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_open_contracts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->actingAs($user)->get(route('contracts.index'))->assertForbidden();
    }

    public function test_admin_can_create_a_contract(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->post(route('contracts.store'), [
            'name' => 'Wartung Heizung',
            'party' => 'Brunner GmbH',
            'category' => 'Wartung',
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'notice_period_days' => 90,
            'owner_user_id' => $admin->id,
        ]);

        $contract = Contract::first();
        $this->assertNotNull($contract);
        $resp->assertRedirect(route('contracts.show', $contract));
        $this->assertSame('Wartung Heizung', $contract->name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contract.created']);
    }

    public function test_status_is_computed_from_end_date(): void
    {
        $c1 = new Contract(['end_date' => now()->addYear(), 'notice_period_days' => 30]);
        $this->assertSame('active', $c1->computedStatus());

        $c2 = new Contract(['end_date' => now()->addDays(20), 'notice_period_days' => 30]);
        $this->assertSame('notice_due', $c2->computedStatus());

        $c3 = new Contract(['end_date' => now()->subDay(), 'notice_period_days' => 30]);
        $this->assertSame('expired', $c3->computedStatus());

        $c4 = new Contract(['end_date' => null]);
        $this->assertSame('active', $c4->computedStatus());
    }

    public function test_cron_creates_notification_when_notice_due(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('admin');

        Contract::create([
            'name' => 'Software-Lizenz Buchhaltung',
            'end_date' => now()->addDays(20),
            'notice_period_days' => 60, // -> heute schon überschritten -> notice_due
            'owner_user_id' => $owner->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        Artisan::call('contracts:check-deadlines');

        // Notification für den Owner
        $notif = AppNotification::where('user_id', $owner->id)
            ->where('type', 'contract.notice_due')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Software-Lizenz', $notif->title);
    }

    public function test_cron_doesnt_remind_twice_within_90_days(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('admin');

        $c = Contract::create([
            'name' => 'Test',
            'end_date' => now()->addDays(20),
            'notice_period_days' => 60,
            'owner_user_id' => $owner->id,
            'status' => 'active',
            'created_by' => $owner->id,
            'last_reminder_at' => now()->subDays(10),
        ]);

        Artisan::call('contracts:check-deadlines');
        $this->assertSame(0, AppNotification::where('user_id', $owner->id)->count());
    }
}
