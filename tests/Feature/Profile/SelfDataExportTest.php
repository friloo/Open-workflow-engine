<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_own_data_zip(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $resp = $this->actingAs($user)->get(route('profile.data_export'));
        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/zip');
    }

    public function test_self_export_creates_audit_entry(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.data_export'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'gdpr.self_export',
            'user_id' => $user->id,
        ]);
    }

    public function test_data_export_link_visible_in_profile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Meine Daten herunterladen');
    }
}
