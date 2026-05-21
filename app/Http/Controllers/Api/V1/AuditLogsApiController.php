<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: Audit-Log lesen (Compliance, SIEM-Anbindung wie Splunk/Wazuh).
 * Token-Ability: audit.view (read-only).
 */
class AuditLogsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = AuditLog::query()->orderByDesc('id');

        if ($event = $request->get('event')) $q->where('event', 'like', $event.'%');
        if ($userId = (int) $request->get('user_id', 0)) $q->where('user_id', $userId);
        if ($from = $request->get('from')) $q->where('created_at', '>=', $from);
        if ($to = $request->get('to')) $q->where('created_at', '<=', $to);
        if ($subjectType = $request->get('subject_type')) {
            $q->where('auditable_type', $subjectType);
            if ($subjectId = (int) $request->get('subject_id', 0)) $q->where('auditable_id', $subjectId);
        }

        $perPage = min(200, max(10, (int) $request->get('per_page', 50)));
        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (AuditLog $l) => [
                'id' => $l->id,
                'event' => $l->event,
                'description' => $l->description,
                'user_id' => $l->user_id,
                'subject_type' => $l->auditable_type,
                'subject_id' => $l->auditable_id,
                'ip' => $l->ip,
                'created_at' => $l->created_at?->toIso8601String(),
                'hash_chain' => substr((string) $l->hash, 0, 16),
            ])->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
