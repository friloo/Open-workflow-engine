<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_topbar_icon_und_modal_erscheinen_wenn_support_an(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        Settings::set('support.enabled', true);
        Settings::set('support.mode', 'mail');
        Settings::set('support.email', 'help@example.com');

        $resp = $this->actingAs($user)->get('/dashboard');
        $body = $resp->getContent();

        $this->assertStringContainsString('supportOpen = true', $body, 'Topbar-Icon-Trigger fehlt');
        $this->assertStringContainsString('support_subject', $body, 'Modal-Form fehlt');
        $this->assertStringNotContainsString('href="'.route('support.show').'"', $body,
            'Sidebar darf keinen Link zu support.show mehr enthalten');
    }

    public function test_topbar_icon_fehlt_wenn_support_aus(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        // Default: nichts gesetzt → support.enabled ist false
        $resp = $this->actingAs($user)->get('/dashboard');
        $body = $resp->getContent();

        $this->assertStringNotContainsString('supportOpen = true', $body,
            'Topbar-Icon darf nicht erscheinen wenn support.enabled aus ist');
    }

    public function test_modal_submit_per_json_funktioniert(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');

        Settings::set('support.enabled', true);
        Settings::set('support.mode', 'mail');
        Settings::set('support.email', 'help@example.com');

        \Illuminate\Support\Facades\Mail::fake();

        $resp = $this->actingAs($user)->postJson(route('support.send'), [
            'subject' => 'Test',
            'description' => 'Druckt nicht.'."\n\n— Aufgerufen von: https://owe.example.com/dashboard",
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure(['status']);
        // Audit-Log dokumentiert den Versand — den koennen wir verlaesslich prüfen.
        $this->assertDatabaseHas('audit_logs', ['event' => 'support.ticket']);
    }
}
