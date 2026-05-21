<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\LdapAuthenticator;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LdapLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_login_still_works_when_ldap_disabled(): void
    {
        config(['services.ldap.enabled' => false]);

        $user = User::factory()->create([
            'email' => 'lokal@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', ['email' => 'lokal@example.com', 'password' => 'secret123'])
            ->assertRedirect('/dashboard');
    }

    public function test_ldap_login_provisions_new_user_on_first_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.ldap.enabled' => true, 'services.ldap.auto_provision' => true, 'services.ldap.default_role' => 'employee']);

        $this->mockLdap([
            'ok' => true,
            'dn' => 'CN=Max Muster,OU=Users,DC=firma,DC=local',
            'email' => 'max.muster@firma.local',
            'name' => 'Max Muster',
        ]);

        $this->post('/login', ['email' => 'mmuster', 'password' => 'ad-pw'])
            ->assertRedirect('/dashboard');

        $u = User::where('email', 'max.muster@firma.local')->first();
        $this->assertNotNull($u, 'User wurde durch LDAP-Login angelegt');
        $this->assertSame('CN=Max Muster,OU=Users,DC=firma,DC=local', $u->ldap_dn);
        $this->assertTrue($u->hasRole('employee'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.ldap.provisioned']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.ldap.login']);
    }

    public function test_ldap_login_reuses_existing_user_matched_by_email(): void
    {
        config(['services.ldap.enabled' => true]);

        $existing = User::factory()->create(['email' => 'erna@firma.local']);

        $this->mockLdap([
            'ok' => true,
            'dn' => 'CN=Erna,OU=Users,DC=firma,DC=local',
            'email' => 'erna@firma.local',
            'name' => 'Erna Beispiel',
        ]);

        $this->post('/login', ['email' => 'erna', 'password' => 'pw'])
            ->assertRedirect('/dashboard');

        $existing->refresh();
        $this->assertSame('CN=Erna,OU=Users,DC=firma,DC=local', $existing->ldap_dn);
        $this->assertSame($existing->id, auth()->id());
    }

    public function test_ldap_failure_falls_back_to_local_password(): void
    {
        config(['services.ldap.enabled' => true]);

        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => Hash::make('localpw'),
        ]);

        $this->mockLdap(['ok' => false, 'error' => 'Benutzer im Verzeichnis nicht gefunden.']);

        $this->post('/login', ['email' => 'admin@local', 'password' => 'localpw'])
            ->assertRedirect('/dashboard');

        $this->assertSame($user->id, auth()->id());
    }

    public function test_ldap_blocks_inactive_account(): void
    {
        config(['services.ldap.enabled' => true]);

        $user = User::factory()->create(['email' => 'gesperrt@firma.local', 'is_active' => false]);

        $this->mockLdap([
            'ok' => true,
            'dn' => 'CN=Gesperrt,OU=Users,DC=firma,DC=local',
            'email' => 'gesperrt@firma.local',
            'name' => 'Gesperrt',
        ]);

        $this->from('/login')->post('/login', ['email' => 'gesperrt', 'password' => 'pw'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.ldap.blocked']);
    }

    public function test_ldap_login_does_not_require_email_format(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.ldap.enabled' => true, 'services.ldap.default_role' => 'employee']);

        $this->mockLdap([
            'ok' => true,
            'dn' => 'CN=Tom,OU=Users,DC=firma,DC=local',
            'email' => 'tom@firma.local',
            'name' => 'Tom',
        ]);

        // 'tom' ist keine gültige E-Mail — sollte aber durchgehen, da LDAP aktiv ist
        $this->post('/login', ['email' => 'tom', 'password' => 'pw'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard');
    }

    public function test_admin_can_save_ldap_settings_and_secret_is_encrypted(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.settings.sso.update'), [
            'ldap_enabled' => '1',
            'ldap_host' => 'ldap.firma.local',
            'ldap_port' => '389',
            'ldap_use_tls' => '1',
            'ldap_base_dn' => 'DC=firma,DC=local',
            'ldap_bind_dn' => 'CN=svc-owe,OU=Service,DC=firma,DC=local',
            'ldap_bind_password' => 'service-pw',
            'ldap_user_filter' => '(&(objectClass=user)(sAMAccountName={username}))',
            'ldap_email_attribute' => 'mail',
            'ldap_name_attribute' => 'displayName',
            'ldap_default_role' => 'employee',
        ])->assertRedirect(route('admin.settings.sso'))->assertSessionHasNoErrors();

        $this->assertTrue((bool) Settings::get('auth.ldap.enabled'));
        $this->assertTrue((bool) Settings::get('auth.ldap.use_tls'));
        $this->assertSame('ldap.firma.local', Settings::get('auth.ldap.host'));
        $this->assertSame(389, (int) Settings::get('auth.ldap.port'));
        $this->assertSame('service-pw', Settings::get('auth.ldap.bind_password'));

        // Bind-Password muss verschlüsselt in der DB stehen
        $raw = \DB::table('system_settings')->where('key', 'auth.ldap.bind_password')->value('value');
        $this->assertNotSame('service-pw', $raw, 'bind_password ist nicht verschlüsselt');
    }

    public function test_admin_can_run_ldap_test_action(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->mockLdap([
            'ok' => true,
            'dn' => 'CN=Probe,DC=firma,DC=local',
            'email' => 'probe@firma.local',
            'name' => 'Probe',
        ]);

        $resp = $this->actingAs($admin)->post(route('admin.settings.sso.test_ldap'), [
            'test_username' => 'probe',
            'test_password' => 'pw',
        ]);

        $resp->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.ldap.test_ok']);
    }

    private function mockLdap(array $result): void
    {
        $fake = new class($result) extends LdapAuthenticator {
            public function __construct(private array $r) {}
            public function authenticate(string $username, string $password): array
            {
                return $this->r;
            }
        };
        $this->app->instance(LdapAuthenticator::class, $fake);
    }
}
