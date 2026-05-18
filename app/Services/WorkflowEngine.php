<?php

namespace App\Services;

use App\Mail\WorkflowNotificationMail;
use App\Mail\WorkflowTaskAssignedMail;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowEngine
{
    private const MAX_DEPTH = 100;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Start a new workflow instance. Walks the graph until it either
     * completes or pauses on a human task.
     */
    public function start(Workflow $workflow, array $formData, ?User $initiator): WorkflowInstance
    {
        if ($workflow->status !== Workflow::STATUS_ACTIVE || ! $workflow->current_version_id) {
            throw new \RuntimeException('Workflow ist nicht aktiv.');
        }

        $version = $workflow->currentVersion()->firstOrFail();

        return DB::transaction(function () use ($workflow, $version, $formData, $initiator) {
            $instance = WorkflowInstance::create([
                'workflow_id' => $workflow->id,
                'workflow_version_id' => $version->id,
                'started_by' => $initiator?->id,
                'status' => WorkflowInstance::STATUS_RUNNING,
                'data' => $formData,
                'started_at' => now(),
            ]);

            $this->audit->log('workflow.instance.started', $instance, null, [
                'workflow' => $workflow->name,
                'initiator' => $initiator?->email,
            ], "Workflow {$workflow->name} gestartet", $initiator?->id);

            try {
                $start = $this->findStartNode($version);
                if ($start) {
                    $this->run($instance, (string) $start['id']);
                }
                $instance->refresh();
            } catch (\Throwable $e) {
                Log::error('Workflow engine failed', ['workflow' => $workflow->id, 'instance' => $instance->id, 'error' => $e->getMessage()]);
                $instance->update(['status' => WorkflowInstance::STATUS_FAILED]);
            }

            return $instance;
        });
    }

    /**
     * Resume from a given node. Used both from start() and after a human
     * action has completed a step.
     */
    public function run(WorkflowInstance $instance, string $startNodeId, int $depth = 0): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \RuntimeException('Workflow-Tiefe ueberschritten — moeglicher Endlos-Loop.');
        }

        $version = $instance->version()->firstOrFail();
        $nodes = $version->definition['drawflow']['Home']['data'] ?? [];
        $node = $nodes[$startNodeId] ?? null;
        if (! $node) return;

        $instance->update(['current_step_key' => (string) $startNodeId]);

        switch ($node['class'] ?? null) {
            case 'start':
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->run($instance, $next, $depth + 1);
                break;

            case 'end':
                $result = $node['data']['result'] ?? 'completed';
                $status = match ($result) {
                    'rejected' => WorkflowInstance::STATUS_COMPLETED,
                    'cancelled' => WorkflowInstance::STATUS_CANCELLED,
                    default => WorkflowInstance::STATUS_COMPLETED,
                };
                $instance->update(['status' => $status, 'completed_at' => now(), 'current_step_key' => null]);
                $this->audit->log('workflow.instance.completed', $instance, null, ['result' => $result],
                    "Workflow-Instanz #{$instance->id} beendet ({$result})");
                break;

            case 'condition':
                $branchIdx = $this->evaluateCondition($node['data'] ?? [], $instance->data ?? []);
                $branches = $node['data']['branches'] ?? [];
                $outputKey = $branchIdx !== null
                    ? 'output_'.($branchIdx + 1)
                    : 'output_'.(count($branches) + 1);
                $next = $this->firstTarget($node, $outputKey);
                if ($next) {
                    $this->run($instance, $next, $depth + 1);
                } else {
                    $instance->update(['status' => WorkflowInstance::STATUS_COMPLETED, 'completed_at' => now()]);
                }
                break;

            case 'notify':
                $this->sendNotification($instance, $node);
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->run($instance, $next, $depth + 1);
                break;

            case 'approval':
                $this->createApprovalTask($instance, $node);
                // Pause here — task completion will resume via completeStep().
                return;

            default:
                Log::warning('Unbekannter Knotentyp', ['class' => $node['class'] ?? null, 'instance' => $instance->id]);
        }
    }

    /**
     * Human action on an approval step: mark completed and follow the
     * matching output edge.
     *
     *   decision => approved | rejected | forwarded
     */
    public function completeStep(WorkflowStepExecution $step, string $decision, ?string $comment, ?int $userId): void
    {
        if ($step->completed_at) {
            throw new \RuntimeException('Aufgabe wurde bereits abgeschlossen.');
        }

        $step->update([
            'completed_at' => now(),
            'completed_by' => $userId,
            'decision' => $decision,
            'comment' => $comment,
        ]);

        $instance = $step->instance()->firstOrFail();
        $version = $instance->version()->firstOrFail();
        $node = $version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        if (! $node) {
            $instance->update(['status' => WorkflowInstance::STATUS_FAILED]);
            return;
        }

        $outputKey = match ($decision) {
            'approved' => 'output_1',
            'rejected' => 'output_2',
            'forwarded' => 'output_3',
            default => 'output_1',
        };

        $this->audit->log('workflow.step.completed', $step, null, [
            'decision' => $decision,
            'comment' => $comment,
            'instance_id' => $instance->id,
        ], "Schritt {$node['data']['label']} ({$decision})", $userId);

        $next = $this->firstTarget($node, $outputKey);
        if ($next) {
            $this->run($instance, $next);
        } else {
            // No edge from this output: treat as termination.
            $instance->update([
                'status' => $decision === 'rejected' ? WorkflowInstance::STATUS_COMPLETED : WorkflowInstance::STATUS_COMPLETED,
                'completed_at' => now(),
                'current_step_key' => null,
            ]);
        }
    }

    /**
     * Forward an open task to a different user. The original step stays
     * open under the original assignee's history but is closed with a
     * "forwarded" decision; a new step is created.
     */
    public function forwardStep(WorkflowStepExecution $step, User $newAssignee, ?string $comment, int $byUserId): WorkflowStepExecution
    {
        if ($step->completed_at) {
            throw new \RuntimeException('Aufgabe wurde bereits abgeschlossen.');
        }

        $step->update([
            'completed_at' => now(),
            'completed_by' => $byUserId,
            'decision' => 'forwarded',
            'comment' => $comment,
        ]);

        $newStep = WorkflowStepExecution::create([
            'workflow_instance_id' => $step->workflow_instance_id,
            'step_key' => $step->step_key,
            'step_type' => $step->step_type,
            'assigned_to_user_id' => $newAssignee->id,
            'assigned_at' => now(),
            'due_at' => $step->due_at,
            'escalated_from_step_id' => $step->id,
        ]);

        $this->audit->log('workflow.step.forwarded', $newStep, null, [
            'from_user' => $step->assignedUser?->email,
            'to_user' => $newAssignee->email,
        ], "Aufgabe weitergeleitet an {$newAssignee->email}", $byUserId);

        $this->notifyAssignee($newStep);

        return $newStep;
    }

    /**
     * Escalate a step whose grace period elapsed. Creates a new task
     * for the configured escalation target and closes the original step
     * with decision "escalated".
     */
    public function escalateOverdueStep(WorkflowStepExecution $step): ?WorkflowStepExecution
    {
        if ($step->completed_at) return null;

        $instance = $step->instance()->firstOrFail();
        $version = $instance->version()->firstOrFail();
        $node = $version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        if (! $node) return null;

        $data = $node['data'] ?? [];
        $target = $this->resolveEscalationTarget($data, $step, $instance);
        if (! $target) {
            $this->audit->log('workflow.step.escalation_skipped', $step, null, null,
                'Karenzzeit ueberschritten, aber kein Eskalationsziel konfiguriert');
            $step->update(['due_at' => null]); // Don't escalate again.
            return null;
        }

        $step->update([
            'completed_at' => now(),
            'decision' => 'escalated',
            'comment' => 'Karenzzeit ueberschritten — automatisch eskaliert.',
        ]);

        $newStep = WorkflowStepExecution::create([
            'workflow_instance_id' => $step->workflow_instance_id,
            'step_key' => $step->step_key,
            'step_type' => 'approval',
            'assigned_to_user_id' => $target['user_id'] ?? null,
            'assigned_to_role_id' => $target['role_id'] ?? null,
            'assigned_at' => now(),
            'due_at' => $this->graceDeadline($data),
            'escalated_from_step_id' => $step->id,
        ]);

        $this->audit->log('workflow.step.escalated', $newStep, null, [
            'instance_id' => $instance->id,
            'from_step_id' => $step->id,
            'target' => $target,
        ], 'Aufgabe eskaliert wegen Karenzzeit-Ueberschreitung');

        $this->notifyAssignee($newStep);
        return $newStep;
    }

    // -- internals -----------------------------------------------------------

    private function findStartNode(WorkflowVersion $version): ?array
    {
        $nodes = $version->definition['drawflow']['Home']['data'] ?? [];
        foreach ($nodes as $node) {
            if (($node['class'] ?? null) === 'start') return $node;
        }
        return null;
    }

    private function firstTarget(array $node, string $outputKey): ?string
    {
        $conn = $node['outputs'][$outputKey]['connections'][0] ?? null;
        return $conn ? (string) $conn['node'] : null;
    }

    private function evaluateCondition(array $data, array $form): ?int
    {
        foreach ($data['branches'] ?? [] as $idx => $branch) {
            $value = $form[$branch['field'] ?? ''] ?? null;
            $expect = $branch['value'] ?? null;
            $op = $branch['operator'] ?? 'eq';

            $matches = match ($op) {
                'eq' => (string) $value === (string) $expect,
                'neq' => (string) $value !== (string) $expect,
                'contains' => is_string($value) && str_contains((string) $value, (string) $expect),
                'gt' => is_numeric($value) && is_numeric($expect) && $value > $expect,
                'gte' => is_numeric($value) && is_numeric($expect) && $value >= $expect,
                'lt' => is_numeric($value) && is_numeric($expect) && $value < $expect,
                'lte' => is_numeric($value) && is_numeric($expect) && $value <= $expect,
                'checked' => $value === true || $value === 1 || $value === '1',
                'unchecked' => $value === false || $value === 0 || $value === '0' || $value === null,
                'empty' => $value === null || $value === '' || $value === [],
                'not_empty' => ! ($value === null || $value === '' || $value === []),
                default => false,
            };
            if ($matches) return $idx;
        }
        return null; // -> else branch
    }

    private function sendNotification(WorkflowInstance $instance, array $node): void
    {
        $data = $node['data'] ?? [];
        $recipients = $this->resolveRecipients($data, $instance);
        if (! $recipients) return;

        $form = $instance->data ?? [];
        $initiator = $instance->starter()->first();
        $context = $form + [
            'initiator' => $initiator?->name ?? 'Unbekannt',
        ];

        $subject = $this->renderTemplate($data['subject'] ?? 'Workflow-Aktualisierung', $context);
        $body = $this->renderTemplate($data['body'] ?? '', $context);

        foreach ($recipients as $user) {
            if (! $user->email_notifications_enabled) continue;
            try {
                Mail::to($user->email)->send(new WorkflowNotificationMail($subject, $body, $instance, $user));
            } catch (\Throwable $e) {
                Log::warning('Notification mail failed', ['to' => $user->email, 'error' => $e->getMessage()]);
            }
        }
    }

    private function createApprovalTask(WorkflowInstance $instance, array $node): void
    {
        $data = $node['data'] ?? [];
        $target = $this->resolveAssignee($data, $instance);

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => (string) $node['id'],
            'step_type' => 'approval',
            'assigned_to_user_id' => $target['user_id'] ?? null,
            'assigned_to_role_id' => $target['role_id'] ?? null,
            'assigned_at' => now(),
            'due_at' => $this->graceDeadline($data),
        ]);

        $this->notifyAssignee($step);
    }

    private function notifyAssignee(WorkflowStepExecution $step): void
    {
        $recipients = $this->stepRecipients($step);
        foreach ($recipients as $user) {
            if (! $user->email_notifications_enabled) continue;
            try {
                Mail::to($user->email)->send(new WorkflowTaskAssignedMail($step, $user));
            } catch (\Throwable $e) {
                Log::warning('Task mail failed', ['to' => $user->email, 'error' => $e->getMessage()]);
            }
        }
    }

    /** @return \Illuminate\Support\Collection<User> */
    private function stepRecipients(WorkflowStepExecution $step): \Illuminate\Support\Collection
    {
        if ($step->assigned_to_user_id) {
            $u = User::find($step->assigned_to_user_id);
            return $u ? collect([$u]) : collect();
        }
        if ($step->assigned_to_role_id) {
            $role = \App\Models\Role::with('users')->find($step->assigned_to_role_id);
            return $role ? $role->users : collect();
        }
        return collect();
    }

    /** @return array{user_id?: int, role_id?: int} */
    private function resolveAssignee(array $data, WorkflowInstance $instance): array
    {
        $type = $data['recipient_type'] ?? 'supervisor_of_initiator';
        return match ($type) {
            'user' => ['user_id' => $data['recipient_user_id'] ?? null],
            'role' => ['role_id' => $data['recipient_role_id'] ?? null],
            'supervisor_of_initiator' => [
                'user_id' => $instance->starter()->first()?->effectiveSupervisor()?->id,
            ],
            'supervisor_of_previous' => [
                'user_id' => $this->previousSupervisor($instance)?->id,
            ],
            default => [],
        };
    }

    private function previousSupervisor(WorkflowInstance $instance): ?User
    {
        $last = $instance->stepExecutions()
            ->whereNotNull('completed_by')
            ->orderByDesc('id')->first();
        $user = $last?->completedBy()->first();
        return $user?->effectiveSupervisor();
    }

    /** @return array{user_id?: int, role_id?: int}|null */
    private function resolveEscalationTarget(array $data, WorkflowStepExecution $step, WorkflowInstance $instance): ?array
    {
        $type = $data['escalation_type'] ?? 'none';
        if ($type === 'none') return null;

        if ($type === 'role') {
            $roleId = $data['escalation_role_id'] ?? null;
            return $roleId ? ['role_id' => (int) $roleId] : null;
        }

        if ($type === 'supervisor_of_current') {
            $assignee = $step->assignedUser()->first();
            $sup = $assignee?->effectiveSupervisor();
            return $sup ? ['user_id' => $sup->id] : null;
        }
        return null;
    }

    /** @return array<User> */
    private function resolveRecipients(array $data, WorkflowInstance $instance): array
    {
        $type = $data['recipient_type'] ?? 'initiator';
        return match ($type) {
            'initiator' => array_filter([$instance->starter()->first()]),
            'supervisor_of_initiator' => array_filter([
                $instance->starter()->first()?->effectiveSupervisor(),
            ]),
            'role' => \App\Models\Role::find($data['recipient_role_id'] ?? 0)
                ?->users?->all() ?? [],
            'user' => array_filter([User::find($data['recipient_user_id'] ?? 0)]),
            default => [],
        };
    }

    private function graceDeadline(array $data): ?\Carbon\CarbonInterface
    {
        $value = (int) ($data['grace_value'] ?? 0);
        if ($value <= 0) return null;
        $unit = $data['grace_unit'] ?? 'days';
        return match ($unit) {
            'hours' => now()->addHours($value),
            'days' => now()->addDays($value),
            'months' => now()->addMonths($value),
            default => now()->addDays($value),
        };
    }

    private function renderTemplate(string $tpl, array $ctx): string
    {
        return preg_replace_callback('/\{\{\s*([\w_]+)\s*\}\}/', function ($m) use ($ctx) {
            $v = $ctx[$m[1]] ?? '';
            if (is_bool($v)) return $v ? 'ja' : 'nein';
            if (is_array($v)) return implode(', ', $v);
            return (string) $v;
        }, $tpl);
    }
}
