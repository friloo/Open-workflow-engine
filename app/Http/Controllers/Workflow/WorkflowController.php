<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('q', ''));
        $status = $request->get('status');

        $workflows = Workflow::query()
            ->with(['currentVersion', 'creator', 'updater'])
            ->withCount('instances')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $ai = app(\App\Services\AIClient::class);
        return view('workflows.index', [
            'workflows' => $workflows,
            'search' => $search,
            'status' => $status,
            'aiConfigured' => $ai->isConfigured(),
            'aiProvider' => $ai->provider(),
        ]);
    }

    public function create(): View
    {
        return view('workflows.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_type' => ['required', 'in:form,manual,recurring'],
        ]);

        $workflow = Workflow::create([
            ...$data,
            'status' => Workflow::STATUS_DRAFT,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log('workflow.created', $workflow, null, $workflow->only([
            'id', 'name', 'slug', 'trigger_type',
        ]), "Workflow {$workflow->name} angelegt");

        return redirect()->route('workflows.design', $workflow);
    }

    public function edit(Workflow $workflow): View
    {
        return view('workflows.edit', compact('workflow'));
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_type' => ['required', 'in:form,manual,recurring'],
        ]);

        $original = $workflow->only(array_keys($data));

        $workflow->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log('workflow.updated', $workflow, $original, $workflow->only(array_keys($data)),
            "Workflow {$workflow->name} aktualisiert");

        return redirect()->route('workflows.index')->with('status', 'Workflow aktualisiert.');
    }

    public function activate(Request $request, Workflow $workflow): RedirectResponse
    {
        if (! $workflow->isPublishable()) {
            return back()->withErrors(['workflow' => 'Workflow hat noch keine gespeicherte Version.']);
        }

        $workflow->update([
            'status' => Workflow::STATUS_ACTIVE,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log('workflow.activated', $workflow, null, ['status' => 'active'],
            "Workflow {$workflow->name} aktiviert");

        return back()->with('status', 'Workflow aktiviert.');
    }

    public function archive(Request $request, Workflow $workflow): RedirectResponse
    {
        $workflow->update([
            'status' => Workflow::STATUS_ARCHIVED,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log('workflow.archived', $workflow, null, ['status' => 'archived'],
            "Workflow {$workflow->name} archiviert");

        return back()->with('status', 'Workflow archiviert.');
    }

    /**
     * Prozessbeschreibung als PDF — Knopfdruck-Variante. Nimmt die
     * aktuellste Version, baut HTML-Template, DomPDF macht PDF.
     */
    public function processDoc(Workflow $workflow, \App\Services\WorkflowProcessDocService $svc)
    {
        return $this->renderProcessDoc($workflow, null, $svc);
    }

    public function processDocVersion(Workflow $workflow, \App\Models\WorkflowVersion $version, \App\Services\WorkflowProcessDocService $svc)
    {
        if ($version->workflow_id !== $workflow->id) abort(404);
        return $this->renderProcessDoc($workflow, $version, $svc);
    }

    private function renderProcessDoc(Workflow $workflow, ?\App\Models\WorkflowVersion $version, \App\Services\WorkflowProcessDocService $svc)
    {
        try {
            $payload = $svc->buildPayload($workflow, $version);
        } catch (\Throwable $e) {
            return back()->withErrors(['process_doc' => $e->getMessage()]);
        }
        $this->audit->log('workflow.process_doc.printed', $workflow, null, [
            'version_id' => $payload['version']->id,
            'definition_hash' => $payload['definition_hash'],
        ], "Prozessbeschreibung gedruckt für {$workflow->name} v{$payload['version']->version_number}");

        $html = view('workflows.print.process-doc', $payload)->render();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4');
        $filename = 'Prozessbeschreibung-'
            .\Illuminate\Support\Str::slug($workflow->name, '_')
            .'-v'.$payload['version']->version_number
            .'-'.now()->format('Y-m-d').'.pdf';
        return $pdf->download($filename);
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $name = $workflow->name;
        $snapshot = $workflow->only(['id', 'name', 'slug']);
        $workflow->delete();

        $this->audit->log('workflow.deleted', $workflow, $snapshot, null,
            "Workflow {$name} gelöscht");

        return redirect()->route('workflows.index')->with('status', 'Workflow gelöscht.');
    }
}
