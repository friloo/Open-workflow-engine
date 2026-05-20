<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowsApiController extends Controller
{
    /** GET /api/v1/workflows — Liste aktiver Workflows. */
    public function index(): JsonResponse
    {
        $items = Workflow::where('status', Workflow::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'trigger_type'])
            ->map(fn ($w) => [
                'id' => $w->id, 'name' => $w->name, 'slug' => $w->slug,
                'trigger_type' => $w->trigger_type,
            ]);
        return response()->json(['data' => $items]);
    }

    /** GET /api/v1/workflow-instances — eigene Vorgaenge. */
    public function instances(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(100, max(1, (int) $request->get('per_page', 25)));

        $q = WorkflowInstance::query()
            ->with(['workflow:id,name'])
            ->where('started_by', $user->id)
            ->orderByDesc('id');

        if ($wf = $request->get('workflow_id')) $q->where('workflow_id', (int) $wf);
        if ($status = $request->get('status')) $q->where('status', $status);

        $paginated = $q->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn ($i) => [
                'id' => $i->id,
                'workflow' => ['id' => $i->workflow->id ?? null, 'name' => $i->workflow->name ?? null],
                'status' => $i->status,
                'current_step_key' => $i->current_step_key,
                'started_at' => $i->started_at?->toIso8601String(),
                'completed_at' => $i->completed_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /** GET /api/v1/workflow-instances/{instance} — Detail. */
    public function instance(Request $request, WorkflowInstance $instance): JsonResponse
    {
        if ($instance->started_by !== $request->user()->id && ! $request->user()->hasAnyPermission(['workflows.design', 'workflows.view'])) {
            abort(403);
        }
        $instance->load('workflow:id,name', 'stepExecutions');

        return response()->json(['data' => [
            'id' => $instance->id,
            'workflow' => ['id' => $instance->workflow->id, 'name' => $instance->workflow->name],
            'status' => $instance->status,
            'current_step_key' => $instance->current_step_key,
            'started_at' => $instance->started_at?->toIso8601String(),
            'completed_at' => $instance->completed_at?->toIso8601String(),
            'data' => (object) ($instance->data ?? []),
            'steps' => $instance->stepExecutions->map(fn ($s) => [
                'id' => $s->id,
                'step_key' => $s->step_key,
                'step_type' => $s->step_type,
                'assigned_at' => $s->assigned_at?->toIso8601String(),
                'completed_at' => $s->completed_at?->toIso8601String(),
                'decision' => $s->decision,
                'comment' => $s->comment,
            ]),
        ]]);
    }
}
