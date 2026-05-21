<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_open_report(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('employee');
        $this->actingAs($u)->get(route('admin.reports.permissions'))->assertForbidden();
    }

    public function test_admin_can_open_html_report(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['name' => 'A. Admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.reports.permissions'))->assertOk()
            ->assertSee('Berechtigungs-Report')
            ->assertSee('A. Admin');
    }

    public function test_admin_can_export_csv(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get(route('admin.reports.permissions.csv'));
        $resp->assertOk();
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));
        $body = $resp->streamedContent();
        $this->assertStringContainsString('User;E-Mail;', $body);
        $this->assertStringContainsString($admin->name, $body);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report.permissions.exported']);
    }

    public function test_admin_can_export_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $resp = $this->actingAs($admin)->get(route('admin.reports.permissions.pdf'));
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('Content-Type'));
        $body = $resp->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertGreaterThan(2000, strlen($body));
    }
}
