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

class PublicFormController extends Controller
{
    public function __construct(
        private readonly FormSchemaValidator $validator,
        private readonly WorkflowEngine $engine,
        private readonly AttachmentStorage $attachments,
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

        $input = array_merge($request->all(), $request->allFiles());
        $clean = $this->validator->validateAgainstSchema($input, $schema);

        $instance = $this->engine->start($workflow, $clean, null);

        $submission = FormSubmission::create([
            'form_id' => null,
            'workflow_instance_id' => $instance->id,
            'submitted_by' => null,
            'data' => $clean,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        // Datei-Felder als Attachments an die Instanz haengen.
        foreach ($this->validator->fileFields($schema) as $field) {
            if ($request->hasFile($field['key'])) {
                try {
                    $this->attachments->store(
                        $request->file($field['key']),
                        $instance,
                        $field['label'] ?? $field['key'],
                        null,
                    );
                } catch (\Throwable) {
                    // best-effort: Workflow laeuft trotzdem weiter
                }
            }
        }

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
