<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\DocumentCase;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractCaseAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_attach_case_from_contract(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $c = Contract::create([
            'name' => 'Wartung Heizung', 'notice_period_days' => 90,
            'status' => 'active', 'created_by' => $admin->id,
        ]);
        $a = DocumentCase::create(['name' => 'Objekt Schillerstr. 12', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('contracts.cases.attach', $c), [
            'document_case_id' => $a->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($c->fresh()->cases->contains($a->id));
        $this->assertDatabaseHas('audit_logs', ['event' => 'contract.case_attached']);
    }

    public function test_admin_can_detach_case_from_contract(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $c = Contract::create(['name' => 'V', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id]);
        $a = DocumentCase::create(['name' => 'A', 'created_by' => $admin->id]);
        $c->cases()->attach($a->id);

        $this->actingAs($admin)->delete(route('contracts.cases.detach', [$c, $a->id]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($c->fresh()->cases->contains($a->id));
    }

    public function test_show_lists_attached_cases(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $c = Contract::create(['name' => 'V', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id]);
        $a = DocumentCase::create(['name' => 'Personalakte Mueller', 'created_by' => $admin->id]);
        $c->cases()->attach($a->id);

        $this->actingAs($admin)->get(route('contracts.show', $c))->assertOk()
            ->assertSee('Personalakte Mueller');
    }
}
