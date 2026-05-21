<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->audit->log('auth.login.blocked', $user, null, null, "Login für inaktiven Account {$user->email}", $user->id);

            throw ValidationException::withMessages([
                'email' => 'Dieses Konto ist deaktiviert.',
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            // Erstes Faktor OK; logge wieder aus und merke die User-ID, bis
            // der TOTP-Code bestätigt wurde.
            Auth::guard('web')->logout();
            $request->session()->put('auth.2fa.user_id', $user->id);
            $request->session()->put('auth.2fa.remember', $request->boolean('remember'));
            $request->session()->put('auth.2fa.intended', redirect()->intended(route('dashboard'))->getTargetUrl());
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log('auth.login', $user, null, null, "Login: {$user->email}", $user->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->audit->log('auth.logout', $user, null, null, "Logout: {$user->email}", $user->id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
