<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
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
            'verifiedAt' => now(),
        ]);
    }
}
