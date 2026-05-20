<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SsoTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_no_sso_buttons_when_disabled(): void
    {
        config([
            'services.microsoft-azure.enabled' => false,
            'services.oidc.enabled' => false,
            'services.google.enabled' => false,
            'services.saml.enabled' => false,
        ]);

        $this->get('/login')->assertOk()
            ->assertDontSee('Mit Microsoft anmelden')
            ->assertDontSee('Mit Single Sign-On anmelden')
            ->assertDontSee('Mit Google anmelden')
            ->assertDontSee('Mit SAML anmelden');
    }

    public function test_login_page_shows_enabled_sso_buttons(): void
    {
        config([
            'services.microsoft-azure.enabled' => true,
            'services.oidc.enabled' => true,
            'services.oidc.button_label' => 'Mit Keycloak anmelden',
            'services.google.enabled' => true,
            'services.saml.enabled' => true,
        ]);

        $resp = $this->get('/login')->assertOk();
        $resp->assertSee('Mit Microsoft anmelden');
        $resp->assertSee('Mit Keycloak anmelden');
        $resp->assertSee('Mit Google anmelden');
        $resp->assertSee('Mit SAML anmelden');
    }

    public function test_oidc_redirect_returns_404_when_disabled(): void
    {
        config(['services.oidc.enabled' => false]);
        $this->get('/auth/oidc/redirect')->assertNotFound();
    }

    public function test_google_redirect_returns_404_when_disabled(): void
    {
        config(['services.google.enabled' => false]);
        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_saml_redirect_returns_404_when_disabled(): void
    {
        config(['services.saml.enabled' => false]);
        $this->get('/auth/saml/redirect')->assertNotFound();
    }

    public function test_saml_metadata_returns_404_when_disabled(): void
    {
        config(['services.saml.enabled' => false]);
        $this->get('/auth/saml/metadata')->assertNotFound();
    }

    public function test_admin_can_open_sso_settings_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.settings.sso'))
            ->assertOk()
            ->assertSee('OpenID Connect')
            ->assertSee('Google Workspace')
            ->assertSee('SAML 2.0');
    }

    public function test_admin_can_save_oidc_settings(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->post(route('admin.settings.sso.update'), [
            'oidc_enabled' => '1',
            'oidc_auto_provision' => '1',
            'oidc_issuer' => 'https://id.example.com/realms/main',
            'oidc_client_id' => 'owe-app',
            'oidc_client_secret' => 'secret-value-xyz',
            'oidc_redirect' => 'https://owe.example.com/auth/oidc/callback',
            'oidc_scopes' => 'openid email profile',
            'oidc_button_label' => 'Mit Keycloak anmelden',
            'oidc_default_role' => 'employee',
        ]);
        $resp->assertRedirect(route('admin.settings.sso'));
        $resp->assertSessionHasNoErrors();

        $this->assertTrue((bool) Settings::get('auth.oidc.enabled'));
        $this->assertSame('https://id.example.com/realms/main', Settings::get('auth.oidc.issuer'));
        $this->assertSame('secret-value-xyz', Settings::get('auth.oidc.client_secret'));
        $this->assertSame('Mit Keycloak anmelden', Settings::get('auth.oidc.button_label'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.sso.updated']);
    }

    public function test_oidc_client_secret_stays_when_left_blank_on_update(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Settings::set('auth.oidc.client_secret', 'original-secret', $admin->id);

        $this->actingAs($admin)->post(route('admin.settings.sso.update'), [
            'oidc_enabled' => '1',
            'oidc_client_id' => 'owe-app',
            // oidc_client_secret intentionally empty
        ])->assertRedirect(route('admin.settings.sso'));

        $this->assertSame('original-secret', Settings::get('auth.oidc.client_secret'));
    }

    public function test_google_settings_save_and_encrypt_secret(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.settings.sso.update'), [
            'google_enabled' => '1',
            'google_client_id' => '12345.apps.googleusercontent.com',
            'google_client_secret' => 'google-secret',
            'google_hosted_domain' => 'firma.de',
            'google_default_role' => 'employee',
        ])->assertRedirect(route('admin.settings.sso'));

        $this->assertTrue((bool) Settings::get('auth.google.enabled'));
        $this->assertSame('firma.de', Settings::get('auth.google.hosted_domain'));
        $this->assertSame('google-secret', Settings::get('auth.google.client_secret'));
    }

    public function test_saml_settings_persist_certificate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cert = "-----BEGIN CERTIFICATE-----\nMIIDAB+nA1ZGUx\n-----END CERTIFICATE-----";

        $this->actingAs($admin)->post(route('admin.settings.sso.update'), [
            'saml_enabled' => '1',
            'saml_idp_entity_id' => 'https://idp.example.com/saml',
            'saml_idp_sso_url' => 'https://idp.example.com/saml/sso',
            'saml_idp_x509_cert' => $cert,
            'saml_default_role' => 'employee',
        ])->assertRedirect(route('admin.settings.sso'));

        $this->assertSame($cert, Settings::get('auth.saml.idp_x509_cert'));
    }
}
