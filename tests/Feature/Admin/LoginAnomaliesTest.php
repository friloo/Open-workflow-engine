<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\LoginAnomalies;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAnomaliesTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_creates_audit_entry(): void
    {
        $user = User::factory()->create();
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.failed',
        ]);
    }

    public function test_snapshot_counts_failed_logins_in_window(): void
    {
        // 3 fehlgeschlagene Logins simulieren
        for ($i = 0; $i < 3; $i++) {
            AuditLog::create([
                'event' => 'auth.login.failed',
                'new_values' => ['email' => "user{$i}@test.de", 'ip' => '1.2.3.4'],
                'created_at' => now(),
                'hash' => str_repeat('a', 64),
                'prev_hash' => str_repeat('0', 64),
            ]);
        }

        $snap = app(LoginAnomalies::class)->snapshot(24);
        $this->assertSame(3, $snap['failed_24h']);
    }

    public function test_admin_can_open_anomalies_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.login_anomalies.index'))
            ->assertOk()->assertSee('Login-Anomalien');
    }
}
