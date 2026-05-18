<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\Workflow;
use App\Services\AttachmentStorage;
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
        private readonly AttachmentStorage $attachments,
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
        $input = array_merge($request->all(), $request->allFiles());
        $clean = $this->validator->validateAgainstSchema($input, $schema);

        $instance = $this->engine->start($workflow, $clean, $request->user());

        FormSubmission::create([
            'workflow_instance_id' => $instance->id,
            'submitted_by' => $request->user()->id,
            'data' => $clean,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        foreach ($this->validator->fileFields($schema) as $field) {
            if ($request->hasFile($field['key'])) {
                $this->attachments->store(
                    $request->file($field['key']),
                    $instance,
                    $field['label'] ?? $field['key'],
                    $request->user()->id,
                );
            }
        }

        return redirect()->route('tasks.index')->with('status', 'Antrag gestartet. Status siehst du im Verlauf.');
    }
}
