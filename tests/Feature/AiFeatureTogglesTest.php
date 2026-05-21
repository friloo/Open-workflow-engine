<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AIClient;
use App\Services\NlSearchService;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiFeatureTogglesTest extends TestCase
{
    use RefreshDatabase;

    private function aiConfigured(): void
    {
        Settings::set('ai.enabled', true, null);
        Settings::set('ai.base_url', 'https://api.openai.com/v1', null);
        Settings::set('ai.model', 'gpt-4o-mini', null);
    }

    public function test_per_feature_default_is_off_for_all(): void
    {
        $this->aiConfigured();
        $c = app(AIClient::class);
        // Default AUS für jede Feature — KI ist komplett Opt-in
        $this->assertFalse($c->isFeatureEnabled('workflow_design'));
        $this->assertFalse($c->isFeatureEnabled('http_suggest'));
        $this->assertFalse($c->isFeatureEnabled('field_extract'));
        $this->assertFalse($c->isFeatureEnabled('nl_search'));
    }

    public function test_master_off_disables_all_features(): void
    {
        $this->aiConfigured();
        Settings::set('ai.enabled', false, null);
        Settings::set('ai.feature.nl_search', true, null);
        $c = app(AIClient::class);
        $this->assertFalse($c->isFeatureEnabled('nl_search'));
        $this->assertFalse($c->isFeatureEnabled('workflow_design'));
    }

    public function test_admin_can_enable_nl_search(): void
    {
        $this->aiConfigured();
        Settings::set('ai.feature.nl_search', true, null);
        $this->assertTrue(app(NlSearchService::class)->isAvailable());
    }

    public function test_admin_update_writes_all_feature_flags(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.ai.update'), [
            'enabled' => '1',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'features' => [
                'workflow_design' => '1',
                'http_suggest' => '0',
                'field_extract' => '1',
                'nl_search' => '1',
            ],
        ])->assertRedirect();

        $c = app(AIClient::class);
        $this->assertTrue($c->isFeatureEnabled('workflow_design'));
        $this->assertFalse($c->isFeatureEnabled('http_suggest'));
        $this->assertTrue($c->isFeatureEnabled('field_extract'));
        $this->assertTrue($c->isFeatureEnabled('nl_search'));
    }

    public function test_workflow_design_endpoint_rejects_when_feature_off(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->aiConfigured();
        Settings::set('ai.feature.workflow_design', false, null);

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $resp = $this->actingAs($admin)->postJson(route('admin.ai.suggest_workflow'), [
            'description' => 'Bestellantrag',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonPath('error', fn ($v) => str_contains((string) $v, 'Workflow-Entwurf'));
    }

    public function test_http_suggest_endpoint_rejects_when_feature_off(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->aiConfigured();
        Settings::set('ai.feature.http_suggest', false, null);

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $resp = $this->actingAs($admin)->postJson(route('admin.ai.suggest_http'), [
            'input' => 'curl https://api.example.com',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonPath('error', fn ($v) => str_contains((string) $v, 'HTTP-Vorschlag'));
    }

    public function test_known_features_lists_data_access_flags(): void
    {
        $features = AIClient::knownFeatures();
        $this->assertArrayHasKey('nl_search', $features);
        $this->assertTrue($features['nl_search']['data_access']);
        $this->assertFalse($features['workflow_design']['data_access']);
    }
}
