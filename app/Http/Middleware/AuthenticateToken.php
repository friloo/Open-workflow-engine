<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loest "Authorization: Bearer owe_..." gegen api_tokens auf und meldet
 * den zugehörigen Benutzer für die Anfrage an. Inaktive Benutzer und
 * widerrufene/abgelaufene Tokens werden abgelehnt.
 *
 * Ability-Checks erfolgen mit `token-ability:<slug>` in den Routen.
 */
class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->bearerToken();
        if ($header === '' || ! str_starts_with($header, 'owe_')) {
            return response()->json(['message' => 'Token fehlt.'], 401);
        }

        $hash = hash('sha256', $header);
        $token = ApiToken::where('token_hash', $hash)->first();
        if (! $token || ! $token->isActive() || ! $token->user || ! $token->user->is_active) {
            return response()->json(['message' => 'Token ungültig.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
