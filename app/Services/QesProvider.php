<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Signature;
use App\Models\User;

/**
 * Schnittstelle für externe QES-Anbieter (qualifizierte elektronische
 * Signatur). Eine Implementation pro Provider (D-Trust, Bundesdruckerei,
 * Swisscom AIS, etc.).
 *
 * Der QesProvider übernimmt das gesamte Signing — inkl. Hin- und Her-Sprung
 * zum Provider-UI für die Signer-Authentifikation, idR. via Redirect-Flow.
 * Für die Erst-Integration kann der Provider auch synchron arbeiten
 * (Server-zu-Server) wenn der Signer per organisations-eigenem Zertifikat
 * arbeitet ("Fernsignatur" via HSM).
 *
 * Diese Codebase enthält nur einen Mock-Provider — echte Implementierungen
 * müssen pro Kunde lizenziert/integriert werden.
 */
interface QesProvider
{
    /**
     * @param array{step?: ?\App\Models\WorkflowStepExecution, ip?: ?string, twofa_verified?: bool, reason?: ?string} $options
     */
    public function sign(Attachment $attachment, User $signer, string $contentHash, string $bytes, array $options): Signature;

    public function providerKey(): string;

    public function displayName(): string;
}
