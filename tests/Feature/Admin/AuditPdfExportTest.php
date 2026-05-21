<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_verification_certificate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Etwas in den Audit-Log werfen
        app(AuditLogger::class)->log('test.event', $admin, null, ['x' => 1], 'Testeintrag', $admin->id);

        $resp = $this->actingAs($admin)->get(route('admin.audit.verify_pdf'));
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('Content-Type'));
        $body = $resp->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertGreaterThan(2000, strlen($body));
    }

    public function test_admin_can_download_audit_export(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        app(AuditLogger::class)->log('test.event', $admin, null, null, 'Eintrag', $admin->id);

        $resp = $this->actingAs($admin)->get(route('admin.audit.export_pdf'));
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('Content-Type'));
        $body = $resp->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
    }

    public function test_employee_cannot_export(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('employee');
        $this->actingAs($u)->get(route('admin.audit.export_pdf'))->assertForbidden();
    }
}
