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

class PublicFormController extends Controller
{
    public function __construct(
        private readonly FormSchemaValidator $validator,
        private readonly WorkflowEngine $engine,
    ) {}

    public function show(string $slug): View
    {
        $workflow = $this->lookup($slug);

        return view('public.form', [
            'workflow' => $workflow,
            'schema' => $workflow->currentVersion->form_schema ?? [],
        ]);
    }

    public function submit(Request $request, string $slug): RedirectResponse
    {
        $workflow = $this->lookup($slug);
        $schema = $workflow->currentVersion->form_schema ?? [];

        $clean = $this->validator->validateAgainstSchema($request->all(), $schema);

        $instance = $this->engine->start($workflow, $clean, null);

        FormSubmission::create([
            'form_id' => null,
            'workflow_instance_id' => $instance->id,
            'submitted_by' => null,
            'data' => $clean,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return redirect()->route('public.form.thanks', $slug);
    }

    public function thanks(string $slug): View
    {
        $workflow = $this->lookup($slug);
        return view('public.thanks', compact('workflow'));
    }

    private function lookup(string $slug): Workflow
    {
        $workflow = Workflow::where('public_slug', $slug)
            ->where('is_public', true)
            ->where('status', Workflow::STATUS_ACTIVE)
            ->with('currentVersion')
            ->firstOrFail();

        if (! $workflow->currentVersion) {
            abort(404);
        }
        return $workflow;
    }
}
