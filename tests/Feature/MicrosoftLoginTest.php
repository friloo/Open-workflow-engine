<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class MicrosoftLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_route_is_404_when_m365_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->get(route('auth.m365.redirect'))->assertNotFound();
    }

    public function test_callback_provisions_new_user_with_default_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->enableM365();

        $this->mockSocialiteUser(oid: 'abc-123', email: 'new@example.com', name: 'Neue Person');

        $this->get(route('auth.m365.callback'))->assertRedirect(route('dashboard'));

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('abc-123', $user->m365_object_id);
        $this->assertTrue($user->hasRole('employee'));
        $this->assertTrue($user->prefer_m365_supervisor);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_links_existing_user_by_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->enableM365();

        $existing = User::factory()->create(['email' => 'known@example.com']);
        $existing->assignRole('employee');

        $this->mockSocialiteUser(oid: 'oid-known', email: 'known@example.com', name: $existing->name);

        $this->get(route('auth.m365.callback'))->assertRedirect();
        $existing->refresh();
        $this->assertSame('oid-known', $existing->m365_object_id);
    }

    public function test_callback_rejects_unknown_user_when_auto_provision_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->enableM365(autoProvision: false);
        $this->mockSocialiteUser(oid: 'oid-x', email: 'stranger@example.com');

        $this->get(route('auth.m365.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(User::where('email', 'stranger@example.com')->first());
    }

    private function enableM365(bool $autoProvision = true): void
    {
        Settings::set('auth.m365.enabled', true);
        Settings::set('auth.m365.client_id', 'fake');
        Settings::set('auth.m365.client_secret', 'fake');
        Settings::set('auth.m365.tenant_id', 'common');
        Settings::set('auth.m365.auto_provision', $autoProvision);
        Settings::set('auth.m365.default_role', 'employee');
        Settings::set('auth.m365.redirect_uri', url('/auth/m365/callback'));

        (new \App\Providers\SettingsServiceProvider($this->app))->boot();
    }

    private function mockSocialiteUser(string $oid, string $email, string $name = 'Test User'): void
    {
        $socUser = Mockery::mock(SocialiteUserContract::class);
        $socUser->shouldReceive('getId')->andReturn($oid);
        $socUser->shouldReceive('getEmail')->andReturn($email);
        $socUser->shouldReceive('getName')->andReturn($name);
        $socUser->user = ['userPrincipalName' => $email, 'jobTitle' => null, 'department' => null, 'mobilePhone' => null];

        Socialite::shouldReceive('driver->user')->andReturn($socUser);
    }
}
