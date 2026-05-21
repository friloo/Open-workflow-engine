<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_with_copy_from_preselects_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');

        $source = Role::create(['slug' => 'src', 'name' => 'Quelle', 'description' => 'Q']);
        $source->permissions()->sync(Permission::whereIn('slug', ['users.view', 'audit.view'])->pluck('id'));

        $resp = $this->actingAs($admin)->get(route('admin.roles.create', ['copy_from' => $source->id]))->assertOk();

        // Header zeigt 'Kopie von Quelle'
        $resp->assertSee('Kopie von Quelle');
        // Permission users.view sollte als checked rendern
        $content = $resp->getContent();
        $usersViewId = Permission::where('slug', 'users.view')->value('id');
        $this->assertStringContainsString('value="' . $usersViewId . '" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked', $content);
    }
}
