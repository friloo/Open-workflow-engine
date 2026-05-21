<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceComment;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowInstanceController extends Controller
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function indexAll(Request $request): View
    {
        $user = $request->user();
        $canSeeAll = $user->hasAnyPermission(['workflows.view', 'workflows.design', 'audit.view']);

        $query = WorkflowInstance::query()
            ->with(['workflow', 'starter'])
            ->when(! $canSeeAll, fn ($q) => $q->where('started_by', $user->id))
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->get('workflow_id'), fn ($q, $w) => $q->where('workflow_id', $w))
            ->when($request->get('q'), function ($q, $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->whereHas('workflow', fn ($w) => $w->where('name', 'like', "%{$term}%"))
                       ->orWhere('data', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id');

        return view('workflows.instances.index', [
            'instances' => $query->paginate(25)->withQueryString(),
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
            'status' => $request->get('status'),
            'search' => $request->get('q', ''),
            'workflowId' => $request->get('workflow_id'),
            'canSeeAll' => $canSeeAll,
        ]);
    }

    public function indexForWorkflow(Workflow $workflow, Request $request): View
    {
        $instances = $workflow->instances()
            ->with(['starter'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('workflows.instances.index', [
            'instances' => $instances,
            'workflow' => $workflow,
            'workflows' => collect(),
            'status' => $request->get('status'),
            'search' => '',
            'workflowId' => $workflow->id,
            'canSeeAll' => true,
        ]);
    }

    public function show(WorkflowInstance $instance, Request $request): View
    {
        $user = $request->user();
        $canSeeAll = $user->hasAnyPermission(['workflows.view', 'workflows.design', 'audit.view']);
        if (! $canSeeAll && $instance->started_by !== $user->id) {
            abort(403);
        }

        $instance->load([
            'workflow', 'version', 'starter', 'attachments',
            'comments.user',
            'stepExecutions.assignedUser',
            'stepExecutions.assignedRole',
            'stepExecutions.completedBy',
        ]);

        $definition = $instance->version?->definition ?? ['drawflow' => ['Home' => ['data' => []]]];
        $completedKeys = $instance->stepExecutions
            ->whereNotNull('completed_at')
            ->pluck('step_key')->unique()->values()->all();

        $viewerPayload = [
            'definition' => $definition,
            'completed_step_keys' => $completedKeys,
            'current_step_key' => $instance->current_step_key,
            'status' => $instance->status,
        ];

        $canCancel = $instance->status === WorkflowInstance::STATUS_RUNNING
            && $user->hasAnyPermission(['workflows.design']);

        return view('workflows.instances.show', compact('instance', 'viewerPayload', 'canCancel'));
    }

    public function cancel(Request $request, WorkflowInstance $instance): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasAnyPermission(['workflows.design'])) {
            abort(403);
        }

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        try {
            $this->engine->cancelInstance($instance, $reason, $user->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('workflow-instances.show', $instance)
            ->with('status', 'Workflow-Instanz abgebrochen.');
    }

    public function bulkCancel(Request $request): RedirectResponse
    {
        if (! $request->user()->hasAnyPermission(['workflows.design'])) abort(403);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $instances = WorkflowInstance::whereIn('id', $data['ids'])
            ->where('status', WorkflowInstance::STATUS_RUNNING)->get();
        $count = 0;
        foreach ($instances as $i) {
            try {
                $this->engine->cancelInstance($i, $data['reason'] ?? 'Bulk-Abbruch', $request->user()->id);
                $count++;
            } catch (\Throwable) {}
        }
        return back()->with('status', "{$count} Instanzen abgebrochen.");
    }

    public function comment(Request $request, WorkflowInstance $instance): RedirectResponse
    {
        $user = $request->user();
        $canSeeAll = $user->hasAnyPermission(['workflows.view', 'workflows.design', 'audit.view']);
        $isParticipant = $instance->started_by === $user->id ||
            $instance->stepExecutions()->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                  ->orWhereIn('assigned_to_role_id', $user->roles->pluck('id'))
                  ->orWhere('completed_by', $user->id);
            })->exists();
        if (! $canSeeAll && ! $isParticipant) abort(403);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        WorkflowInstanceComment::create([
            'workflow_instance_id' => $instance->id,
            'user_id' => $user->id,
            'body' => $data['body'],
        ]);
        return back()->with('status', 'Kommentar hinzugefügt.');
    }
}
