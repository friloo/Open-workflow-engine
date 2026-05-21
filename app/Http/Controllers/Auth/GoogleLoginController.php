<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google Workspace SSO via Socialite (built-in google driver).
 * Optional Domain-Lock: services.google.hosted_domain restricts to one
 * Google-Workspace-Tenant (so only users from firma.de can log in).
 */
class GoogleLoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function redirect(): Response
    {
        if (! $this->enabled()) {
            abort(404);
        }

        $driver = Socialite::driver('google')->scopes(['openid', 'email', 'profile']);

        $hostedDomain = config('services.google.hosted_domain');
        if ($hostedDomain) {
            // hd param hints Google's login UI + filters which domains can grant consent
            $driver = $driver->with(['hd' => $hostedDomain]);
        }

        return $driver->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->enabled()) {
            abort(404);
        }

        try {
            $oauthUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google callback failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withErrors(['email' => 'Google-Anmeldung fehlgeschlagen: '.$e->getMessage()]);
        }

        $sub = (string) $oauthUser->getId();
        $email = strtolower((string) $oauthUser->getEmail());
        $name = $oauthUser->getName() ?: $email;

        if (! $sub || ! $email) {
            return redirect()->route('login')->withErrors(['email' => 'Google hat keine vollständigen Profilangaben geliefert.']);
        }

        // Enforce hosted_domain restriction (defense-in-depth - Google's hd param is a hint, not a guarantee)
        $hostedDomain = config('services.google.hosted_domain');
        if ($hostedDomain) {
            $userDomain = $oauthUser->user['hd'] ?? substr(strrchr($email, '@') ?: '', 1);
            if (strcasecmp($userDomain, $hostedDomain) !== 0) {
                return redirect()->route('login')->withErrors([
                    'email' => "Nur Konten der Domain {$hostedDomain} dürfen sich anmelden.",
                ]);
            }
        }

        $autoProvision = (bool) config('services.google.auto_provision', true);
        $defaultRole = config('services.google.default_role', 'employee');

        $user = User::where('google_subject', $sub)->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            if (! $autoProvision) {
                return redirect()->route('login')->withErrors(['email' => 'Konto nicht gefunden. Bitte vom Administrator anlegen lassen.']);
            }
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'is_active' => true,
                'email_verified_at' => now(),
                'google_subject' => $sub,
            ]);
            $user->assignRole($defaultRole);
            $this->audit->log('auth.google.provisioned', $user, null, ['email' => $email, 'sub' => $sub],
                "Neuer Benutzer via Google angelegt: {$email}", $user->id);
        } else {
            $user->forceFill([
                'google_subject' => $user->google_subject ?: $sub,
                'name' => $user->name ?: $name,
            ])->save();
        }

        if (! $user->is_active) {
            $this->audit->log('auth.google.blocked', $user, null, null,
                "Google-Login für inaktiven Account {$user->email}", $user->id);
            return redirect()->route('login')->withErrors(['email' => 'Dieses Konto ist deaktiviert.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log('auth.google.login', $user, null, null,
            "Google-Login: {$user->email}", $user->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function enabled(): bool
    {
        return (bool) config('services.google.enabled')
            && ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'));
    }
}
