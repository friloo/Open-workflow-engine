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
        $this->get('/install')->assertOk()->assertSee('System-Pruefung')->assertSee('PHP-Version');
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
        // Mindestens ein User noetig, damit dbReady greift
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
    }
}
