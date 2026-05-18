<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Workflow-Templates: Export einer aktiven/abgespeicherten Version als JSON,
 * Import einer JSON-Datei (eigener Export oder mitgeliefertes Template),
 * Liste der vorgefertigten Cookbooks.
 *
 * Export-Format (v1):
 *   {
 *     "owe_workflow_template": 1,
 *     "name": "Rechnungseingang",
 *     "description": "...",
 *     "trigger_type": "manual",
 *     "definition": { ... Drawflow ... },
 *     "form_schema": [ ... ]
 *   }
 */
class TemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('workflows.templates.index', [
            'templates' => $this->builtInTemplates(),
        ]);
    }

    public function export(Workflow $workflow): StreamedResponse
    {
        $version = $workflow->currentVersion()->first();
        if (! $version) abort(404, 'Workflow hat keine gespeicherte Version.');

        $payload = [
            'owe_workflow_template' => 1,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'trigger_type' => $workflow->trigger_type,
            'definition' => $version->definition,
            'form_schema' => $version->form_schema,
            'exported_at' => now()->toIso8601String(),
            'exported_from_version' => $version->version_number,
        ];

        $filename = \Illuminate\Support\Str::slug($workflow->name).'-template.json';
        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(
            fn () => print($body),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function importShow(): View
    {
        return view('workflows.templates.import', [
            'templates' => $this->builtInTemplates(),
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', 'in:builtin,upload,paste'],
            'builtin' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:1024'],
            'json' => ['nullable', 'string', 'max:2000000'],
            'name_override' => ['nullable', 'string', 'max:255'],
        ]);

        $raw = match ($data['source']) {
            'builtin' => $this->loadBuiltinJson((string) ($data['builtin'] ?? '')),
            'upload' => $request->file('file')?->get() ?: '',
            'paste' => (string) ($data['json'] ?? ''),
        };
        $raw = trim($raw);
        if ($raw === '') {
            return back()->withErrors(['file' => 'Keine Vorlage angegeben.']);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return back()->withErrors(['file' => 'Ungueltiges JSON.']);
        }

        $v = Validator::make($payload, [
            'owe_workflow_template' => ['required', 'integer', 'in:1'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['required', 'in:form,manual,recurring'],
            'definition' => ['required', 'array'],
            'definition.drawflow' => ['required', 'array'],
            'form_schema' => ['nullable', 'array'],
        ]);
        if ($v->fails()) {
            return back()->withErrors($v->errors());
        }
        $payload = $v->validated();

        $name = trim((string) ($data['name_override'] ?? '')) ?: $payload['name'];

        $workflow = Workflow::create([
            'name' => $name,
            'description' => $payload['description'] ?? null,
            'trigger_type' => $payload['trigger_type'],
            'status' => Workflow::STATUS_DRAFT,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'definition' => $payload['definition'],
            'form_schema' => $payload['form_schema'] ?? [],
            'change_summary' => 'Import aus Vorlage',
            'created_by' => $request->user()->id,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        $this->audit->log('workflow.imported', $workflow, null, [
            'source' => $data['source'],
            'builtin' => $data['builtin'] ?? null,
            'workflow_id' => $workflow->id,
        ], "Workflow {$workflow->name} importiert");

        return redirect()->route('workflows.design', $workflow)
            ->with('status', 'Vorlage importiert — noch im Entwurf, bitte pruefen und aktivieren.');
    }

    /** @return array<int, array{slug:string,name:string,description:string,trigger_type:string,filename:string}> */
    private function builtInTemplates(): array
    {
        $dir = resource_path('templates/workflows');
        if (! is_dir($dir)) return [];

        $out = [];
        foreach (glob($dir.'/*.json') ?: [] as $file) {
            $json = json_decode((string) file_get_contents($file), true);
            if (! is_array($json) || ! isset($json['name'])) continue;
            $out[] = [
                'slug' => basename($file, '.json'),
                'name' => (string) $json['name'],
                'description' => (string) ($json['description'] ?? ''),
                'trigger_type' => (string) ($json['trigger_type'] ?? 'manual'),
                'filename' => basename($file),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $out;
    }

    private function loadBuiltinJson(string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: '';
        if ($slug === '') return '';
        $file = resource_path('templates/workflows/'.$slug.'.json');
        return is_file($file) ? (string) file_get_contents($file) : '';
    }
}
