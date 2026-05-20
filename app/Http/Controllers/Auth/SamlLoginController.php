<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OneLogin\Saml2\Auth as Saml2Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SAML 2.0 SSO (SP-initiated) via onelogin/php-saml.
 *
 * Settings (auth.saml.*):
 * - idp_entity_id    — IdP-EntityID
 * - idp_sso_url      — SingleSignOn-URL des IdP
 * - idp_x509_cert    — Public Cert des IdP (PEM, ohne BEGIN/END-Zeilen ok)
 * - sp_entity_id     — eigene EntityID (default: app.url/saml/metadata)
 * - email_attribute  — Attribute-Name fuer E-Mail (default "email")
 * - name_attribute   — Attribute-Name fuer Anzeigename (default "displayName")
 * - auto_provision / default_role / button_label
 */
class SamlLoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function redirect(): Response
    {
        if (! $this->enabled()) {
            abort(404);
        }

        $auth = new Saml2Auth($this->settings());
        $auth->login(route('dashboard'));

        // Saml2Auth::login() does the redirect via header() and exits internally,
        // but we return a Response stub for completeness.
        return response('Redirecting to IdP', 302);
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->enabled()) {
            abort(404);
        }

        try {
            $auth = new Saml2Auth($this->settings());
            $auth->processResponse();
        } catch (\Throwable $e) {
            Log::warning('SAML processResponse failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')->withErrors(['email' => 'SAML-Antwort ungueltig: '.$e->getMessage()]);
        }

        if (! empty($auth->getErrors())) {
            $reason = $auth->getLastErrorReason() ?: implode(', ', $auth->getErrors());
            Log::warning('SAML errors', ['errors' => $auth->getErrors(), 'reason' => $reason]);
            return redirect()->route('login')->withErrors(['email' => 'SAML-Fehler: '.$reason]);
        }
        if (! $auth->isAuthenticated()) {
            return redirect()->route('login')->withErrors(['email' => 'SAML: nicht authentifiziert.']);
        }

        $nameId = $auth->getNameId();
        $attrs = $auth->getAttributes();
        $emailAttr = config('services.saml.email_attribute', 'email');
        $nameAttr = config('services.saml.name_attribute', 'displayName');

        $email = strtolower((string) (self::first($attrs, $emailAttr) ?: $nameId));
        $name = (string) (self::first($attrs, $nameAttr) ?: $email);

        if (! $email || ! str_contains($email, '@')) {
            return redirect()->route('login')->withErrors(['email' => 'SAML: kein E-Mail-Attribut gefunden ('.$emailAttr.').']);
        }

        $autoProvision = (bool) config('services.saml.auto_provision', true);
        $defaultRole = config('services.saml.default_role', 'employee');

        $user = User::where('saml_nameid', $nameId)->first()
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
                'saml_nameid' => $nameId,
            ]);
            $user->assignRole($defaultRole);
            $this->audit->log('auth.saml.provisioned', $user, null, ['email' => $email, 'nameid' => $nameId],
                "Neuer Benutzer via SAML angelegt: {$email}", $user->id);
        } else {
            $user->forceFill([
                'saml_nameid' => $user->saml_nameid ?: $nameId,
                'name' => $user->name ?: $name,
            ])->save();
        }

        if (! $user->is_active) {
            $this->audit->log('auth.saml.blocked', $user, null, null,
                "SAML-Login fuer inaktiven Account {$user->email}", $user->id);
            return redirect()->route('login')->withErrors(['email' => 'Dieses Konto ist deaktiviert.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log('auth.saml.login', $user, null, null,
            "SAML-Login: {$user->email}", $user->id);

        $relayState = $request->input('RelayState');
        return redirect()->intended($relayState ?: route('dashboard', absolute: false));
    }

    public function metadata(): Response
    {
        if (! $this->enabled()) {
            abort(404);
        }

        try {
            $auth = new Saml2Auth($this->settings());
            $metadata = $auth->getSettings()->getSPMetadata();
            $errors = $auth->getSettings()->validateMetadata($metadata);
            if (! empty($errors)) {
                Log::warning('SAML metadata invalid', ['errors' => $errors]);
            }
            return response($metadata, 200, ['Content-Type' => 'text/xml']);
        } catch (\Throwable $e) {
            return response('SAML metadata error: '.$e->getMessage(), 500);
        }
    }

    private function enabled(): bool
    {
        return (bool) config('services.saml.enabled')
            && ! empty(config('services.saml.idp_entity_id'))
            && ! empty(config('services.saml.idp_sso_url'))
            && ! empty(config('services.saml.idp_x509_cert'));
    }

    private function settings(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $spEntityId = config('services.saml.sp_entity_id') ?: $appUrl.'/saml/metadata';

        return [
            'strict' => true,
            'debug' => false,
            'sp' => [
                'entityId' => $spEntityId,
                'assertionConsumerService' => [
                    'url' => $appUrl.'/auth/saml/callback',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            ],
            'idp' => [
                'entityId' => config('services.saml.idp_entity_id'),
                'singleSignOnService' => [
                    'url' => config('services.saml.idp_sso_url'),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => self::normalizeCert((string) config('services.saml.idp_x509_cert')),
            ],
            'security' => [
                'wantAssertionsSigned' => (bool) config('services.saml.want_assertions_signed', false),
                'wantMessagesSigned' => (bool) config('services.saml.want_messages_signed', false),
            ],
        ];
    }

    private static function normalizeCert(string $cert): string
    {
        $cert = trim($cert);
        $cert = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $cert);
        $cert = preg_replace('/-----END CERTIFICATE-----/', '', $cert);
        return trim(preg_replace('/\s+/', '', (string) $cert));
    }

    private static function first(array $attrs, string $key): ?string
    {
        if (! isset($attrs[$key])) return null;
        $val = $attrs[$key];
        return is_array($val) ? (string) ($val[0] ?? '') : (string) $val;
    }
}
