<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->orderByDesc('id');

        if ($event = $request->get('event')) {
            $query->where('event', $event);
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%");
            });
        }

        return view('admin.audit.index', [
            'entries' => $query->paginate(50)->withQueryString(),
            'events' => AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
            'filterEvent' => $event,
            'search' => $search,
        ]);
    }

    public function verify(): View
    {
        $result = $this->audit->verifyChain();

        return view('admin.audit.verify', [
            'broken' => $result,
            'total' => AuditLog::count(),
            'firstEntry' => AuditLog::orderBy('id')->first(),
            'lastEntry' => AuditLog::orderByDesc('id')->first(),
            'verifiedAt' => now(),
        ]);
    }

    /**
     * Druckbares Audit-Zertifikat: Beleg dass die Hash-Kette zu einem
     * bestimmten Zeitpunkt intakt war. Enthaelt Total + Erst-/Letzter
     * Eintrag + Verifikations-Ergebnis. Fuer Audits.
     */
    public function verifyPdf(Request $request): Response
    {
        $result = $this->audit->verifyChain();
        $first = AuditLog::orderBy('id')->first();
        $last = AuditLog::orderByDesc('id')->first();

        $pdf = Pdf::loadView('admin.audit.verify_pdf', [
            'broken' => $result,
            'total' => AuditLog::count(),
            'firstEntry' => $first,
            'lastEntry' => $last,
            'verifiedAt' => now(),
            'verifiedBy' => $request->user()->name,
        ])->setPaper('a4');

        $this->audit->log('audit.chain_verified', null, null,
            ['result' => $result === null ? 'ok' : 'broken', 'broken_at' => $result['broken_at_id'] ?? null],
            'Hash-Chain-Pruefung als PDF exportiert', $request->user()->id);

        return $pdf->download('audit-zertifikat-' . now()->format('Ymd-Hi') . '.pdf');
    }

    /**
     * PDF-Export aller Audit-Eintraege im Zeitraum (Default letzte 30 Tage).
     * Optional Event-Filter.
     */
    public function exportPdf(Request $request): Response
    {
        $from = $request->date('from') ?: now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?: now()->endOfDay();
        $event = $request->get('event');

        $q = AuditLog::with('user')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->orderBy('id');
        if ($event) $q->where('event', 'like', $event . '%');

        $entries = $q->limit(5000)->get();

        $pdf = Pdf::loadView('admin.audit.export_pdf', [
            'entries' => $entries,
            'from' => $from,
            'to' => $to,
            'event' => $event,
            'generatedAt' => now(),
            'generator' => $request->user()->name,
            'count' => $entries->count(),
        ])->setPaper('a4', 'landscape');

        $this->audit->log('audit.exported', null, null,
            ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'count' => $entries->count(), 'event' => $event],
            "Audit-Log exportiert ({$entries->count()} Eintraege)", $request->user()->id);

        return $pdf->download('audit-log-' . $from->format('Ymd') . '-bis-' . $to->format('Ymd') . '.pdf');
    }
}
