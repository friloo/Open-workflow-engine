<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Signature;
use App\Models\User;

/**
 * Stub-QES-Provider für Tests und lokale Demos. Tut so, als würde er bei
 * einem externen Anbieter signieren — speichert aber nur Metadaten ohne
 * echte qualifizierte Signatur. In Produktion durch echten Provider
 * ersetzen (D-Trust, Bundesdruckerei, Swisscom AIS, Adobe Sign, ...).
 *
 * Aktivierung: in Settings 'signatures.qes_provider' = 'mock'.
 */
class QesMockProvider implements QesProvider
{
    public function sign(Attachment $attachment, User $signer, string $contentHash, string $bytes, array $options): Signature
    {
        $ip = $options['ip'] ?? request()?->ip();
        $twofa = (bool) ($options['twofa_verified'] ?? $signer->hasTwoFactorEnabled());

        return Signature::create([
            'attachment_id' => $attachment->id,
            'user_id' => $signer->id,
            'workflow_step_execution_id' => ($options['step'] ?? null)?->id,
            'level' => Signature::LEVEL_QES,
            'provider' => $this->providerKey(),
            'content_hash' => $contentHash,
            'signer_name' => $signer->name,
            'signer_email' => $signer->email,
            'signer_ip' => $ip,
            'certificate_pem' => null,
            'signature_blob' => null,
            'twofa_verified' => $twofa,
            'signed_at' => now(),
            'metadata' => [
                'reason' => $options['reason'] ?? null,
                'note' => 'MOCK-QES: keine rechtsgültige qualifizierte Signatur. Für Tests/Demos. In Produktion durch echten Provider ersetzen.',
                'provider_reference' => 'mock-tx-' . bin2hex(random_bytes(8)),
            ],
        ]);
    }

    public function providerKey(): string
    {
        return 'mock';
    }

    public function displayName(): string
    {
        return 'Mock-QES (nur für Tests)';
    }
}
