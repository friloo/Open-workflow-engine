<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\User;
use App\Services\ContractTemplateRenderer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderer_fills_placeholders(): void
    {
        $c = new Contract([
            'name' => 'Mietvertrag', 'party' => 'Mustermann GmbH', 'notice_period_days' => 90,
            'start_date' => '2026-01-01', 'end_date' => '2027-12-31',
        ]);
        $c->setRelation('owner', new User(['name' => 'A. Admin', 'email' => 'a@example.com']));
        $t = new ContractTemplate([
            'body_html' => 'Vertrag mit {{ party }} ueber {{ name }}. Frist: {{ notice_period_days }} Tage. Sachbearbeiter: {{ owner.name }}',
        ]);

        $html = (new ContractTemplateRenderer())->render($t, $c);
        $this->assertStringContainsString('Mustermann GmbH', $html);
        $this->assertStringContainsString('Mietvertrag', $html);
        $this->assertStringContainsString('90 Tage', $html);
        $this->assertStringContainsString('A. Admin', $html);
    }

    public function test_admin_can_create_template_and_generate_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create(); $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('contract-templates.store'), [
            'name' => 'Standard-Wartung',
            'body_html' => '<h1>{{ name }}</h1><p>{{ party }}</p>',
        ])->assertRedirect(route('contract-templates.index'));

        $template = ContractTemplate::first();
        $contract = Contract::create([
            'name' => 'Wartung Heizung', 'party' => 'Brunner GmbH',
            'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('contracts.template.generate', $contract), [
            'template_id' => $template->id,
        ])->assertRedirect(route('contracts.show', $contract));

        $this->assertSame(1, $contract->fresh()->attachments()->count());
        $att = $contract->fresh()->attachments->first();
        $this->assertSame('application/pdf', $att->mime_type);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contract.pdf_generated']);
    }

    public function test_non_manage_user_cannot_generate(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $u = User::factory()->create(); $u->assignRole('employee');
        // even with manage permission... but employee doesn't have contracts.manage in default
        $contract = Contract::create(['name' => 'X', 'notice_period_days' => 90, 'status' => 'active']);

        $this->actingAs($u)->post(route('contracts.template.generate', $contract), [
            'template_id' => 1,
        ])->assertForbidden();
    }
}
