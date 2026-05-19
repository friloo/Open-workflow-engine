<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Update\UpdateChannelFactory;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_command_pushes_notification_to_admins_when_update_available(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $other->assignRole('employee');

        $oldSha = str_repeat('a', 40);
        $newSha = str_repeat('b', 40);
        file_put_contents(base_path('.version'), $oldSha);

        try {
            $channel = UpdateChannelFactory::current();
            Http::fake([$channel->baseUrl.'/version' => Http::response($newSha, 200)]);

            $this->artisan('update:notify-available')->assertSuccessful();

            // Admin bekommt
            $this->assertSame(1, AppNotification::where('user_id', $admin->id)
                ->where('type', 'system.update.available')->count());
            // employee nicht
            $this->assertSame(0, AppNotification::where('user_id', $other->id)
                ->where('type', 'system.update.available')->count());

            $this->assertSame($newSha, Settings::get('update.last_notified_sha'));
        } finally {
            @unlink(base_path('.version'));
        }
    }

    public function test_command_does_not_resend_same_version(): void
    {
        $admin = $this->admin();
        $oldSha = str_repeat('a', 40);
        $newSha = str_repeat('b', 40);
        file_put_contents(base_path('.version'), $oldSha);

        try {
            $channel = UpdateChannelFactory::current();
            Http::fake([$channel->baseUrl.'/version' => Http::response($newSha, 200)]);

            $this->artisan('update:notify-available');
            $this->artisan('update:notify-available');

            $this->assertSame(1, AppNotification::where('user_id', $admin->id)
                ->where('type', 'system.update.available')->count());
        } finally {
            @unlink(base_path('.version'));
        }
    }

    public function test_command_does_nothing_when_no_update(): void
    {
        $admin = $this->admin();
        $sha = str_repeat('c', 40);
        file_put_contents(base_path('.version'), $sha);

        try {
            $channel = UpdateChannelFactory::current();
            Http::fake([$channel->baseUrl.'/version' => Http::response($sha, 200)]);

            $this->artisan('update:notify-available')->assertSuccessful();
            $this->assertSame(0, AppNotification::count());
        } finally {
            @unlink(base_path('.version'));
        }
    }
}
