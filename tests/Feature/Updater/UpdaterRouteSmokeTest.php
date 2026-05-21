<?php

namespace Tests\Feature\Updater;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdaterRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_update_index_renders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get(route('admin.update.index'));
        if ($resp->status() !== 200) {
            dump([
                'status' => $resp->status(),
                'content' => substr($resp->getContent(), 0, 4000),
            ]);
        }
        $resp->assertOk();
    }

    public function test_admin_update_progress_returns_json(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get(route('admin.update.progress'));
        if ($resp->status() !== 200) {
            dump(['status' => $resp->status(), 'content' => substr($resp->getContent(), 0, 4000)]);
        }
        $resp->assertOk();
    }

    public function test_admin_update_migrations_returns_json(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get(route('admin.update.migrations'));
        if ($resp->status() !== 200) {
            dump(['status' => $resp->status(), 'content' => substr($resp->getContent(), 0, 4000)]);
        }
        $resp->assertOk();
    }
}
