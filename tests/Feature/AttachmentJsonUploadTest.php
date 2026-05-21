<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentJsonUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_returns_json_when_xhr(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $contract = Contract::create([
            'name' => 'Test-Vertrag',
            'party' => 'ACME',
            'status' => 'active',
            'owner_user_id' => $admin->id,
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $resp = $this->actingAs($admin)
            ->postJson(route('attachments.store', ['type' => 'contract', 'id' => $contract->id]), [
                'file' => $file,
            ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);
        $resp->assertJsonStructure(['ok', 'id', 'name', 'size', 'mime', 'url']);
    }

    public function test_upload_returns_json_error_on_validation_failure(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $contract = Contract::create([
            'name' => 'V', 'party' => 'X', 'status' => 'active', 'owner_user_id' => $admin->id,
        ]);

        $resp = $this->actingAs($admin)
            ->postJson(route('attachments.store', ['type' => 'contract', 'id' => $contract->id]), []);

        // Laravel validation returns 422 for JSON requests
        $resp->assertStatus(422);
    }
}
