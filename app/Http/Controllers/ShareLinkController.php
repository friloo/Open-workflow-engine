<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ShareLink;
use App\Models\ShareLinkAccess;
use App\Services\AuditLogger;
use App\Support\DocumentTypes;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShareLinkController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->hasPermission('shares.manage_all');

        $query = ShareLink::with(['attachment', 'creator'])
            ->withCount('accesses')
            ->when(! $isAdmin, fn ($q) => $q->where('created_by', $user->id))
            ->orderByDesc('id');

        return view('shares.index', [
            'shares' => $query->paginate(25)->withQueryString(),
            'isAdmin' => $isAdmin,
            'maxDays' => (int) Settings::get('shares.max_expiry_days', 90),
            'reviewDays' => (int) Settings::get('shares.review_interval_days', 7),
        ]);
    }

    public function store(Request $request, Attachment $attachment): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('shares.create')) abort(403);
        if (! DocumentTypes::canViewType($user, $attachment->document_type)) abort(403);

        $data = $request->validate([
            'expires_in_days' => ['nullable', 'integer', 'between:1,3650'],
            'password' => ['nullable', 'string', 'min:4', 'max:128'],
            'max_downloads' => ['nullable', 'integer', 'between:1,10000'],
            'follow_versions' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $expires = isset($data['expires_in_days']) && $data['expires_in_days']
            ? now()->addDays((int) $data['expires_in_days'])
            : ShareLink::defaultExpiry();
        // Cap auf den Admin-Maximum
        $cap = ShareLink::maxAllowedExpiry();
        if ($expires->gt($cap)) $expires = $cap;

        $share = new ShareLink([
            'attachment_id' => $attachment->id,
            'created_by' => $user->id,
            'follow_versions' => $request->boolean('follow_versions', true),
            'expires_at' => $expires,
            'max_downloads' => $data['max_downloads'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
        $share->setPassword($data['password'] ?? null);
        $share->save();

        $this->audit->log('share.created', $share, null, [
            'attachment_id' => $attachment->id,
            'name' => $attachment->original_name,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'has_password' => (bool) $share->password_hash,
            'max_downloads' => $share->max_downloads,
        ], "Freigabe-Link erstellt: {$attachment->original_name}");

        return back()->with('status', 'Freigabe-Link erstellt.')
            ->with('shareCreated', [
                'url' => route('share.show', $share->token),
                'token' => $share->token,
                'expires' => $share->expires_at?->format('d.m.Y H:i'),
            ]);
    }

    public function revoke(Request $request, ShareLink $share): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('shares.manage_all') && $share->created_by !== $user->id) abort(403);

        $share->revoke($request->input('reason'));
        $this->audit->log('share.revoked', $share, null, ['reason' => $request->input('reason')],
            "Freigabe widerrufen: ".($share->attachment?->original_name ?: '#'.$share->id), $user->id);

        return back()->with('status', 'Freigabe widerrufen.');
    }

    /** Mail-Signed-Link „Freigabe behalten" — fragt nach Grund. */
    public function reviewConfirmForm(Request $request, ShareLink $share): View
    {
        abort_unless($request->hasValidSignature(), 403, 'Link ungueltig oder abgelaufen.');
        return view('shares.review-confirm', ['share' => $share]);
    }

    public function reviewConfirm(Request $request, ShareLink $share): View
    {
        abort_unless($request->hasValidSignature(false) || $request->hasValidSignature(), 403, 'Link ungueltig.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $share->forceFill([
            'last_review_response_at' => now(),
            'review_response' => $data['reason'],
        ])->save();
        $this->audit->log('share.review.confirmed', $share, null, ['reason' => $data['reason']],
            'Freigabe-Pruefung bestaetigt: '.\Illuminate\Support\Str::limit($data['reason'], 80));
        return view('shares.review-done', ['share' => $share, 'mode' => 'confirmed']);
    }

    public function reviewRevoke(Request $request, ShareLink $share): View
    {
        abort_unless($request->hasValidSignature(), 403, 'Link ungueltig oder abgelaufen.');
        $share->revoke('Auf Pruefungs-Anfrage durch Inhaber widerrufen.');
        $this->audit->log('share.review.revoked', $share, null, null,
            'Freigabe durch Pruefungs-Mail widerrufen');
        return view('shares.review-done', ['share' => $share, 'mode' => 'revoked']);
    }
}
