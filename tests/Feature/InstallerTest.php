<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Marker wieder entfernen, damit der Installer erreichbar ist.
        @unlink(Installer::markerPath());
    }

    protected function tearDown(): void
    {
        @unlink(Installer::markerPath());
        parent::tearDown();
    }

    public function test_root_redirects_to_install_when_marker_missing(): void
    {
        $this->get('/')->assertRedirect('/install');
    }

    public function test_welcome_page_renders_with_checks(): void
    {
        $this->get('/install')->assertOk()->assertSee('System-Prüfung')->assertSee('PHP-Version');
    }

    public function test_database_page_renders(): void
    {
        $this->get('/install/database')->assertOk()->assertSee('Datenbank');
    }

    public function test_admin_step_creates_admin_user(): void
    {
        // DB ist via RefreshDatabase schon migriert. Seeder noch nicht.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $resp = $this->post('/install/admin', [
            'name' => 'Chef',
            'email' => 'chef@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('install.finish'));

        $user = User::where('email', 'chef@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_finish_step_sets_marker(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        // Mindestens ein User nötig, damit dbReady greift
        $u = User::factory()->create();
        $u->roles()->attach(Role::where('slug', 'admin')->first()->id);

        $this->assertFalse(Installer::isInstalled());
        $this->get(route('install.finish'))->assertOk()->assertSee('Installation abgeschlossen');
        $this->assertTrue(Installer::isInstalled());
    }

    public function test_installer_blocked_after_marker_set(): void
    {
        Installer::markInstalled();
        $this->get('/install')->assertRedirect('/');
        $this->get('/install/database')->assertRedirect('/');
        $this->get('/install/admin')->assertRedirect('/');
        $this->get('/install/restore')->assertRedirect('/');
    }

    public function test_welcome_shows_restore_option(): void
    {
        $this->get('/install')->assertOk()
            ->assertSee('Frische Installation')
            ->assertSee('Aus Backup wiederherstellen');
    }

    public function test_restore_page_renders(): void
    {
        $this->get('/install/restore')->assertOk()
            ->assertSee('Backup-Datei')
            ->assertSee('Max. Upload-Größe');
    }

    public function test_restore_rejects_when_driver_mismatch(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        @mkdir(storage_path('app/attachments'), 0775, true);
        $path = app(\App\Services\BackupService::class)->create();

        $upload = new \Illuminate\Http\UploadedFile($path, basename($path), 'application/zip', null, true);

        $this->post('/install/restore', [
            'driver' => 'mysql',
            'host' => 'localhost', 'port' => '3306',
            'database' => 'x', 'username' => 'x', 'password' => 'x',
            'app_name' => 'Test', 'app_url' => 'http://localhost',
            'backup_file' => $upload,
            'confirm' => '1',
        ])->assertOk()
          ->assertSee('Backup wurde mit sqlite erstellt');

        foreach (app(\App\Services\BackupService::class)->list() as $b) {
            @unlink(app(\App\Services\BackupService::class)->path($b['file']));
        }
    }

    public function test_restore_requires_confirm(): void
    {
        $this->post('/install/restore', [
            'driver' => 'sqlite',
            'app_name' => 'Test', 'app_url' => 'http://localhost',
        ])->assertSessionHasErrors(['backup_file', 'confirm']);
    }
}
