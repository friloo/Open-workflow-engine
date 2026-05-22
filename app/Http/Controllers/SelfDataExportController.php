<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\GdprService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * DSGVO-Auskunft Self-Service. User kann seine eigenen Daten als ZIP
 * runterladen, ohne dass ein Admin involviert sein muss. Reuse der
 * bestehenden GdprService::exportForEmail-Funktion mit eigener Email.
 */
class SelfDataExportController extends Controller
{
    public function __construct(
        private readonly GdprService $gdpr,
        private readonly AuditLogger $audit,
    ) {}

    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $result = $this->gdpr->exportForEmail($user->email);

        $this->audit->log('gdpr.self_export', $user, null, [
            'summary' => $result['summary'] ?? [],
        ], "DSGVO-Selbst-Auskunft fuer {$user->email}", $user->id);

        return response()->download($result['path'], $result['filename'])
            ->deleteFileAfterSend();
    }
}
