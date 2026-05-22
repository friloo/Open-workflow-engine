<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_activity_page_renders_with_deliveries(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $wh = Webhook::create([
            'name' => 'Test-Hook',
            'url' => 'https://example.com/hook',
            'events' => ['instance.completed'],
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        WebhookDelivery::create([
            'webhook_id' => $wh->id,
            'event' => 'instance.completed',
            'response_code' => 200,
            'ok' => true,
            'duration_ms' => 123,
            'sent_at' => now(),
        ]);
        WebhookDelivery::create([
            'webhook_id' => $wh->id,
            'event' => 'instance.failed',
            'response_code' => 500,
            'ok' => false,
            'duration_ms' => 5000,
            'error' => 'Internal Server Error',
            'sent_at' => now(),
        ]);

        $resp = $this->actingAs($admin)->get(route('admin.webhooks.activity', $wh));
        $resp->assertOk();
        $resp->assertSee('Test-Hook');
        $resp->assertSee('instance.completed');
        $resp->assertSee('500');
    }
}
