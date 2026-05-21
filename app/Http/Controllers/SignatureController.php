<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Signature;
use App\Services\SignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignatureController extends Controller
{
    public function __construct(private readonly SignatureService $sig) {}

    /**
     * Sign-Endpoint: User signiert ein konkretes Dokument mit dem Level,
     * das aus Dokument-Typ + (optional) Workflow-Override resolved wird.
     */
    public function store(Request $request, Attachment $attachment): RedirectResponse
    {
        if (! $attachment->visibleTo($request->user())) abort(403);

        $data = $request->validate([
            'level' => ['nullable', 'in:ses,aes,qes'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $level = $this->sig->resolveLevel($attachment->document_type, $data['level'] ?? null);
        if ($level === 'none') {
            return back()->withErrors(['level' => 'Kein Signatur-Level für diesen Dokumenttyp konfiguriert.']);
        }

        try {
            $sig = $this->sig->sign($attachment, $request->user(), $level, [
                'reason' => $data['reason'] ?? null,
                'ip' => $request->ip(),
                'twofa_verified' => $request->user()->hasTwoFactorEnabled(),
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['level' => $e->getMessage()]);
        }

        return back()->with('status', 'Dokument signiert ('.strtoupper($sig->level).').');
    }

    /**
     * Zeigt die Signatur-Historie + Verifikation für ein Dokument.
     */
    public function show(Attachment $attachment, Request $request): View
    {
        if (! $attachment->visibleTo($request->user())) abort(403);

        $signatures = $this->sig->signaturesFor($attachment);
        $verifications = $signatures->mapWithKeys(fn ($s) => [$s->id => $this->sig->verify($s)]);

        return view('signatures.show', [
            'attachment' => $attachment,
            'signatures' => $signatures,
            'verifications' => $verifications,
            'levelForType' => $this->sig->levelForDocumentType($attachment->document_type),
            'levels' => Signature::LEVELS,
        ]);
    }
}
