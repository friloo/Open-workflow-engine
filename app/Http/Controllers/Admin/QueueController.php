<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Zeigt den Status der eingebauten DB-Queue: offene + fehlgeschlagene
 * Jobs, Worker-Konfiguration, kurze Hinweise zur Einrichtung.
 *
 * Bewusst minimal — wer ein vollwertiges Queue-Dashboard will, setzt
 * Horizon dazu (Redis erforderlich). Diese Seite reicht fuer 'datenbank'-
 * Queue im typischen Mittelstands-Setup.
 */
class QueueController extends Controller
{
    public function index(): View
    {
        $connection = config('queue.default');
        $isSync = $connection === 'sync';

        // Pending: in der jobs-Tabelle (nur wenn nicht sync).
        $pending = 0;
        if (! $isSync && \Schema::hasTable('jobs')) {
            $pending = DB::table('jobs')->count();
        }

        $failed = 0;
        $recentFailed = [];
        if (\Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $recentFailed = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(10)
                ->get(['id', 'queue', 'payload', 'exception', 'failed_at'])
                ->map(function ($row) {
                    $payload = json_decode($row->payload, true) ?: [];
                    return [
                        'id' => $row->id,
                        'queue' => $row->queue,
                        'job' => $payload['displayName'] ?? '?',
                        'failed_at' => $row->failed_at,
                        'first_line' => trim(strtok($row->exception, "\n")),
                    ];
                });
        }

        return view('admin.queue.index', [
            'connection' => $connection,
            'is_sync' => $isSync,
            'queue_ocr' => (bool) config('app.queue_ocr', false),
            'pending' => $pending,
            'failed' => $failed,
            'recent_failed' => $recentFailed,
        ]);
    }
}
