<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Services\WorkflowSaver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowDesignerController extends Controller
{
    public function __construct(private readonly WorkflowSaver $saver) {}

    public function show(Workflow $workflow): View
    {
        $workflow->load('currentVersion');

        $payload = [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'status' => $workflow->status,
                'trigger_type' => $workflow->trigger_type,
                'current_version_number' => $workflow->currentVersion?->version_number,
            ],
            'definition' => $workflow->currentVersion?->definition,
            'form_schema' => $workflow->currentVersion?->form_schema ?? [],
            'directory' => [
                'roles' => Role::orderBy('name')->get(['id', 'name', 'slug'])->all(),
                'users' => User::humans()->where('is_active', true)->orderBy('name')
                    ->limit(500)->get(['id', 'name', 'email'])->all(),
                'lists' => \App\Models\LookupList::orderBy('name')
                    ->get(['id', 'name', 'columns'])
                    ->map(fn ($l) => [
                        'id' => $l->id,
                        'name' => $l->name,
                        'has_responsible' => (bool) collect($l->columns)->firstWhere('role', 'responsible'),
                        'has_escalation' => (bool) collect($l->columns)->firstWhere('role', 'escalation'),
                    ])->all(),
                // Workflows-Liste fuer die Sub-Workflow- und Loop-Knoten.
                // Den aktuellen Workflow filtern wir aus — sonst koennte
                // jemand sich selbst aufrufen (Endlos-Rekursion).
                'workflows' => \App\Models\Workflow::where('id', '!=', $workflow->id)
                    ->where('status', \App\Models\Workflow::STATUS_ACTIVE)
                    ->orderBy('name')
                    ->get(['id', 'name', 'trigger_type'])->all(),
            ],
            'urls' => [
                'save' => route('workflows.designer.save', $workflow),
                'back' => route('workflows.index'),
                'versions' => route('workflows.versions', $workflow),
            ],
        ];

        return view('workflows.design', compact('workflow', 'payload'));
    }

    public function save(Request $request, Workflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'definition' => ['required', 'array'],
            'definition.drawflow' => ['required', 'array'],
            'form_schema' => ['nullable', 'array'],
            'form_schema.*.key' => ['required_with:form_schema', 'string', 'max:64'],
            'form_schema.*.label' => ['required_with:form_schema', 'string', 'max:255'],
            'form_schema.*.type' => ['required_with:form_schema', 'string', 'in:text,textarea,number,select,radio,checkbox,date,file'],
            'form_schema.*.required' => ['nullable', 'boolean'],
            'form_schema.*.options' => ['nullable', 'array'],
            'form_schema.*.show_if' => ['nullable', 'array'],
            'form_schema.*.show_if.field' => ['nullable', 'string', 'max:64'],
            'form_schema.*.show_if.operator' => ['nullable', 'string', 'in:eq,neq,contains,checked,unchecked,empty,not_empty'],
            'form_schema.*.show_if.value' => ['nullable', 'string', 'max:255'],
            'change_summary' => ['nullable', 'string', 'max:500'],
        ]);

        $version = $this->saver->save(
            $workflow,
            $data['definition'],
            $data['form_schema'] ?? null,
            $data['change_summary'] ?? null,
            $request->user()->id,
        );

        return response()->json([
            'ok' => true,
            'version_number' => $version->version_number,
            'saved_at' => $version->created_at->toIso8601String(),
        ]);
    }

    public function versions(Workflow $workflow): View
    {
        $versions = $workflow->versions()->with('creator')->paginate(20);
        return view('workflows.versions', compact('workflow', 'versions'));
    }

    public function versionsDiff(Request $request, Workflow $workflow): View
    {
        $a = $request->integer('a');
        $b = $request->integer('b');
        $versionsList = $workflow->versions()->orderByDesc('version_number')->get(['id', 'version_number', 'change_summary', 'created_at']);

        $verA = $a ? $workflow->versions()->where('id', $a)->first() : null;
        $verB = $b ? $workflow->versions()->where('id', $b)->first() : null;
        $diff = ($verA && $verB) ? app(\App\Services\WorkflowDiffer::class)->diff($verA, $verB) : null;

        return view('workflows.versions_diff', compact('workflow', 'versionsList', 'verA', 'verB', 'diff'));
    }

    public function restore(Request $request, Workflow $workflow, WorkflowVersion $version)
    {
        abort_unless($version->workflow_id === $workflow->id, 404);

        $this->saver->save(
            $workflow,
            $version->definition,
            $version->form_schema,
            "Wiederhergestellt aus Version {$version->version_number}",
            $request->user()->id,
        );

        return redirect()->route('workflows.design', $workflow)->with('status', "Version {$version->version_number} wiederhergestellt.");
    }
}
