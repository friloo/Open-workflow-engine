<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $g2fa = new Google2FA();

        $pending = null;
        if (! $user->hasTwoFactorEnabled()) {
            $secret = $request->session()->get('2fa.pending_secret');
            if (! $secret) {
                $secret = $g2fa->generateSecretKey();
                $request->session()->put('2fa.pending_secret', $secret);
            }
            $issuer = config('app.name');
            $otpauth = $g2fa->getQRCodeUrl($issuer, $user->email, $secret);
            $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
            $qrSvg = $writer->writeString($otpauth);
            $pending = ['secret' => $secret, 'qr' => $qrSvg];
        }

        return view('profile.two-factor', [
            'pending' => $pending,
            'recoveryCodes' => $request->session()->pull('2fa.show_recovery_codes'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return back();
        }

        $secret = $request->session()->get('2fa.pending_secret');
        if (! $secret) {
            return back()->withErrors(['code' => 'Setup neu starten.']);
        }

        $data = $request->validate(['code' => ['required', 'string']]);
        $g2fa = new Google2FA();
        if (! $g2fa->verifyKey($secret, preg_replace('/\s+/', '', $data['code']))) {
            throw ValidationException::withMessages(['code' => 'Code passt nicht.']);
        }

        $codes = $this->generateRecoveryCodes();
        $user->setTwoFactorSecret($secret);
        $user->setTwoFactorRecoveryCodes($codes);
        $user->two_factor_confirmed_at = now();
        $user->save();

        $request->session()->forget('2fa.pending_secret');
        $request->session()->put('2fa.show_recovery_codes', $codes);
        $this->audit->log('auth.2fa.enabled', $user, null, null, "2FA aktiviert: {$user->email}", $user->id);

        return back()->with('status', '2FA aktiviert. Bitte die Recovery-Codes sicher verwahren!');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['password' => ['required', 'current_password']]);

        $user->setTwoFactorSecret(null);
        $user->setTwoFactorRecoveryCodes(null);
        $user->two_factor_confirmed_at = null;
        $user->save();
        $request->session()->forget('2fa.pending_secret');

        $this->audit->log('auth.2fa.disabled', $user, null, null, "2FA deaktiviert: {$user->email}", $user->id);
        return back()->with('status', '2FA wurde deaktiviert.');
    }

    public function regenerateCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            return back();
        }
        $codes = $this->generateRecoveryCodes();
        $user->setTwoFactorRecoveryCodes($codes);
        $user->save();
        $request->session()->put('2fa.show_recovery_codes', $codes);
        $this->audit->log('auth.2fa.recovery_regenerated', $user, null, null, "2FA-Recovery-Codes neu erzeugt: {$user->email}", $user->id);
        return back()->with('status', 'Neue Recovery-Codes erzeugt.');
    }

    /** @return array<int,string> */
    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }
        return $codes;
    }
}
