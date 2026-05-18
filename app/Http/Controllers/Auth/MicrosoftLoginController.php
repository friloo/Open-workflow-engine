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

class MicrosoftLoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function redirect(): Response
    {
        if (! $this->enabled()) {
            abort(404);
        }

        return Socialite::driver('microsoft-azure')
            ->scopes(['openid', 'email', 'profile', 'User.Read'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->enabled()) {
            abort(404);
        }

        try {
            $oauthUser = Socialite::driver('microsoft-azure')->user();
        } catch (\Throwable $e) {
            Log::warning('M365 callback failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withErrors(['email' => 'Microsoft-Anmeldung fehlgeschlagen: '.$e->getMessage()]);
        }

        $email = strtolower((string) ($oauthUser->getEmail() ?: $oauthUser->user['userPrincipalName'] ?? ''));
        $oid = (string) $oauthUser->getId();
        if (! $email || ! $oid) {
            return redirect()->route('login')->withErrors(['email' => 'Microsoft hat keine vollstaendigen Profilangaben geliefert.']);
        }

        $autoProvision = (bool) config('services.microsoft-azure.auto_provision', true);
        $defaultRoleSlug = config('services.microsoft-azure.default_role', 'employee');

        $user = User::where('m365_object_id', $oid)->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            if (! $autoProvision) {
                return redirect()->route('login')->withErrors(['email' => 'Konto nicht gefunden. Bitte vom Administrator anlegen lassen.']);
            }
            $user = User::create([
                'name' => $oauthUser->getName() ?: $email,
                'email' => $email,
                'is_active' => true,
                'email_verified_at' => now(),
                'm365_object_id' => $oid,
                'job_title' => $oauthUser->user['jobTitle'] ?? null,
                'department' => $oauthUser->user['department'] ?? null,
                'phone' => $oauthUser->user['mobilePhone'] ?? null,
                'prefer_m365_supervisor' => true,
            ]);
            $user->assignRole($defaultRoleSlug);
            $this->audit->log('auth.m365.provisioned', $user, null, ['email' => $email, 'm365_oid' => $oid],
                "Neuer Benutzer via M365 angelegt: {$email}", $user->id);
        } else {
            $user->forceFill([
                'm365_object_id' => $user->m365_object_id ?: $oid,
                'name' => $user->name ?: ($oauthUser->getName() ?: $email),
            ])->save();
        }

        if (! $user->is_active) {
            $this->audit->log('auth.m365.blocked', $user, null, null,
                "M365-Login fuer inaktiven Account {$user->email}", $user->id);
            return redirect()->route('login')->withErrors(['email' => 'Dieses Konto ist deaktiviert.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log('auth.m365.login', $user, null, null,
            "M365-Login: {$user->email}", $user->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function enabled(): bool
    {
        return (bool) config('services.microsoft-azure.enabled')
            && ! empty(config('services.microsoft-azure.client_id'))
            && ! empty(config('services.microsoft-azure.client_secret'))
            && ! empty(config('services.microsoft-azure.tenant'));
    }
}
