<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic OpenID-Connect login via Authorization-Code-Flow.
 * Works with Keycloak, Authentik, Auth0, Okta, Zitadel, ...
 *
 * Configuration via Settings UI (auth.oidc.*):
 * - issuer (e.g. https://id.example.com/realms/main)
 *   The provider's discovery document at <issuer>/.well-known/openid-configuration
 *   is fetched + cached and provides authorization_endpoint, token_endpoint,
 *   userinfo_endpoint.
 * - client_id / client_secret
 * - redirect_uri (must match what is registered with the IdP)
 * - scopes (default: openid email profile)
 * - button_label (default: "Mit Single Sign-On anmelden")
 * - auto_provision / default_role
 */
class OidcLoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function redirect(Request $request): Response
    {
        if (! $this->enabled()) {
            abort(404);
        }

        $cfg = $this->discovery();
        if (! $cfg) {
            return response('OIDC-Discovery fehlgeschlagen. Issuer prüfen.', 503);
        }

        $state = Str::random(32);
        $nonce = Str::random(32);
        $request->session()->put('oidc.state', $state);
        $request->session()->put('oidc.nonce', $nonce);

        $params = [
            'client_id' => config('services.oidc.client_id'),
            'redirect_uri' => config('services.oidc.redirect'),
            'response_type' => 'code',
            'scope' => config('services.oidc.scopes', 'openid email profile'),
            'state' => $state,
            'nonce' => $nonce,
        ];

        return redirect($cfg['authorization_endpoint'].'?'.http_build_query($params));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->enabled()) {
            abort(404);
        }

        $expectedState = $request->session()->pull('oidc.state');
        $expectedNonce = $request->session()->pull('oidc.nonce');
        $state = $request->query('state');
        $code = $request->query('code');

        if (! $expectedState || $expectedState !== $state) {
            return redirect()->route('login')->withErrors(['email' => 'OIDC-Login fehlgeschlagen: State-Mismatch.']);
        }
        if (! $code) {
            $err = $request->query('error_description') ?: $request->query('error') ?: 'kein Code';
            return redirect()->route('login')->withErrors(['email' => 'OIDC-Login abgebrochen: '.$err]);
        }

        $cfg = $this->discovery();
        if (! $cfg) {
            return redirect()->route('login')->withErrors(['email' => 'OIDC-Discovery nicht erreichbar.']);
        }

        try {
            $tokenResp = Http::asForm()->post($cfg['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('services.oidc.redirect'),
                'client_id' => config('services.oidc.client_id'),
                'client_secret' => config('services.oidc.client_secret'),
            ])->throw()->json();

            $userInfo = Http::withToken($tokenResp['access_token'])
                ->acceptJson()
                ->get($cfg['userinfo_endpoint'])
                ->throw()
                ->json();
        } catch (\Throwable $e) {
            Log::warning('OIDC callback failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withErrors(['email' => 'OIDC-Login fehlgeschlagen: '.$e->getMessage()]);
        }

        $sub = (string) ($userInfo['sub'] ?? '');
        $email = strtolower((string) ($userInfo['email'] ?? ''));
        $name = $userInfo['name']
            ?? trim(($userInfo['given_name'] ?? '').' '.($userInfo['family_name'] ?? ''))
            ?: $email;

        if (! $sub) {
            return redirect()->route('login')->withErrors(['email' => 'OIDC: kein Subject im UserInfo.']);
        }
        if (! $email) {
            return redirect()->route('login')->withErrors(['email' => 'OIDC: kein E-Mail-Claim. Bitte Scope "email" prüfen.']);
        }
        if (isset($userInfo['email_verified']) && $userInfo['email_verified'] === false) {
            return redirect()->route('login')->withErrors(['email' => 'OIDC: E-Mail-Adresse ist beim IdP nicht verifiziert.']);
        }

        $autoProvision = (bool) config('services.oidc.auto_provision', true);
        $defaultRole = config('services.oidc.default_role', 'employee');

        $user = User::where('oidc_subject', $sub)->first()
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
                'oidc_subject' => $sub,
            ]);
            $user->assignRole($defaultRole);
            $this->audit->log('auth.oidc.provisioned', $user, null, ['email' => $email, 'sub' => $sub],
                "Neuer Benutzer via OIDC angelegt: {$email}", $user->id);
        } else {
            $user->forceFill([
                'oidc_subject' => $user->oidc_subject ?: $sub,
                'name' => $user->name ?: $name,
            ])->save();
        }

        if (! $user->is_active) {
            $this->audit->log('auth.oidc.blocked', $user, null, null,
                "OIDC-Login für inaktiven Account {$user->email}", $user->id);
            return redirect()->route('login')->withErrors(['email' => 'Dieses Konto ist deaktiviert.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log('auth.oidc.login', $user, null, null,
            "OIDC-Login: {$user->email}", $user->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function enabled(): bool
    {
        return (bool) config('services.oidc.enabled')
            && ! empty(config('services.oidc.issuer'))
            && ! empty(config('services.oidc.client_id'))
            && ! empty(config('services.oidc.client_secret'));
    }

    /**
     * Fetches + caches the .well-known/openid-configuration document.
     * Returns null on failure so callers can render a helpful error.
     */
    private function discovery(): ?array
    {
        $issuer = rtrim((string) config('services.oidc.issuer'), '/');
        if (! $issuer) return null;

        $cacheKey = 'oidc.discovery:'.md5($issuer);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;

        try {
            $doc = Http::acceptJson()->get($issuer.'/.well-known/openid-configuration')->throw()->json();
        } catch (\Throwable $e) {
            Log::warning('OIDC discovery failed', ['issuer' => $issuer, 'error' => $e->getMessage()]);
            return null;
        }

        if (! isset($doc['authorization_endpoint'], $doc['token_endpoint'], $doc['userinfo_endpoint'])) {
            return null;
        }
        Cache::put($cacheKey, $doc, now()->addHour());
        return $doc;
    }
}
