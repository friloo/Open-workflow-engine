<?php

namespace Tests\Feature\Admin;

use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_token_page_for_other_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $service = User::factory()->create(['name' => 'API Bridge', 'is_service_account' => true]);

        $this->actingAs($admin)->get(route('admin.users.tokens.index', $service))
            ->assertOk()
            ->assertSee('API Bridge')
            ->assertSee('Service-Account');
    }

    public function test_admin_can_create_token_for_service_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $service = User::factory()->create(['is_service_account' => true]);
        $service->assignRole('admin'); // Voller Zugriff via Rolle

        $resp = $this->actingAs($admin)->post(route('admin.users.tokens.store', $service), [
            'name' => 'n8n-Bridge',
            'abilities' => ['*'],
            'expires_in_days' => 365,
        ]);
        $resp->assertRedirect(route('admin.users.tokens.index', $service));
        $resp->assertSessionHas('api_token.plain');

        $this->assertSame(1, $service->apiTokens()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'api_token.created_for_user']);
    }

    public function test_admin_cannot_grant_token_abilities_beyond_user_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        // Limit-User mit nur reports.view-Rolle
        $limitRole = Role::create(['slug' => 'reports-only', 'name' => 'Reports', 'description' => 'r']);
        $limitRole->permissions()->sync(\App\Models\Permission::whereIn('slug', ['reports.view'])->pluck('id'));
        $service = User::factory()->create(['is_service_account' => true]);
        $service->roles()->sync([$limitRole->id]);

        $this->actingAs($admin)->post(route('admin.users.tokens.store', $service), [
            'name' => 'BI',
            'abilities' => ['reports.view', 'contracts.manage'], // contracts.manage hat User nicht
        ])->assertSessionHasErrors('abilities');
        $this->assertSame(0, $service->apiTokens()->count());
    }

    public function test_admin_can_revoke_token_for_other_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $service = User::factory()->create();
        $t = ApiToken::generate($service, 'T', ['*'])['token'];

        $this->actingAs($admin)->delete(route('admin.users.tokens.destroy', [$service, $t]))
            ->assertRedirect();
        $this->assertNotNull($t->fresh()->revoked_at);
    }

    public function test_humans_scope_excludes_service_accounts(): void
    {
        $human = User::factory()->create(['is_service_account' => false]);
        $svc = User::factory()->create(['is_service_account' => true]);

        $ids = User::humans()->pluck('id')->all();
        $this->assertContains($human->id, $ids);
        $this->assertNotContains($svc->id, $ids);
    }

    public function test_api_users_default_excludes_service_accounts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['name' => 'Real Admin']);
        $admin->assignRole('admin');
        User::factory()->create(['name' => 'Service Bot', 'is_service_account' => true]);

        $token = ApiToken::generate($admin, 't', ['*'])['plain'];
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $resp = $this->get('/api/v1/users', $headers)->assertOk();
        $names = collect($resp->json('data'))->pluck('name')->all();
        $this->assertContains('Real Admin', $names);
        $this->assertNotContains('Service Bot', $names);

        // include_service=1 -> auch Service-Bots
        $resp2 = $this->get('/api/v1/users?include_service=1', $headers)->assertOk();
        $names2 = collect($resp2->json('data'))->pluck('name')->all();
        $this->assertContains('Service Bot', $names2);
    }
}
