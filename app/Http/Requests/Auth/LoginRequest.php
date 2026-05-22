<?php

namespace App\Http\Requests\Auth;

use App\Services\AuditLogger;
use App\Services\LdapAuthenticator;
use App\Services\LdapUserProvisioner;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Wenn LDAP aktiv ist, akzeptieren wir auch sAMAccountName/uid statt
        // einer E-Mail im "email"-Feld — viele AD-Nutzer melden sich mit
        // ihrem Anmeldenamen an, nicht mit ihrer Mail.
        $emailRules = config('services.ldap.enabled')
            ? ['required', 'string', 'max:255']
            : ['required', 'string', 'email'];

        return [
            'email' => $emailRules,
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if ($this->tryLdapAuthentication()) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            app(AuditLogger::class)->log('auth.login.failed', null, null, [
                'email' => (string) $this->string('email'),
                'ip' => $this->ip(),
                'ua' => substr((string) $this->header('User-Agent'), 0, 255),
            ], 'Fehlgeschlagener Login fuer '.(string) $this->string('email'), null);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Versucht den User per LDAP-Bind zu authentifizieren. Liefert true,
     * wenn der Login erfolgreich war (User ist dann eingeloggt). false
     * bedeutet: LDAP ist aus oder hat keinen Treffer — also weiter mit
     * lokalem Login. Wirft ValidationException nur wenn LDAP einen
     * harten Fehler liefert UND der lokale Fallback nicht greift.
     */
    private function tryLdapAuthentication(): bool
    {
        if (! config('services.ldap.enabled')) return false;

        /** @var LdapAuthenticator $ldap */
        $ldap = app(LdapAuthenticator::class);
        $result = $ldap->authenticate(
            (string) $this->string('email'),
            (string) $this->string('password'),
        );

        if (! ($result['ok'] ?? false)) return false;

        /** @var LdapUserProvisioner $provisioner */
        $provisioner = app(LdapUserProvisioner::class);
        $user = $provisioner->findOrCreate($result);
        if (! $user) return false;

        if (! $user->is_active) {
            app(AuditLogger::class)->log('auth.ldap.blocked', $user, null, null,
                "LDAP-Login für inaktiven Account {$user->email}", $user->id);
            throw ValidationException::withMessages(['email' => 'Dieses Konto ist deaktiviert.']);
        }

        Auth::login($user, $this->boolean('remember'));
        $user->forceFill(['last_login_at' => now()])->save();
        app(AuditLogger::class)->log('auth.ldap.login', $user, null, null,
            "LDAP-Login: {$user->email}", $user->id);

        return true;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
