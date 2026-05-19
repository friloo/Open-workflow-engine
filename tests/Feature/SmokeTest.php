<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_support_renders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.settings.support'))->assertOk();
    }

    public function test_support_show_404_when_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('support.show'))->assertNotFound();
    }

    public function test_support_show_200_when_enabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        \App\Support\Settings::set('support.enabled', true);
        \App\Support\Settings::set('support.mode', 'mail');
        \App\Support\Settings::set('support.email', 'help@example.com');

        $emp = User::factory()->create();
        $emp->assignRole('employee');

        $this->actingAs($emp)->get(route('support.show'))->assertOk();
    }
}
