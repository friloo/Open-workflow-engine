<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solange OWE noch nicht installiert ist (Marker storage/app/.installed
 * fehlt), schicken wir jeden Aufruf zum Installer. Ausgenommen sind die
 * Installer-Routen selbst und der Maintenance-Endpoint.
 */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installer::isInstalled()) {
            return $next($request);
        }
        if ($request->is('install*') || $request->is('up')) {
            return $next($request);
        }
        return redirect('/install');
    }
}
