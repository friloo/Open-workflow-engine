<?php

namespace Tests\Feature\Signatures;

use App\Models\Attachment;
use App\Models\Signature;
use App\Models\User;
use App\Services\SignatureService;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttachment(User $owner, string $docType = 'Rechnung'): Attachment
    {
        $path = 'docs/'.Str::uuid().'.txt';
        $body = 'Test-Inhalt für Signatur '.now()->toIso8601String();
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
            'document_type' => $docType,
            'version_chain_id' => (string) Str::uuid(),
            'version_number' => 1,
            'is_current_version' => true,
        ]);
    }

    public function test_resolve_level_uses_document_type_default(): void
    {
        Settings::set('attachments.signature_levels', ['Rechnung' => 'aes'], null);
        $svc = app(SignatureService::class);
        $this->assertSame('aes', $svc->resolveLevel('Rechnung', null));
    }

    public function test_resolve_level_override_only_upgrades(): void
    {
        Settings::set('attachments.signature_levels', ['Rechnung' => 'aes'], null);
        $svc = app(SignatureService::class);
        // Override AES -> SES = Downgrade, bleibt AES
        $this->assertSame('aes', $svc->resolveLevel('Rechnung', 'ses'));
        // Override AES -> QES = Upgrade
        $this->assertSame('qes', $svc->resolveLevel('Rechnung', 'qes'));
    }

    public function test_ses_signature_records_hash_and_signer(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $user = User::factory()->create();
        $att = $this->makeAttachment($user);

        $svc = app(SignatureService::class);
        $sig = $svc->sign($att, $user, Signature::LEVEL_SES);

        $this->assertSame('ses', $sig->level);
        $this->assertSame($att->content_hash, $sig->content_hash);
        $this->assertSame($user->id, $sig->user_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'document.signed']);
    }

    public function test_aes_fails_when_no_certificate_configured(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $user = User::factory()->create();
        $att = $this->makeAttachment($user);

        $svc = app(SignatureService::class);
        $this->expectException(\RuntimeException::class);
        $svc->sign($att, $user, Signature::LEVEL_AES);
    }

    public function test_aes_creates_pkcs7_when_cert_configured(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        // Self-signed Zertifikat für den Test generieren
        $keyRes = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['CN' => 'OWE Test'], $keyRes);
        $x509 = openssl_csr_sign($csr, null, $keyRes, 365);
        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($keyRes, $keyPem);

        Settings::set('signatures.aes_cert_pem', $certPem, null);
        Settings::set('signatures.aes_key_pem', $keyPem, null);

        $user = User::factory()->create();
        $att = $this->makeAttachment($user);

        $svc = app(SignatureService::class);
        $sig = $svc->sign($att, $user, Signature::LEVEL_AES);

        $this->assertSame('aes', $sig->level);
        $this->assertNotEmpty($sig->signature_blob);
        $this->assertNotEmpty($sig->certificate_pem);

        // verifizieren
        $v = $svc->verify($sig);
        $this->assertTrue($v['ok'], $v['reason'] ?? '');
    }

    public function test_verify_fails_when_document_changed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $user = User::factory()->create();
        $att = $this->makeAttachment($user);

        $sig = app(SignatureService::class)->sign($att, $user, Signature::LEVEL_SES);

        // Datei manipulieren
        Storage::disk('local')->put($att->path, 'manipuliert');

        $v = app(SignatureService::class)->verify($sig);
        $this->assertFalse($v['ok']);
        $this->assertStringContainsString('Hash', $v['reason']);
    }

    public function test_qes_mock_provider_signs_when_enabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Settings::set('signatures.qes_provider', 'mock', null);

        $user = User::factory()->create();
        $att = $this->makeAttachment($user);

        $sig = app(SignatureService::class)->sign($att, $user, Signature::LEVEL_QES);
        $this->assertSame('qes', $sig->level);
        $this->assertSame('mock', $sig->provider);
    }

    public function test_signature_view_renders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $att = $this->makeAttachment($admin);

        $this->actingAs($admin)->get(route('documents.signatures.show', $att))
            ->assertOk()->assertSee('Signaturen');
    }

    public function test_signature_post_creates_record(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Settings::set('attachments.signature_levels', ['Rechnung' => 'ses'], null);

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $att = $this->makeAttachment($admin);

        $this->actingAs($admin)->post(route('documents.signatures.store', $att), [
            'reason' => 'Freigabe Rechnung 4711',
        ])->assertRedirect();

        $this->assertDatabaseHas('signatures', [
            'attachment_id' => $att->id,
            'user_id' => $admin->id,
            'level' => 'ses',
        ]);
    }
}
