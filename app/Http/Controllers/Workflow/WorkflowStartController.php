<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\Workflow;
use App\Services\FormSchemaValidator;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowStartController extends Controller
{
    public function __construct(
        private readonly FormSchemaValidator $validator,
        private readonly WorkflowEngine $engine,
    ) {}

    public function show(Workflow $workflow): View
    {
        abort_unless($workflow->status === Workflow::STATUS_ACTIVE, 404);
        $workflow->load('currentVersion');

        return view('workflows.start', [
            'workflow' => $workflow,
            'schema' => $workflow->currentVersion->form_schema ?? [],
        ]);
    }

    public function submit(Request $request, Workflow $workflow): RedirectResponse
    {
        abort_unless($workflow->status === Workflow::STATUS_ACTIVE, 404);
        $workflow->load('currentVersion');

        $schema = $workflow->currentVersion->form_schema ?? [];
        $clean = $this->validator->validateAgainstSchema($request->all(), $schema);

        $instance = $this->engine->start($workflow, $clean, $request->user());

        FormSubmission::create([
            'workflow_instance_id' => $instance->id,
            'submitted_by' => $request->user()->id,
            'data' => $clean,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return redirect()->route('tasks.index')->with('status', 'Antrag gestartet. Status siehst du im Verlauf.');
    }
}
