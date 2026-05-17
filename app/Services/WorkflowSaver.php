<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

class WorkflowSaver
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Persist a new immutable version. The workflow's `current_version_id`
     * always points at the latest version. Older versions stay around for
     * audit and rollback.
     */
    public function save(
        Workflow $workflow,
        array $definition,
        ?array $formSchema,
        ?string $changeSummary,
        int $userId,
    ): WorkflowVersion {
        return DB::transaction(function () use ($workflow, $definition, $formSchema, $changeSummary, $userId) {
            $next = ((int) $workflow->versions()->max('version_number')) + 1;

            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version_number' => $next,
                'definition' => $definition,
                'form_schema' => $formSchema,
                'change_summary' => $changeSummary,
                'created_by' => $userId,
            ]);

            $workflow->forceFill([
                'current_version_id' => $version->id,
                'updated_by' => $userId,
            ])->save();

            $this->audit->log(
                'workflow.version.saved',
                $workflow,
                null,
                [
                    'version' => $next,
                    'change_summary' => $changeSummary,
                    'nodes' => count($definition['drawflow']['Home']['data'] ?? []),
                ],
                "Workflow {$workflow->name}: Version {$next} gespeichert",
                $userId,
            );

            return $version;
        });
    }
}
