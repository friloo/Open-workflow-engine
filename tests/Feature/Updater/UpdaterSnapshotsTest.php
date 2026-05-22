<?php

namespace Tests\Feature\Updater;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Updater\UpdaterFactory;

class UpdaterSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshots_endpoint_returns_list(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get(route('admin.update.snapshots'));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $this->assertIsArray($resp->json('data'));
    }

    public function test_create_pre_update_snapshot_writes_meta(): void
    {
        $m = UpdaterFactory::create(DB::connection());

        $file = $m->createPreUpdateSnapshot(null);
        $this->assertNotEmpty($file);

        try {
            // Meta-Datei existiert und enthaelt den Filename
            $metaPath = base_path('.updater-snapshots.json');
            $this->assertFileExists($metaPath);
            $meta = json_decode(file_get_contents($metaPath), true);
            $this->assertArrayHasKey($file, $meta);
            $this->assertSame(true, true); // sanity: code ran through
        } finally {
            @unlink(storage_path('app/backups/'.$file));
            @unlink(base_path('.updater-snapshots.json'));
        }
    }

    public function test_snapshot_restore_requires_confirmation_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->postJson(route('admin.update.snapshots.restore'), [
            'file' => 'whatever.zip',
            'confirm' => 'wrong',
        ]);
        // Validierung schlaegt fehl wegen confirm != RESTORE
        $resp->assertStatus(422);
    }
}
