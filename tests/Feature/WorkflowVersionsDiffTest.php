<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Services\WorkflowDiffer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowVersionsDiffTest extends TestCase
{
    use RefreshDatabase;

    public function test_differ_detects_added_removed_modified(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $w = Workflow::create(['name' => 'X', 'slug' => 'x', 'status' => 'active', 'created_by' => $admin->id]);

        $defA = ['drawflow' => ['Home' => ['data' => [
            'n1' => ['id' => 'n1', 'class' => 'start', 'data' => ['label' => 'Start']],
            'n2' => ['id' => 'n2', 'class' => 'approval', 'data' => ['label' => 'Pruefung', 'grace_value' => 3]],
            'n3' => ['id' => 'n3', 'class' => 'end', 'data' => ['label' => 'Ende']],
        ]]]];
        $defB = ['drawflow' => ['Home' => ['data' => [
            'n1' => ['id' => 'n1', 'class' => 'start', 'data' => ['label' => 'Start']],
            'n2' => ['id' => 'n2', 'class' => 'approval', 'data' => ['label' => 'Pruefung', 'grace_value' => 7]], // modified
            'n4' => ['id' => 'n4', 'class' => 'notify', 'data' => ['label' => 'Hinweis']], // added
            // n3 removed
        ]]]];

        $va = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1, 'definition' => $defA]);
        $vb = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 2, 'definition' => $defB]);

        $diff = (new WorkflowDiffer())->diff($va, $vb);
        $this->assertSame(1, $diff['counts']['added']);
        $this->assertSame(1, $diff['counts']['removed']);
        $this->assertSame(1, $diff['counts']['modified']);
        $this->assertSame(1, $diff['counts']['unchanged']);

        // n2 modified — sollte grace_value-Aenderung enthalten
        $n2 = collect($diff['nodes'])->firstWhere('step_key', 'n2');
        $this->assertSame('modified', $n2['status']);
        $hasGrace = collect($n2['fields'])->contains(fn ($f) => $f['key'] === 'grace_value');
        $this->assertTrue($hasGrace);
    }

    public function test_diff_view_renders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $w = Workflow::create(['name' => 'X', 'slug' => 'x', 'status' => 'active', 'created_by' => $admin->id]);

        $v1 = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1, 'definition' => ['drawflow' => ['Home' => ['data' => []]]]]);
        $v2 = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 2, 'definition' => ['drawflow' => ['Home' => ['data' => [
            'n1' => ['id' => 'n1', 'class' => 'start', 'data' => ['label' => 'Start']],
        ]]]]]);

        $this->actingAs($admin)->get(route('workflows.versions.diff', $w).'?a='.$v1->id.'&b='.$v2->id)
            ->assertOk()
            ->assertSee('Zusammenfassung')
            ->assertSee('Start');
    }
}
