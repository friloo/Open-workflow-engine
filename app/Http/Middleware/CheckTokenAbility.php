<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('api_token');
        if (! $token) {
            return response()->json(['message' => 'Kein API-Token im Kontext.'], 401);
        }
        $user = Auth::user();
        foreach ($abilities as $ability) {
            // Token darf nie mehr als der Benutzer selbst.
            if ($user && ! $user->hasPermission($ability)) {
                return response()->json(['message' => "Berechtigung fehlt: {$ability}"], 403);
            }
            if (! $token->can($ability)) {
                return response()->json(['message' => "Token darf {$ability} nicht."], 403);
            }
        }
        return $next($request);
    }
}
