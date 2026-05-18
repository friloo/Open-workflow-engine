<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Workflow;
use App\Services\AuditLogger;
use App\Services\FormSchemaValidator;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FormController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FormSchemaValidator $validator,
        private readonly WorkflowEngine $engine,
    ) {}

    public function index(): View
    {
        $forms = Form::with('workflow', 'creator')
            ->withCount('submissions')
            ->orderByDesc('updated_at')
            ->paginate(20);
        return view('forms.index', compact('forms'));
    }

    public function create(): View
    {
        return view('forms.edit', [
            'form' => new Form(['schema' => []]),
            'workflows' => Workflow::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateForm($request);
        $form = Form::create([
            ...$data,
            'is_public' => $request->boolean('is_public'),
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('form.created', $form, null, $form->only(['id', 'name', 'slug']),
            "Formular {$form->name} angelegt");
        return redirect()->route('forms.edit', $form)->with('status', 'Formular angelegt.');
    }

    public function edit(Form $form): View
    {
        return view('forms.edit', [
            'form' => $form,
            'workflows' => Workflow::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $data = $this->validateForm($request, $form);
        $original = $form->only(array_keys($data));
        $form->update([
            ...$data,
            'is_public' => $request->boolean('is_public'),
        ]);
        $this->audit->log('form.updated', $form, $original, $form->only(array_keys($data)),
            "Formular {$form->name} aktualisiert");
        return back()->with('status', 'Formular gespeichert.');
    }

    public function destroy(Form $form): RedirectResponse
    {
        $snapshot = $form->only(['id', 'name', 'slug']);
        $form->delete();
        $this->audit->log('form.deleted', null, $snapshot, null, "Formular {$snapshot['name']} geloescht");
        return redirect()->route('forms.index')->with('status', 'Formular geloescht.');
    }

    // -- public --

    public function showPublic(string $slug): View
    {
        $form = $this->lookupPublic($slug);
        return view('public.form', [
            'workflow' => (object) ['name' => $form->name, 'description' => $form->description, 'public_slug' => $form->public_slug],
            'schema' => $form->schema ?? [],
            'isStandaloneForm' => true,
            'submitUrl' => route('forms.public.submit', $slug),
        ]);
    }

    public function submitPublic(Request $request, string $slug): RedirectResponse
    {
        $form = $this->lookupPublic($slug);
        $clean = $this->validator->validateAgainstSchema($request->all(), $form->schema ?? []);

        $instanceId = null;
        if ($form->workflow_id && $form->workflow && $form->workflow->status === Workflow::STATUS_ACTIVE) {
            $instance = $this->engine->start($form->workflow, $clean, null);
            $instanceId = $instance->id;
        }

        FormSubmission::create([
            'form_id' => $form->id,
            'workflow_instance_id' => $instanceId,
            'data' => $clean,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return redirect()->route('forms.public.thanks', $slug);
    }

    public function thanksPublic(string $slug): View
    {
        $form = $this->lookupPublic($slug);
        return view('public.thanks', [
            'workflow' => (object) ['name' => $form->name, 'public_slug' => $form->public_slug, 'standalone' => true],
        ]);
    }

    private function lookupPublic(string $slug): Form
    {
        return Form::where('public_slug', $slug)->where('is_public', true)->firstOrFail();
    }

    private function validateForm(Request $request, ?Form $form = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')->whereNull('deleted_at')],
            'is_public' => ['nullable', 'boolean'],
            'public_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', Rule::unique('forms', 'public_slug')->ignore($form?->id)->whereNull('deleted_at')],
            'schema' => ['nullable', 'array'],
            'schema.*.key' => ['required_with:schema', 'string', 'max:64'],
            'schema.*.label' => ['required_with:schema', 'string', 'max:255'],
            'schema.*.type' => ['required_with:schema', 'string', 'in:text,textarea,number,select,radio,checkbox,date'],
            'schema.*.required' => ['nullable', 'boolean'],
            'schema.*.options' => ['nullable', 'array'],
        ];
        $data = $request->validate($rules);

        // Normalize schema booleans
        if (isset($data['schema'])) {
            $data['schema'] = array_map(function ($f) {
                $f['required'] = (bool) ($f['required'] ?? false);
                $f['options'] = array_values(array_filter((array) ($f['options'] ?? []), fn ($v) => $v !== ''));
                return $f;
            }, $data['schema']);
        } else {
            $data['schema'] = [];
        }

        return $data;
    }
}
