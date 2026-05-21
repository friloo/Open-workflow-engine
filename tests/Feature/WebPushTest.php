<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushSender;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_a_subscription(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $resp = $this->actingAs($user)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'fakep256dh', 'auth' => 'fakeauth'],
        ]);

        $resp->assertOk();
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'public_key' => 'fakep256dh',
            'auth_token' => 'fakeauth',
        ]);
    }

    public function test_duplicate_subscription_is_updated_not_doubled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $payload = [
            'endpoint' => 'https://example.com/push/abc',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ];
        $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();
        $payload['keys']['p256dh'] = 'c';
        $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();

        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count());
        $this->assertSame('c', PushSubscription::first()->public_key);
    }

    public function test_user_can_unsubscribe(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'https://foo/bar',
            'public_key' => 'a', 'auth_token' => 'b',
        ]);

        $this->actingAs($user)->postJson(route('push.unsubscribe'), ['endpoint' => 'https://foo/bar'])
            ->assertOk();
        $this->assertSame(0, PushSubscription::where('user_id', $user->id)->count());
    }

    public function test_push_is_disabled_without_vapid_keys(): void
    {
        $sender = new WebPushSender();
        $this->assertFalse($sender->isEnabled());
    }

    public function test_app_notification_send_does_not_crash_without_push(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $n = AppNotification::send($user, 'test', 'Hallo', 'Body', '/');
        $this->assertNotNull($n);
        $this->assertSame('Hallo', $n->title);
    }

    public function test_app_notification_send_attempts_push_when_enabled(): void
    {
        // Fake VAPID keys (32-byte+ base64url strings) damit isEnabled() true ist
        Settings::set('auth.push.vapid_public', str_repeat('A', 64));
        Settings::set('auth.push.vapid_private', str_repeat('B', 44));

        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        // Subscription anlegen
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'https://fake.example.com/push/abc',
            'public_key' => 'fakep256dh', 'auth_token' => 'fakeauth',
        ]);

        // Mock WebPushSender im Container
        $mock = new class extends WebPushSender {
            public int $calls = 0;
            public function isEnabled(): bool { return true; }
            public function sendToUser(User $u, string $t, ?string $b = null, ?string $url = null): int
            {
                $this->calls++;
                return 1;
            }
        };
        $this->app->instance(WebPushSender::class, $mock);

        AppNotification::send($user, 'test', 'Push-Test');
        $this->assertSame(1, $mock->calls);
    }
}
