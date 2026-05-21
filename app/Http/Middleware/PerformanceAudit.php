<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Misst pro Request die Dauer + Anzahl der DB-Queries. Wenn ein
 * Threshold überschritten wird, wandert ein Eintrag mit Route,
 * Methode, Dauer und Query-Count ins Laravel-Log (channel: perf).
 *
 * Stellschrauben in config('app.perf_audit'):
 *   threshold_ms      Default 500
 *   threshold_queries Default 40
 *
 * Bewusst leichtgewichtig: kein Profiler, keine eigene Tabelle,
 * keine Trace-Capture — nur ein Sanity-Wachposten der Slowdowns
 * sichtbar macht.
 */
class PerformanceAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $count = 0;

        DB::listen(function () use (&$count) { $count++; });

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $thMs = (int) config('app.perf_audit.threshold_ms', 500);
        $thQ = (int) config('app.perf_audit.threshold_queries', 40);

        // Nur weben-Routen logen (api/web). Asset-Requests kommen hier eh nicht durch.
        if ($durationMs > $thMs || $count > $thQ) {
            $route = optional($request->route())->getName() ?: $request->path();
            Log::channel('perf')->warning('slow-route', [
                'route' => $route,
                'method' => $request->method(),
                'duration_ms' => $durationMs,
                'queries' => $count,
                'user_id' => $request->user()?->id,
                'status' => $response->getStatusCode(),
            ]);
        }

        // Bei Bedarf kann ein Debug-Header eingeblendet werden — aktivierbar via
        // APP_PERF_HEADER=true in der .env.
        if (config('app.perf_audit.send_header', false)) {
            $response->headers->set('X-Server-Duration', $durationMs.'ms');
            $response->headers->set('X-DB-Queries', (string) $count);
        }

        return $response;
    }
}
