<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt die App-Locale pro Request. Reihenfolge:
 *   1. user.locale (wenn eingeloggt und gesetzt)
 *   2. Session 'locale' (z.B. ?lang=en + Logout)
 *   3. Accept-Language Header (wenn die Sprache in available_locales ist)
 *   4. config('app.locale')
 *
 * Liste der gültigen Locales kommt aus config('app.available_locales').
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('app.available_locales', ['de' => 'Deutsch']));
        $fallback = config('app.locale', 'de');
        $locale = null;

        $user = $request->user();
        if ($user && ! empty($user->locale) && in_array($user->locale, $available, true)) {
            $locale = $user->locale;
        }

        if (! $locale && $request->session()->has('locale')) {
            $candidate = $request->session()->get('locale');
            if (in_array($candidate, $available, true)) {
                $locale = $candidate;
            }
        }

        if (! $locale) {
            $accept = $request->getPreferredLanguage($available);
            if ($accept && in_array($accept, $available, true)) {
                $locale = $accept;
            }
        }

        App::setLocale($locale ?: $fallback);

        return $next($request);
    }
}
