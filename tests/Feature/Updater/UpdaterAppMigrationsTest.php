<?php

namespace Tests\Feature\Updater;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Updater\UpdaterFactory;

class UpdaterAppMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_caches_endpoint_runs_artisan_clear_commands(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->postJson(route('admin.update.caches.clear'));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $data = $resp->json('data');
        $this->assertSame('ok', $data['view:clear'] ?? null);
        $this->assertSame('ok', $data['config:clear'] ?? null);
        $this->assertSame('ok', $data['route:clear'] ?? null);
        $this->assertSame('ok', $data['cache:clear'] ?? null);
    }

    public function test_migration_status_includes_app_and_updater_sections(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get(route('admin.update.migrations'));
        $resp->assertOk();
        $data = $resp->json('data');
        $this->assertArrayHasKey('updater', $data);
        $this->assertArrayHasKey('app', $data);
        $this->assertArrayHasKey('applied', $data['updater']);
        $this->assertArrayHasKey('pending', $data['app']);
    }

    public function test_run_migrations_endpoint_returns_counts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->postJson(route('admin.update.migrations.run'));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $resp->assertJsonStructure(['ok', 'data' => ['updater_applied', 'app_applied']]);
    }

    public function test_update_manager_clear_app_caches_marks_each_command(): void
    {
        $m = UpdaterFactory::create(DB::connection());
        $results = $m->clearAppCaches();
        $this->assertSame('ok', $results['view:clear']);
        $this->assertSame('ok', $results['config:clear']);
        $this->assertSame('ok', $results['route:clear']);
        $this->assertSame('ok', $results['cache:clear']);
    }
}
