<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkflowStepExecution;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $roleIds = $user->roles->pluck('id');
        $filter = $request->get('filter', 'all');
        $q = trim((string) $request->get('q', ''));

        $baseScope = fn ($query) => $query
            ->whereNull('completed_at')
            ->where(function ($q2) use ($user, $roleIds) {
                $q2->where('assigned_to_user_id', $user->id);
                if ($roleIds->isNotEmpty()) {
                    $q2->orWhereIn('assigned_to_role_id', $roleIds);
                }
            });

        // Counts pro Filter-Chip — eine Roundtrip-Query reicht nicht, aber
        // pro Chip eine count()-Query ist okay (kleine Datenmengen pro User).
        $now = now();
        $counts = [
            'all' => $baseScope(WorkflowStepExecution::query())->count(),
            'overdue' => $baseScope(WorkflowStepExecution::query())
                ->whereNotNull('due_at')->where('due_at', '<', $now)->count(),
            'today' => $baseScope(WorkflowStepExecution::query())
                ->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])->count(),
            'week' => $baseScope(WorkflowStepExecution::query())
                ->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count(),
            'mine' => $baseScope(WorkflowStepExecution::query())
                ->where('assigned_to_user_id', $user->id)->whereNull('assigned_to_role_id')->count(),
        ];

        $query = WorkflowStepExecution::query()
            ->with(['instance.workflow', 'instance.starter', 'assignedRole']);
        $baseScope($query);

        switch ($filter) {
            case 'overdue':
                $query->whereNotNull('due_at')->where('due_at', '<', $now);
                break;
            case 'today':
                $query->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
                break;
            case 'week':
                $query->whereNotNull('due_at')->whereBetween('due_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'mine':
                $query->where('assigned_to_user_id', $user->id)->whereNull('assigned_to_role_id');
                break;
        }

        if ($q !== '') {
            $query->whereHas('instance.workflow', fn ($w) => $w->where('name', 'like', '%'.$q.'%'));
        }

        $open = $query->orderBy('due_at')->paginate(20)->withQueryString();

        $myRecent = WorkflowStepExecution::query()
            ->with(['instance.workflow'])
            ->where('completed_by', $user->id)
            ->orderByDesc('completed_at')
            ->limit(5)->get();

        return view('tasks.index', compact('open', 'myRecent', 'counts', 'filter', 'q'));
    }

    public function show(WorkflowStepExecution $step, Request $request): View
    {
        $this->authorizeStep($step, $request->user());

        $step->load([
            'instance.workflow', 'instance.starter', 'instance.version',
            'instance.stepExecutions', 'instance.attachments',
            'assignedUser', 'assignedRole',
        ]);

        $node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;

        $completedKeys = $step->instance->stepExecutions
            ->whereNotNull('completed_at')
            ->pluck('step_key')->unique()->values()->all();

        return view('tasks.show', [
            'step' => $step,
            'node' => $node,
            'instance' => $step->instance,
            'directory' => [
                'users' => User::where('is_active', true)
                    ->where('id', '!=', $request->user()->id)
                    ->orderBy('name')->limit(500)->get(['id', 'name', 'email']),
            ],
            'viewerPayload' => [
                'definition' => $step->instance->version->definition,
                'completed_step_keys' => $completedKeys,
                'current_step_key' => $step->step_key,
                'status' => $step->instance->status,
            ],
        ]);
    }

    public function decide(Request $request, WorkflowStepExecution $step): RedirectResponse
    {
        $this->authorizeStep($step, $request->user());

        // Bedingte Pflichtfelder aus dem Approval-Knoten lesen.
        $node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        $requireOnApproval = (bool) data_get($node, 'data.require_comment_on_approval', false);
        $requireOnRejection = (bool) data_get($node, 'data.require_comment_on_rejection', false);

        $decision = $request->input('decision');
        $commentRule = 'nullable';
        if ($decision === 'approved' && $requireOnApproval) {
            $commentRule = 'required';
        } elseif ($decision === 'rejected' && $requireOnRejection) {
            $commentRule = 'required';
        }

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,forwarded'],
            'comment' => [$commentRule, 'string', 'max:2000'],
            'forward_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'comment.required' => $decision === 'rejected'
                ? 'Bitte gib eine Begruendung fuer die Ablehnung ein.'
                : 'Bitte gib einen Kommentar zur Genehmigung ein.',
        ]);

        if ($data['decision'] === 'forwarded') {
            if (empty($data['forward_user_id'])) {
                return back()->withErrors(['forward_user_id' => 'Bitte einen Benutzer auswaehlen.']);
            }
            $target = User::findOrFail($data['forward_user_id']);
            $this->engine->forwardStep($step, $target, $data['comment'] ?? null, $request->user()->id);
            return redirect()->route('tasks.index')->with('status', 'Aufgabe weitergeleitet an '.$target->name.'.');
        }

        $this->engine->completeStep(
            $step,
            $data['decision'],
            $data['comment'] ?? null,
            $request->user()->id,
        );

        return redirect()->route('tasks.index')->with('status', 'Entscheidung gespeichert.');
    }

    private function authorizeStep(WorkflowStepExecution $step, User $user): void
    {
        if ($step->completed_at) {
            abort(410, 'Diese Aufgabe wurde bereits abgeschlossen.');
        }
        if ($step->assigned_to_user_id && $step->assigned_to_user_id === $user->id) return;
        if ($step->assigned_to_role_id && $user->roles->pluck('id')->contains($step->assigned_to_role_id)) return;
        abort(403);
    }
}
