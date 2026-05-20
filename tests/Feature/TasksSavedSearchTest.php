<?php

namespace Tests\Feature;

use App\Models\SavedSearch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksSavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_a_task_view(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $resp = $this->actingAs($user)->post(route('saved_searches.store'), [
            'name' => 'Meine Eskalationen',
            'scope' => 'tasks',
            'params' => ['filter' => 'overdue', 'q' => 'Rechnung'],
        ]);
        $resp->assertRedirect()->assertSessionHasNoErrors();

        $saved = SavedSearch::where('user_id', $user->id)->where('scope', 'tasks')->first();
        $this->assertNotNull($saved);
        $this->assertSame('Meine Eskalationen', $saved->name);
        $this->assertSame(['filter' => 'overdue', 'q' => 'Rechnung'], $saved->params);
    }

    public function test_tasks_list_renders_saved_views_chip(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');
        SavedSearch::create([
            'user_id' => $user->id,
            'scope' => 'tasks',
            'name' => 'Diese Woche dringend',
            'params' => ['filter' => 'week'],
            'sort_order' => 0,
        ]);

        $this->actingAs($user)->get(route('tasks.index'))->assertOk()
            ->assertSee('Diese Woche dringend');
    }

    public function test_task_saved_search_drops_unknown_params(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->actingAs($user)->post(route('saved_searches.store'), [
            'name' => 'Test',
            'scope' => 'tasks',
            'params' => ['filter' => 'all', 'evil' => 'hack', 'fields' => ['x' => 'y']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $saved = SavedSearch::where('user_id', $user->id)->first();
        $this->assertSame(['filter' => 'all'], $saved->params);
    }

    public function test_user_cannot_delete_someone_elses_saved_view(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u1 = User::factory()->create();
        $u1->assignRole('employee');
        $u2 = User::factory()->create();
        $u2->assignRole('employee');

        $foreign = SavedSearch::create([
            'user_id' => $u1->id, 'scope' => 'tasks', 'name' => 'X',
            'params' => ['filter' => 'all'], 'sort_order' => 0,
        ]);

        $this->actingAs($u2)->delete(route('saved_searches.destroy', $foreign))->assertForbidden();
        $this->assertDatabaseHas('saved_searches', ['id' => $foreign->id]);
    }
}
