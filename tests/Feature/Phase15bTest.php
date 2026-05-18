<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\AppNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class Phase15bTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_two_factor_setup_flow(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get(route('two-factor.show'))->assertOk();
        $secret = session('2fa.pending_secret');
        $this->assertNotEmpty($secret);

        $g2fa = new Google2FA();
        $code = $g2fa->getCurrentOtp($secret);

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertNotEmpty($user->getTwoFactorRecoveryCodes());
    }

    public function test_login_redirects_to_two_factor_when_enabled(): void
    {
        $user = $this->admin();
        $g2fa = new Google2FA();
        $secret = $g2fa->generateSecretKey();
        $user->setTwoFactorSecret($secret);
        $user->setTwoFactorRecoveryCodes(['ABCDE-12345']);
        $user->two_factor_confirmed_at = now();
        $user->password = bcrypt('secret-pass');
        $user->save();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertFalse(auth()->check());

        $code = $g2fa->getCurrentOtp($secret);
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect();
        $this->assertTrue(auth()->check());
    }

    public function test_api_token_generation_and_auth(): void
    {
        $user = $this->admin();
        $resp = $this->actingAs($user)->post(route('tokens.store'), [
            'name' => 'CI-Bot',
            'abilities' => ['workflows.run'],
        ])->assertRedirect();
        $plain = session('api_token.plain');
        $this->assertNotEmpty($plain);
        $this->assertStringStartsWith('owe_', $plain);

        $token = ApiToken::where('user_id', $user->id)->first();
        $this->assertSame(['workflows.run'], $token->abilities);
        $this->assertTrue($token->isActive());
        $this->assertTrue($token->can('workflows.run'));
        $this->assertFalse($token->can('system.settings'));

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_api_rejects_revoked_token(): void
    {
        $user = $this->admin();
        $res = ApiToken::generate($user, 't', null);
        $res['token']->update(['revoked_at' => now()]);
        $this->withHeader('Authorization', 'Bearer '.$res['plain'])
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    public function test_notification_bell_lifecycle(): void
    {
        $user = $this->admin();
        AppNotification::send($user, 'test', 'Hallo', 'Welt', '/dashboard');
        AppNotification::send($user, 'test', 'Zweite');

        $resp = $this->actingAs($user)->getJson(route('notifications.dropdown'));
        $resp->assertOk()->assertJsonPath('unread', 2);
        $this->assertCount(2, $resp->json('items'));

        $n = AppNotification::first();
        $this->actingAs($user)->get(route('notifications.read', $n))->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);

        $this->actingAs($user)->post(route('notifications.read_all'));
        $this->assertSame(0, AppNotification::whereNull('read_at')->count());
    }
}
