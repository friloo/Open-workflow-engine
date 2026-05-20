<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkflowStepExecution;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TasksApiController extends Controller
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    /**
     * GET /api/v1/tasks — eigene offene Aufgaben.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $roleIds = $user->roles->pluck('id');

        $q = WorkflowStepExecution::query()
            ->with(['instance.workflow', 'instance.starter'])
            ->whereNull('completed_at')
            ->where(function ($q2) use ($user, $roleIds) {
                $q2->where('assigned_to_user_id', $user->id);
                if ($roleIds->isNotEmpty()) $q2->orWhereIn('assigned_to_role_id', $roleIds);
            })
            ->where(function ($q2) {
                $q2->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            });

        $items = $q->orderBy('due_at')->limit(100)->get()->map(function ($s) {
            $node = data_get($s->instance->version->definition ?? [], "drawflow.Home.data.{$s->step_key}");
            return [
                'id' => $s->id,
                'step_key' => $s->step_key,
                'label' => data_get($node, 'data.label', 'Aufgabe'),
                'workflow' => [
                    'id' => $s->instance->workflow?->id,
                    'name' => $s->instance->workflow?->name,
                ],
                'instance_id' => $s->workflow_instance_id,
                'starter_email' => $s->instance->starter?->email,
                'assigned_at' => $s->assigned_at?->toIso8601String(),
                'due_at' => $s->due_at?->toIso8601String(),
                'overdue' => $s->due_at && $s->due_at->isPast(),
            ];
        });

        return response()->json(['data' => $items, 'count' => $items->count()]);
    }

    /**
     * POST /api/v1/tasks/{step}/decide — Decision durchfuehren.
     * Erwartet 'decision' (approved/rejected/forwarded), optional
     * 'comment', 'extra' (Zusatzfelder), 'forward_user_id'.
     */
    public function decide(Request $request, WorkflowStepExecution $step): JsonResponse
    {
        $user = $request->user();
        if ($step->completed_at) {
            return response()->json(['error' => 'Schritt bereits abgeschlossen.'], 410);
        }
        $isAssignee = $step->assigned_to_user_id === $user->id
            || ($step->assigned_to_role_id && $user->roles->pluck('id')->contains($step->assigned_to_role_id));
        if (! $isAssignee) {
            return response()->json(['error' => 'Nicht zustaendig fuer diese Aufgabe.'], 403);
        }

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,forwarded'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'extra' => ['nullable', 'array'],
            'forward_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($data['decision'] === 'forwarded') {
            if (empty($data['forward_user_id'])) {
                return response()->json(['error' => 'forward_user_id erforderlich.'], 422);
            }
            $target = User::findOrFail($data['forward_user_id']);
            $this->engine->forwardStep($step, $target, $data['comment'] ?? null, $user->id);
            return response()->json(['status' => 'forwarded', 'to' => $target->email]);
        }

        // Falls extra-Felder mitgekommen sind, koennen wir sie hier
        // theoretisch verarbeiten — die volle Validierung liegt im
        // Web-Controller. Fuer die API behalten wir's einfach: wir
        // schreiben sie direkt als indexed_fields an den Anhang
        // (sofern target=doc gewollt waere — pro Workflow konfiguriert).
        // Hier nicht ausgewertet; siehe Web-Variante fuer die volle Logik.

        $this->engine->completeStep($step, $data['decision'], $data['comment'] ?? null, $user->id);
        return response()->json(['status' => $data['decision']]);
    }
}
