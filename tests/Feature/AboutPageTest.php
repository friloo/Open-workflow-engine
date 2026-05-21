<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_user_can_open_about_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $resp = $this->actingAs($user)->get(route('help.show', 'about'))->assertOk();
        $resp->assertSee('Friederich Loheide');
        $resp->assertSee('Haftungsausschluss');
        $resp->assertSee('KI-Code-Generierung');
    }

    public function test_app_footer_shows_author(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        $resp = $this->actingAs($user)->get(route('tasks.index'))->assertOk();
        $resp->assertSee('Friederich Loheide');
        $resp->assertSee('loheide.eu');
    }

    public function test_guest_login_page_shows_author(): void
    {
        $resp = $this->get('/login')->assertOk();
        $resp->assertSee('Friederich Loheide');
        $resp->assertSee('KI generiert');
    }
}
