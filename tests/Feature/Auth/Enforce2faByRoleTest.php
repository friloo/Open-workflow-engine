<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Enforce2faByRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_in_2fa_required_role_is_redirected_to_setup(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['slug' => 'finance', 'name' => 'Finanzen', 'requires_2fa' => true]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $resp = $this->actingAs($user)->get(route('dashboard'));
        $resp->assertRedirect(route('two-factor.show'));
    }

    public function test_user_without_2fa_role_is_not_redirected(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['slug' => 'plain', 'name' => 'Plain', 'requires_2fa' => false]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_user_with_2fa_enabled_passes_through(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['slug' => 'finance', 'name' => 'Finanzen', 'requires_2fa' => true]);
        $user = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        $user->setTwoFactorSecret(str_repeat('A', 16));
        $user->save();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_setup_route_is_reachable_even_when_enforced(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['slug' => 'finance', 'name' => 'Finanzen', 'requires_2fa' => true]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('two-factor.show'))->assertOk();
    }
}
