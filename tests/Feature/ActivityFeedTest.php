<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\ActivityFeed;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_includes_notifications_and_overdue_tasks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        AppNotification::send($user, 'test', 'Neue Aufgabe', 'Bitte ansehen');

        // ueberfaellige Aufgabe
        $wf = Workflow::create(['name' => 'WF', 'trigger_type' => 'manual', 'status' => 'active']);
        $v = WorkflowVersion::create(['workflow_id' => $wf->id, 'version_number' => 1, 'definition' => [], 'created_by' => $user->id]);
        $wf->update(['current_version_id' => $v->id]);
        $i = WorkflowInstance::create(['workflow_id' => $wf->id, 'workflow_version_id' => $v->id, 'status' => 'running', 'started_at' => now()]);
        WorkflowStepExecution::create([
            'workflow_instance_id' => $i->id, 'step_key' => 's1', 'step_type' => 'approval',
            'assigned_to_user_id' => $user->id, 'assigned_at' => now()->subDays(5),
            'due_at' => now()->subDay(),
        ]);

        $feed = app(ActivityFeed::class)->for($user);

        $types = array_unique(array_column($feed, 'type'));
        $this->assertContains('notification', $types);
        $this->assertContains('task_overdue', $types);
    }

    public function test_dashboard_renders_activity_section(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        AppNotification::send($user, 'test', 'Hallo Welt', 'Bitte beachten');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Aktivität');
    }
}
