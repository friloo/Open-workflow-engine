<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Zeigt die letzten Einträge aus storage/logs/perf.log — also Routen
 * die in der PerformanceAudit-Middleware die Threshold-Werte für
 * Dauer oder Query-Count gerissen haben.
 *
 * Liest die Datei direkt; bewusst kein Index/DB-Eintrag, weil die
 * Schwellen-Werte ohnehin niedrige Volumes erzeugen. Bei mehr als
 * ~5000 Einträgen pro Tag → Threshold hochsetzen oder Worker fixen.
 */
class PerfController extends Controller
{
    public function index(): View
    {
        // Heute + gestern zusammenfassen (rotierte Datei).
        $files = collect([
            storage_path('logs/perf-'.now()->toDateString().'.log'),
            storage_path('logs/perf-'.now()->subDay()->toDateString().'.log'),
            storage_path('logs/perf.log'),
        ])->filter(fn ($p) => is_file($p))->unique()->values();

        $entries = collect();
        foreach ($files as $path) {
            foreach ($this->tail($path, 500) as $line) {
                $parsed = $this->parseLine($line);
                if ($parsed) $entries->push($parsed);
            }
        }

        $entries = $entries
            ->sortByDesc('timestamp')
            ->take(200)
            ->values();

        // Aggregat: Top-Routen by Anzahl Vorkommen
        $top = $entries->groupBy('route')->map(fn ($items) => [
            'route' => $items->first()['route'],
            'count' => $items->count(),
            'max_ms' => (int) $items->max('duration_ms'),
            'avg_ms' => (int) round($items->avg('duration_ms')),
            'max_queries' => (int) $items->max('queries'),
        ])->sortByDesc('count')->take(15)->values();

        return view('admin.perf.index', [
            'entries' => $entries,
            'top' => $top,
            'threshold_ms' => config('app.perf_audit.threshold_ms'),
            'threshold_queries' => config('app.perf_audit.threshold_queries'),
        ]);
    }

    /** Lies die letzten N Zeilen einer Datei effizient (rückwärts). */
    private function tail(string $path, int $lines): array
    {
        $f = @fopen($path, 'r');
        if (! $f) return [];
        $buffer = '';
        $chunk = 4096;
        $offset = 0;
        $found = [];
        fseek($f, 0, SEEK_END);
        $size = ftell($f);
        while ($offset < $size && substr_count($buffer, "\n") <= $lines) {
            $read = min($chunk, $size - $offset);
            $offset += $read;
            fseek($f, -$offset, SEEK_END);
            $buffer = fread($f, $read).$buffer;
        }
        fclose($f);
        $arr = preg_split('/\R/', trim($buffer)) ?: [];
        return array_slice($arr, -$lines);
    }

    /**
     * Laravel-Default-Log-Format:
     *   [2026-05-19 22:01:14] local.WARNING: slow-route {"route":"…","duration_ms":850,…}
     */
    private function parseLine(string $line): ?array
    {
        if (! preg_match('/^\[(?<ts>[^\]]+)\] [^.]+\.WARNING: slow-route (?<json>\{.+\})/', $line, $m)) {
            return null;
        }
        $data = json_decode($m['json'], true);
        if (! is_array($data)) return null;
        return [
            'timestamp' => $m['ts'],
            'route' => $data['route'] ?? '?',
            'method' => $data['method'] ?? 'GET',
            'duration_ms' => (int) ($data['duration_ms'] ?? 0),
            'queries' => (int) ($data['queries'] ?? 0),
            'status' => (int) ($data['status'] ?? 0),
            'user_id' => $data['user_id'] ?? null,
        ];
    }
}
