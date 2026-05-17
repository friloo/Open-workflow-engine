<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_roles_and_permissions_exist(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(4, Role::count());
        $this->assertGreaterThanOrEqual(15, Permission::count());

        $admin = Role::where('slug', 'admin')->firstOrFail();
        $this->assertTrue($admin->is_system);
    }

    public function test_admin_has_implicit_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->hasPermission('any.fictitious.permission'));
        $this->assertTrue($user->hasPermission('users.view'));
    }

    public function test_non_admin_only_has_assigned_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->assertTrue($user->hasPermission('workflows.run'));
        $this->assertFalse($user->hasPermission('users.delete'));
    }

    public function test_admin_routes_require_correct_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $this->actingAs($employee)->get('/admin/users')->assertForbidden();
        $this->actingAs($employee)->get('/admin/roles')->assertForbidden();
        $this->actingAs($employee)->get('/admin/audit')->assertForbidden();
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/admin/audit')->assertOk();
    }
}
