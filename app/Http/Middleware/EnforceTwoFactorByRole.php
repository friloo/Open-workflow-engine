<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wenn eine der Rollen des Users requires_2fa hat, der User aber kein
 * 2FA aktiviert hat, leiten wir auf den 2FA-Setup um. Logout / Profil-
 * 2FA-Routen sind ausgenommen, damit der User die Einrichtung
 * abschliessen oder sich abmelden kann.
 */
class EnforceTwoFactorByRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->isServiceAccount() || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        $needs = $user->roles->contains(fn ($role) => (bool) $role->requires_2fa);
        if (! $needs) {
            return $next($request);
        }

        $allowed = [
            'two-factor.show',
            'two-factor.confirm',
            'logout',
        ];
        if (in_array($request->route()?->getName(), $allowed, true)) {
            return $next($request);
        }

        return redirect()->route('two-factor.show')
            ->with('status', 'Ihre Rolle erfordert 2FA. Bitte richten Sie die Zweitfaktor-Authentifizierung jetzt ein.');
    }
}
