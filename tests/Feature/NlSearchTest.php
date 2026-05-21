<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AIClient;
use App\Services\NlSearchService;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NlSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_not_available_when_ai_disabled(): void
    {
        Settings::set('ai.enabled', false, null);
        $this->assertFalse(app(NlSearchService::class)->isAvailable());
    }

    public function test_search_not_available_when_ai_not_configured(): void
    {
        Settings::set('ai.enabled', true, null);
        Settings::set('ai.base_url', null, null);
        Settings::set('ai.model', null, null);
        $this->assertFalse(app(NlSearchService::class)->isAvailable());
    }

    public function test_search_available_when_enabled_configured_and_feature_on(): void
    {
        Settings::set('ai.enabled', true, null);
        Settings::set('ai.base_url', 'https://api.openai.com/v1', null);
        Settings::set('ai.model', 'gpt-4o-mini', null);
        // NL-Search ist Opt-in: muss explizit aktiviert werden
        Settings::set('ai.feature.nl_search', true, null);
        $this->assertTrue(app(NlSearchService::class)->isAvailable());
    }

    public function test_search_not_available_when_feature_off_even_if_master_on(): void
    {
        Settings::set('ai.enabled', true, null);
        Settings::set('ai.base_url', 'https://api.openai.com/v1', null);
        Settings::set('ai.model', 'gpt-4o-mini', null);
        Settings::set('ai.feature.nl_search', false, null);
        $this->assertFalse(app(NlSearchService::class)->isAvailable());
    }

    public function test_form_renders_unavailable_when_ai_off(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Settings::set('ai.enabled', false, null);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('search.nl'))->assertOk()
            ->assertSee('KI ist deaktiviert oder nicht konfiguriert');
    }

    public function test_ask_endpoint_rejects_when_ai_off(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Settings::set('ai.enabled', false, null);
        $user = User::factory()->create();

        $resp = $this->actingAs($user)->postJson(route('search.nl.ask'), ['query' => 'Verträge in 30 Tagen']);
        $resp->assertStatus(422);
        $resp->assertJsonPath('ok', false);
    }

    public function test_ai_client_chat_blocks_when_disabled(): void
    {
        Settings::set('ai.enabled', false, null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('global deaktiviert');
        app(AIClient::class)->chat([['role' => 'user', 'content' => 'test']]);
    }

    public function test_ai_client_is_ready_combines_flags(): void
    {
        $c = app(AIClient::class);
        Settings::set('ai.enabled', true, null);
        Settings::set('ai.base_url', 'https://x', null);
        Settings::set('ai.model', 'm', null);
        $this->assertTrue($c->isReady());

        Settings::set('ai.enabled', false, null);
        $this->assertFalse($c->isReady());
    }
}
