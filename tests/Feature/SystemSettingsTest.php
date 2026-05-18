<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_smtp_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.settings.mail.update'), [
            'transport' => 'smtp',
            'host' => 'mail.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'noreply@example.com',
            'password' => 'supersecret',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'OWE',
            'timeout' => 15,
        ])->assertRedirect(route('admin.settings.index'));

        $this->assertSame('mail.example.com', Settings::get('mail.host'));
        $this->assertSame('supersecret', Settings::get('mail.password'));

        // Password is stored encrypted at rest.
        $row = Setting::find('mail.password');
        $this->assertNotSame('supersecret', $row->value);
        $this->assertSame('supersecret', Crypt::decryptString($row->value));
    }

    public function test_empty_password_keeps_previously_stored_value(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Settings::set('mail.password', 'oldsecret');

        $this->actingAs($admin)->post(route('admin.settings.mail.update'), [
            'transport' => 'smtp',
            'host' => 'mail.example.com',
            'port' => 587,
            'password' => '',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'OWE',
        ])->assertRedirect();

        $this->assertSame('oldsecret', Settings::get('mail.password'));
    }

    public function test_settings_provider_overrides_mail_config_at_runtime(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Settings::set('mail.transport', 'smtp');
        Settings::set('mail.host', 'overridden.example.com');
        Settings::set('mail.port', 2525);

        // Re-boot the provider by registering it again
        $provider = new \App\Providers\SettingsServiceProvider($this->app);
        $provider->boot();

        $this->assertSame('overridden.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $emp = User::factory()->create();
        $emp->assignRole('employee');

        $this->actingAs($emp)->get(route('admin.settings.index'))->assertForbidden();
    }
}
