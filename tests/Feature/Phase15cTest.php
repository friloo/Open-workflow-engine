<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Update\UpdateChannelFactory;
use App\Services\Update\UpdateManager;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase15cTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_check_returns_latest_when_versions_differ(): void
    {
        $oldSha = str_repeat('a', 40);
        $newSha = str_repeat('b', 40);
        file_put_contents(base_path('.version'), $oldSha);

        $channel = UpdateChannelFactory::current();
        Http::fake([
            $channel->baseUrl.'/version' => Http::response($newSha, 200),
        ]);

        try {
            $manager = app(UpdateManager::class);
            $check = $manager->check();
            $this->assertSame($oldSha, $check['current']);
            $this->assertSame($newSha, $check['latest']);
            $this->assertTrue($check['has_update']);
        } finally {
            @unlink(base_path('.version'));
        }
    }

    public function test_check_no_update_when_versions_match(): void
    {
        $sha = str_repeat('c', 40);
        file_put_contents(base_path('.version'), $sha);
        $channel = UpdateChannelFactory::current();
        Http::fake([
            $channel->baseUrl.'/version' => Http::response($sha, 200),
        ]);
        try {
            $check = app(UpdateManager::class)->check();
            $this->assertFalse($check['has_update']);
        } finally {
            @unlink(base_path('.version'));
        }
    }

    public function test_check_returns_error_on_invalid_sha(): void
    {
        $channel = UpdateChannelFactory::current();
        Http::fake([$channel->baseUrl.'/version' => Http::response('not-a-sha', 200)]);
        $check = app(UpdateManager::class)->check();
        $this->assertNull($check['latest']);
        $this->assertNotEmpty($check['error']);
    }

    public function test_admin_ui_shows_status(): void
    {
        $sha = str_repeat('d', 40);
        $channel = UpdateChannelFactory::current();
        Http::fake([$channel->baseUrl.'/version' => Http::response($sha, 200)]);

        $resp = $this->actingAs($this->admin())->get(route('admin.update.index'));
        $resp->assertOk()->assertSee('System-Update');
    }

    public function test_channel_can_be_changed(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.update.channel'), ['channel' => 'development'])
            ->assertRedirect();
        $this->assertSame('development', Settings::get('update.channel'));
    }
}
