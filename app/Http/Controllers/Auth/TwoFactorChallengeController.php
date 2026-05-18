<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('auth.2fa.user_id');
        if (! $userId) return redirect()->route('login');
        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.2fa.user_id');
        if (! $userId) return redirect()->route('login');

        $user = User::find($userId);
        if (! $user || ! $user->hasTwoFactorEnabled()) return redirect()->route('login');

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $ok = false;
        if (! empty($data['code'])) {
            $g2fa = new Google2FA();
            $ok = $g2fa->verifyKey((string) $user->getTwoFactorSecret(), preg_replace('/\s+/', '', $data['code']));
            if ($ok) {
                $this->audit->log('auth.2fa.verified', $user, null, null, "2FA-Code OK: {$user->email}", $user->id);
            }
        } elseif (! empty($data['recovery_code'])) {
            $codes = $user->getTwoFactorRecoveryCodes() ?? [];
            $clean = strtoupper(trim($data['recovery_code']));
            foreach ($codes as $i => $c) {
                if (hash_equals(strtoupper((string) $c), $clean)) {
                    unset($codes[$i]);
                    $user->setTwoFactorRecoveryCodes(array_values($codes));
                    $user->save();
                    $ok = true;
                    $this->audit->log('auth.2fa.recovery_used', $user, null, ['remaining' => count($codes)],
                        "Recovery-Code verbraucht: {$user->email}", $user->id);
                    break;
                }
            }
        }

        if (! $ok) {
            $this->audit->log('auth.2fa.failed', $user, null, null, "2FA-Code falsch: {$user->email}", $user->id);
            throw ValidationException::withMessages(['code' => 'Code nicht gueltig.']);
        }

        $remember = (bool) $request->session()->pull('auth.2fa.remember', false);
        $intended = $request->session()->pull('auth.2fa.intended');
        $request->session()->forget('auth.2fa.user_id');

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->log('auth.login', $user, null, null, "Login: {$user->email} (2FA)", $user->id);

        return $intended ? redirect($intended) : redirect()->route('dashboard');
    }
}
