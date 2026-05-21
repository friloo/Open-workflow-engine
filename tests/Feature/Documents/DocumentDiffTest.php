<?php

namespace Tests\Feature\Documents;

use App\Models\Attachment;
use App\Models\User;
use App\Services\DocumentDiffer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentDiffTest extends TestCase
{
    use RefreshDatabase;

    private function mkAttachment(string $chain, int $version, string $body, ?User $owner = null): Attachment
    {
        $owner ??= User::factory()->create();
        $path = 'docs/'.Str::uuid().'.txt';
        Storage::disk('local')->put($path, $body);

        return Attachment::create([
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => $owner->id,
            'original_name' => 'doku.txt',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => strlen($body),
            'content_hash' => hash('sha256', $body),
            'version_chain_id' => $chain,
            'version_number' => $version,
            'is_current_version' => $version === 2,
        ]);
    }

    public function test_text_diff_extracts_added_and_removed_lines(): void
    {
        Storage::fake('local');
        $chain = (string) Str::uuid();
        $owner = User::factory()->create();
        $v1 = $this->mkAttachment($chain, 1, "Zeile A\nZeile B\nZeile C\n", $owner);
        $v2 = $this->mkAttachment($chain, 2, "Zeile A\nZeile B geändert\nZeile C\nZeile D\n", $owner);

        $result = app(DocumentDiffer::class)->diff($v1, $v2);

        $this->assertTrue($result['supported']);
        $this->assertGreaterThan(0, $result['stats']['added']);
        $this->assertGreaterThan(0, $result['stats']['removed']);
    }

    public function test_diff_route_renders_with_versions_picker(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $chain = (string) Str::uuid();
        $v1 = $this->mkAttachment($chain, 1, "Hello\n", $admin);
        $v2 = $this->mkAttachment($chain, 2, "Hello World\n", $admin);

        $resp = $this->actingAs($admin)->get(route('documents.diff', ['attachment' => $v1, 'other' => $v2]));
        $resp->assertOk();
        $resp->assertSee('Versions-Vergleich');

        $this->assertDatabaseHas('audit_logs', ['event' => 'document.diff_viewed']);
    }

    public function test_diff_rejects_documents_from_different_chains(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $a = $this->mkAttachment('chain-a', 1, "abc", $admin);
        $b = $this->mkAttachment('chain-b', 1, "def", $admin);

        $this->actingAs($admin)->get(route('documents.diff', ['attachment' => $a, 'other' => $b]))
            ->assertNotFound();
    }
}
