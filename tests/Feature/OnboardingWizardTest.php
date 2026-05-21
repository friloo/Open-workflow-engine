<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_dismiss_wizard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson(route('onboarding.dismiss'))->assertOk();
        $this->assertNotNull($admin->fresh()->onboarding_dismissed_at);
    }

    public function test_admin_can_complete_wizard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson(route('onboarding.complete'))->assertOk();
        $this->assertNotNull($admin->fresh()->onboarding_completed_at);
    }

    public function test_wizard_visible_in_layout_for_fresh_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $resp->assertSee('Open Workflow Engine ist installiert');
    }

    public function test_wizard_hidden_after_completion(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['onboarding_completed_at' => now()]);
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $resp->assertDontSee('Open Workflow Engine ist installiert');
    }
}
