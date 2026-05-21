<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractAclTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private Role $hrRole;
    private User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->employee = User::factory()->create();
        $this->employee->assignRole('employee');

        // Eigene HR-Rolle erstellen, plus User mit der Rolle
        $this->hrRole = Role::create(['slug' => 'hr', 'name' => 'HR', 'description' => 'HR']);
        $this->hrRole->permissions()->sync(\App\Models\Permission::where('slug', 'contracts.view')->pluck('id'));
        $this->hrUser = User::factory()->create();
        $this->hrUser->roles()->sync([$this->hrRole->id]);
    }

    public function test_employee_without_role_cannot_see_typed_contract(): void
    {
        $type = ContractType::create(['name' => 'Personal', 'slug' => 'personal', 'default_notice_period_days' => 90]);
        $type->roles()->sync([$this->hrRole->id => ['can_manage' => true]]);
        Contract::create([
            'name' => 'Arbeitsvertrag Mueller',
            'contract_type_id' => $type->id,
            'notice_period_days' => 30,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // Employee hat keine contracts.view → 403 vom Middleware
        $this->actingAs($this->employee)->get(route('contracts.index'))->assertForbidden();
    }

    public function test_hr_user_sees_only_typed_contracts(): void
    {
        $personal = ContractType::create(['name' => 'Personal', 'slug' => 'personal', 'default_notice_period_days' => 90]);
        $personal->roles()->sync([$this->hrRole->id => ['can_manage' => true]]);

        $wartung = ContractType::create(['name' => 'Wartung', 'slug' => 'wartung', 'default_notice_period_days' => 60]);
        // Wartung NICHT für HR freigeschaltet

        $a = Contract::create(['name' => 'Personalvertrag X', 'contract_type_id' => $personal->id, 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $this->admin->id]);
        $b = Contract::create(['name' => 'Heizungswartung', 'contract_type_id' => $wartung->id, 'notice_period_days' => 60, 'status' => 'active', 'created_by' => $this->admin->id]);

        $visible = Contract::query()->visibleTo($this->hrUser)->pluck('id')->all();
        $this->assertContains($a->id, $visible);
        $this->assertNotContains($b->id, $visible);
    }

    public function test_owner_always_sees_own_contract_even_without_type_role(): void
    {
        $type = ContractType::create(['name' => 'Sonst', 'slug' => 'sonst', 'default_notice_period_days' => 90]);
        // KEINE Rolle freigeschaltet
        $c = Contract::create([
            'name' => 'Mein Vertrag',
            'contract_type_id' => $type->id,
            'notice_period_days' => 30,
            'status' => 'active',
            'owner_user_id' => $this->hrUser->id,
            'created_by' => $this->admin->id,
        ]);

        $visible = Contract::query()->visibleTo($this->hrUser)->pluck('id')->all();
        $this->assertContains($c->id, $visible);
    }

    public function test_per_contract_role_grants_access(): void
    {
        $type = ContractType::create(['name' => 'Sonst', 'slug' => 'sonst2', 'default_notice_period_days' => 90]);
        $c = Contract::create([
            'name' => 'Per-Vertrag',
            'contract_type_id' => $type->id,
            'notice_period_days' => 30,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
        $c->roles()->sync([$this->hrRole->id => ['can_manage' => false]]);

        $visible = Contract::query()->visibleTo($this->hrUser)->pluck('id')->all();
        $this->assertContains($c->id, $visible);

        // Aber kein manage
        $this->assertFalse($c->userCanManage($this->hrUser));
    }

    public function test_can_manage_via_per_contract_role(): void
    {
        // HR-Rolle bekommt zusaetzlich contracts.manage
        $this->hrRole->permissions()->sync(
            \App\Models\Permission::whereIn('slug', ['contracts.view', 'contracts.manage'])->pluck('id')
        );

        $type = ContractType::create(['name' => 'X', 'slug' => 'x', 'default_notice_period_days' => 90]);
        $c = Contract::create([
            'name' => 'V', 'contract_type_id' => $type->id,
            'notice_period_days' => 30, 'status' => 'active', 'created_by' => $this->admin->id,
        ]);
        $c->roles()->sync([$this->hrRole->id => ['can_manage' => true]]);

        $this->assertTrue($c->userCanManage($this->hrUser));
    }

    public function test_admin_can_create_and_manage_contract_types(): void
    {
        $resp = $this->actingAs($this->admin)->post(route('contract-types.store'), [
            'name' => 'Mietvertrag',
            'default_notice_period_days' => 90,
            'color' => '#ef4444',
            'roles' => [
                ['id' => $this->hrRole->id, 'can_view' => '1', 'can_manage' => '1'],
            ],
        ]);
        $resp->assertRedirect(route('contract-types.index'))->assertSessionHasNoErrors();

        $type = ContractType::where('name', 'Mietvertrag')->first();
        $this->assertNotNull($type);
        $this->assertSame(1, $type->roles()->count());
        $this->assertTrue((bool) $type->roles->first()->pivot->can_manage);

        $this->assertDatabaseHas('audit_logs', ['event' => 'contract_type.created']);
    }

    public function test_show_returns_403_for_user_without_visibility(): void
    {
        $type = ContractType::create(['name' => 'P', 'slug' => 'p', 'default_notice_period_days' => 90]);
        $c = Contract::create(['name' => 'V', 'contract_type_id' => $type->id, 'notice_period_days' => 30, 'status' => 'active', 'created_by' => $this->admin->id]);

        $this->actingAs($this->hrUser)->get(route('contracts.show', $c))->assertForbidden();
    }
}
