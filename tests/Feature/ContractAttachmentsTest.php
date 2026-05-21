<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_pdf_to_contract(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $c = Contract::create([
            'name' => 'Wartung', 'notice_period_days' => 90, 'status' => 'active', 'created_by' => $admin->id,
        ]);

        $pdf = UploadedFile::fake()->createWithContent('vertrag.pdf', "%PDF-1.4\nhello world content here long enough\n%%EOF");

        $resp = $this->actingAs($admin)->post(route('attachments.store', ['type' => 'contract', 'id' => $c->id]), [
            'file' => $pdf,
            'label' => 'Hauptvertrag',
        ]);
        $resp->assertRedirect();

        $c->refresh();
        $this->assertSame(1, $c->attachments()->count());
        $this->assertSame('Hauptvertrag', $c->attachments->first()->label);
        $this->assertDatabaseHas('audit_logs', ['event' => 'attachment.uploaded']);
    }

    public function test_employee_without_visibility_cannot_upload(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $type = ContractType::create(['name' => 'Personal', 'slug' => 'p', 'default_notice_period_days' => 90]);
        $c = Contract::create([
            'name' => 'V', 'contract_type_id' => $type->id, 'notice_period_days' => 90,
            'status' => 'active', 'created_by' => $admin->id,
        ]);

        $pdf = UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nx\n%%EOF");
        $this->actingAs($employee)->post(route('attachments.store', ['type' => 'contract', 'id' => $c->id]), [
            'file' => $pdf,
        ])->assertForbidden();
    }

    public function test_user_without_contract_visibility_cannot_download_its_attachment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = ContractType::create(['name' => 'P', 'slug' => 'p', 'default_notice_period_days' => 90]);
        $c = Contract::create([
            'name' => 'V', 'contract_type_id' => $type->id, 'notice_period_days' => 90,
            'status' => 'active', 'created_by' => $admin->id,
        ]);

        $pdf = UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\nx-unique-content\n%%EOF");
        $this->actingAs($admin)->post(route('attachments.store', ['type' => 'contract', 'id' => $c->id]), [
            'file' => $pdf,
        ])->assertRedirect();

        $att = $c->attachments()->first();
        $this->assertNotNull($att);

        // Anderer User ohne Sichtbarkeit -> Download verboten
        $outsider = User::factory()->create();
        $outsider->assignRole('employee'); // employee hat contracts.view-Recht nicht in unserem Setup
        $this->actingAs($outsider)->get(route('attachments.download', $att))->assertForbidden();
    }
}
