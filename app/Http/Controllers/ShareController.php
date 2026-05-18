<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Models\ShareLinkAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShareController extends Controller
{
    public function show(Request $request, string $token)
    {
        $share = ShareLink::where('token', $token)->first();
        if (! $share || ! $share->isActive()) {
            $this->log($share, $request, 'view', false);
            return view('public.share.unavailable', ['status' => $share?->statusLabel() ?? 'unbekannt']);
        }

        // Passwort-gesperrt? Wenn nicht in der Session entsperrt -> Eingabe.
        if ($share->password_hash && ! $this->isUnlocked($request, $share)) {
            return view('public.share.password', ['share' => $share]);
        }

        $attachment = $share->effectiveAttachment();
        if (! $attachment) return view('public.share.unavailable', ['status' => 'Datei nicht gefunden']);

        $this->log($share, $request, 'view', true);
        return view('public.share.show', ['share' => $share, 'attachment' => $attachment]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $share = ShareLink::where('token', $token)->first();
        if (! $share || ! $share->isActive()) abort(404);

        $data = $request->validate(['password' => ['required', 'string']]);
        if (! $share->checkPassword($data['password'])) {
            $this->log($share, $request, 'password_attempt', false);
            return back()->withErrors(['password' => 'Falsches Passwort.']);
        }
        $this->log($share, $request, 'password_attempt', true);
        $request->session()->put("share_unlocked.{$share->id}", true);
        return redirect()->route('share.show', $share->token);
    }

    public function preview(Request $request, string $token): StreamedResponse
    {
        $share = ShareLink::where('token', $token)->first();
        if (! $share || ! $share->isActive()) abort(404);
        if ($share->password_hash && ! $this->isUnlocked($request, $share)) abort(403);

        $att = $share->effectiveAttachment();
        if (! $att) abort(404);
        $disk = Storage::disk($att->disk);
        if (! $disk->exists($att->path)) abort(404);

        $this->log($share, $request, 'preview', true);

        return $disk->response($att->path, $att->original_name, [
            'Content-Type' => $att->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($att->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, string $token)
    {
        $share = ShareLink::where('token', $token)->first();
        if (! $share || ! $share->isActive()) abort(404);
        if ($share->password_hash && ! $this->isUnlocked($request, $share)) abort(403);

        $att = $share->effectiveAttachment();
        if (! $att) abort(404);

        $share->increment('download_count');
        $this->log($share, $request, 'download', true);

        return Storage::disk($att->disk)->download($att->path, $att->original_name);
    }

    private function isUnlocked(Request $request, ShareLink $share): bool
    {
        return (bool) $request->session()->get("share_unlocked.{$share->id}", false);
    }

    private function log(?ShareLink $share, Request $request, string $action, bool $success): void
    {
        if (! $share) return;
        ShareLinkAccess::create([
            'share_link_id' => $share->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'action' => $action,
            'success' => $success,
            'accessed_at' => now(),
        ]);
    }
}
