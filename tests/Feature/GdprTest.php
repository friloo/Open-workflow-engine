<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GdprService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GdprTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_for_email_produces_zip_with_summary(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Ziel-User mit ein paar Daten
        $target = User::factory()->create(['email' => 'subject@example.com']);

        $service = app(GdprService::class);
        $result = $service->exportForEmail('subject@example.com');

        $this->assertFileExists($result['path']);
        $this->assertStringContainsString('DSGVO-Auskunft', $result['filename']);
        $this->assertStringContainsString('subject_example_com', $result['filename']);
        @unlink($result['path']);

        // Audit-Log-Eintrag
        $this->assertDatabaseHas('audit_logs', ['event' => 'gdpr.access_request']);
    }

    public function test_export_for_unknown_email_runs_without_error(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $result = app(GdprService::class)->exportForEmail('niemand@example.com');
        $this->assertFileExists($result['path']);
        @unlink($result['path']);
    }

    public function test_anonymize_replaces_user_data_and_logs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create([
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
        ]);
        $user->assignRole('employee');
        $userId = $user->id;

        app(GdprService::class)->anonymize($user, 'Loeschungs-Antrag vom 19.05.');

        $user->refresh();
        $this->assertSame("anonymized-{$userId}@deleted.local", $user->email);
        $this->assertStringContainsString('Anonymisiert', $user->name);
        $this->assertFalse((bool) $user->is_active);
        $this->assertSame(0, $user->roles()->count());

        $this->assertDatabaseHas('audit_logs', ['event' => 'gdpr.anonymization']);
    }

    public function test_admin_can_request_export_via_ui(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->post(route('admin.gdpr.export'), [
            'email' => 'someone@example.com',
        ]);
        $resp->assertOk();
        $this->assertStringContainsString('application/zip', $resp->headers->get('Content-Type'));
    }
}
