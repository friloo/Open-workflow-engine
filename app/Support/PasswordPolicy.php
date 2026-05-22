<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Liest die Password-Policy aus den Admin-Settings (security.password.*)
 * und baut daraus eine Laravel-Validation-Rule. Greift in jedem Form-
 * Request, der diese Klasse statt der Default-Rule nutzt.
 *
 * Settings-Keys + Defaults:
 *   security.password.min_length          (int, default 8)
 *   security.password.require_uppercase   (bool, default false)
 *   security.password.require_lowercase   (bool, default false)
 *   security.password.require_number      (bool, default false)
 *   security.password.require_symbol      (bool, default false)
 *   security.password.max_age_days        (int|null, default null = aus)
 *
 * 'Forced Rotation' (max_age_days) wird nicht von der Rule erzwungen,
 * sondern beim Login ueber eine Middleware geprueft (separates Feature).
 */
class PasswordPolicy
{
    /**
     * @return array<int, string|object>
     */
    public static function rules(): array
    {
        $rule = PasswordRule::min((int) Settings::get('security.password.min_length', 8));

        if ((bool) Settings::get('security.password.require_uppercase', false)) {
            $rule = $rule->mixedCase();
        }
        if ((bool) Settings::get('security.password.require_number', false)) {
            $rule = $rule->numbers();
        }
        if ((bool) Settings::get('security.password.require_symbol', false)) {
            $rule = $rule->symbols();
        }

        return ['required', 'string', $rule, 'confirmed'];
    }

    /** Reine Rule ohne required+confirmed — fuer Admin-Setzt-Passwort-Form. */
    public static function ruleObject(): object
    {
        $rule = PasswordRule::min((int) Settings::get('security.password.min_length', 8));
        if ((bool) Settings::get('security.password.require_uppercase', false)) $rule = $rule->mixedCase();
        if ((bool) Settings::get('security.password.require_number', false)) $rule = $rule->numbers();
        if ((bool) Settings::get('security.password.require_symbol', false)) $rule = $rule->symbols();
        return $rule;
    }

    /**
     * Aktuelle Policy-Snapshot als Array (fuer UI-Beschreibung).
     *
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'min_length' => (int) Settings::get('security.password.min_length', 8),
            'require_uppercase' => (bool) Settings::get('security.password.require_uppercase', false),
            'require_lowercase' => (bool) Settings::get('security.password.require_lowercase', false),
            'require_number' => (bool) Settings::get('security.password.require_number', false),
            'require_symbol' => (bool) Settings::get('security.password.require_symbol', false),
            'max_age_days' => Settings::get('security.password.max_age_days', null),
        ];
    }

    /** Lesbare Bedingungs-Liste fuer den Help-Text in Formularen. */
    public static function describeLines(): array
    {
        $d = self::describe();
        $lines = ["Mindestens {$d['min_length']} Zeichen"];
        if ($d['require_uppercase']) $lines[] = 'mind. ein Großbuchstabe';
        if ($d['require_number']) $lines[] = 'mind. eine Zahl';
        if ($d['require_symbol']) $lines[] = 'mind. ein Sonderzeichen';
        if (! empty($d['max_age_days'])) $lines[] = "läuft nach {$d['max_age_days']} Tagen ab";
        return $lines;
    }
}
