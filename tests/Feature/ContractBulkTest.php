<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\DocumentCase;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_set_owner(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $target = User::factory()->create();
        $a = Contract::create(['name' => 'A', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id]);
        $b = Contract::create(['name' => 'B', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('contracts.bulk'), [
            'contract_ids' => [$a->id, $b->id],
            'action' => 'set_owner',
            'owner_user_id' => $target->id,
        ])->assertRedirect();

        $this->assertSame($target->id, $a->fresh()->owner_user_id);
        $this->assertSame($target->id, $b->fresh()->owner_user_id);
    }

    public function test_admin_can_bulk_attach_to_case(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $case = DocumentCase::create(['name' => 'Akte X', 'created_by' => $admin->id]);
        $a = Contract::create(['name' => 'A', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id]);
        $b = Contract::create(['name' => 'B', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('contracts.bulk'), [
            'contract_ids' => [$a->id, $b->id],
            'action' => 'attach_case',
            'document_case_id' => $case->id,
        ])->assertRedirect();

        $this->assertTrue($a->fresh()->cases->contains($case->id));
        $this->assertTrue($b->fresh()->cases->contains($case->id));
    }

    public function test_bulk_delete_works(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $a = Contract::create(['name' => 'A', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('contracts.bulk'), [
            'contract_ids' => [$a->id],
            'action' => 'delete',
        ])->assertRedirect();

        $this->assertSoftDeleted('contracts', ['id' => $a->id]);
    }

    public function test_employee_without_manage_cannot_bulk(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create(); $u->assignRole('employee');
        $this->actingAs($u)->post(route('contracts.bulk'), [
            'contract_ids' => [1], 'action' => 'delete',
        ])->assertForbidden();
    }
}
