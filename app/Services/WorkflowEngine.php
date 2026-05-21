<?php

namespace App\Services;

use App\Mail\WorkflowNotificationMail;
use App\Mail\WorkflowTaskAssignedMail;
use App\Services\ApprovalStampService;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowEngine
{
    private const MAX_DEPTH = 100;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * ApprovalStampService wird über den Container aufgelöst, damit
     * Tests einen Fake registrieren koennen (statt PDF wirklich zu rendern).
     */
    private function approvalStamper(): ApprovalStampService
    {
        return app(ApprovalStampService::class);
    }

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

            $this->dispatchWebhook(Webhook::EVENT_INSTANCE_STARTED, $instance);

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
            throw new \RuntimeException('Workflow-Tiefe überschritten — möglicher Endlos-Loop.');
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
                $this->dispatchWebhook(Webhook::EVENT_INSTANCE_COMPLETED, $instance);
                // Sub-Workflow: Parent aufwecken
                if ($instance->parent_step_execution_id) {
                    $this->finishChild($instance);
                }
                break;

            case 'http':
                $ok = $this->executeHttpNode($instance, $node);
                $next = $this->firstTarget($node, $ok ? 'output_1' : 'output_2');
                if ($next) {
                    $this->run($instance, $next, $depth + 1);
                } elseif (! $ok && empty($node['data']['continue_on_error'])) {
                    $instance->update(['status' => WorkflowInstance::STATUS_FAILED, 'completed_at' => now()]);
                    $this->dispatchWebhook(Webhook::EVENT_INSTANCE_FAILED, $instance);
                }
                break;

            case 'pdf_render':
                $this->renderPdfNode($instance, $node);
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->run($instance, $next, $depth + 1);
                break;

            case 'condition':
                $branchIdx = $this->evaluateCondition($node['data'] ?? [], $this->buildContext($instance));
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

            case 'wait':
                $this->createWaitStep($instance, $node);
                // Pausiert bis due_at — workflow:check-due weckt auf.
                return;

            case 'subworkflow':
                $this->startSubworkflow($instance, $node);
                // Pausiert bis Child-Instance completed — wake-up via finishChild().
                return;

            case 'loop':
                $this->startForEachLoop($instance, $node);
                // Pausiert bis alle Child-Instances completed.
                return;

            case 'switch_node':
                $branchIdx = $this->evaluateSwitch($node['data'] ?? [], $this->buildContext($instance));
                $cases = $node['data']['cases'] ?? [];
                $outputKey = $branchIdx !== null
                    ? 'output_'.($branchIdx + 1)
                    : 'output_'.(count($cases) + 1); // default = letzter Ausgang
                $next = $this->firstTarget($node, $outputKey);
                if ($next) {
                    $this->run($instance, $next, $depth + 1);
                } else {
                    $instance->update(['status' => WorkflowInstance::STATUS_COMPLETED, 'completed_at' => now()]);
                }
                break;

            case 'aggregator':
                $this->runAggregator($instance, $node);
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->run($instance, $next, $depth + 1);
                break;

            case 'set_field':
                $this->setFieldsNode($instance, $node);
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->run($instance, $next, $depth + 1);
                break;

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

        DB::transaction(function () use ($step, $decision, $comment, $userId) {
            $step->update([
                'completed_at' => now(),
                'completed_by' => $userId,
                'decision' => $decision,
                'comment' => $comment,
            ]);
            $this->continueAfterStep($step, $decision, $comment, $userId);
        });
    }

    private function continueAfterStep(WorkflowStepExecution $step, string $decision, ?string $comment, ?int $userId): void
    {

        $instance = $step->instance()->firstOrFail();
        $version = $instance->version()->firstOrFail();
        $node = $version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        if (! $node) {
            $instance->update(['status' => WorkflowInstance::STATUS_FAILED]);
            return;
        }

        // Quorum: wenn mehrere Geschwister für denselben Step existieren,
        // erst bei erfülltem Quorum weitergehen. Logging ist immer.
        $this->audit->log('workflow.step.completed', $step, null, [
            'decision' => $decision,
            'comment' => $comment,
            'instance_id' => $instance->id,
        ], "Schritt {$node['data']['label']} ({$decision})", $userId);

        $quorum = $this->resolveQuorumDecision($step, $instance, $decision);
        if ($quorum === null) {
            // Noch unentschieden -> warten auf weitere Stimmen
            return;
        }
        $decision = $quorum;

        // Auto-Stempel: PDF-Anhänge der Instance mit Approval-Stempel
        // bedrucken, falls am Knoten konfiguriert (data.stamp_pdf=true).
        if ($step->step_type === 'approval' && in_array($decision, ['approved', 'rejected'], true)) {
            try {
                $this->approvalStamper()->maybeStamp($step, $decision);
            } catch (\Throwable $e) {
                Log::warning('approval stamping crashed', ['step_id' => $step->id, 'error' => $e->getMessage()]);
            }
        }

        $outputKey = match ($decision) {
            'approved' => 'output_1',
            'rejected' => 'output_2',
            'forwarded' => 'output_3',
            default => 'output_1',
        };

        $this->dispatchWebhook(Webhook::EVENT_STEP_COMPLETED, $instance, ['step' => $step, 'decision' => $decision]);

        $next = $this->firstTarget($node, $outputKey);
        if ($next) {
            $this->run($instance, $next);
        } else {
            $instance->update([
                'status' => WorkflowInstance::STATUS_COMPLETED,
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
                'Karenzzeit überschritten, aber kein Eskalationsziel konfiguriert');
            $step->update(['due_at' => null]); // Don't escalate again.
            return null;
        }

        $step->update([
            'completed_at' => now(),
            'decision' => 'escalated',
            'comment' => 'Karenzzeit überschritten — automatisch eskaliert.',
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
        ], 'Aufgabe eskaliert wegen Karenzzeit-Überschreitung');

        $this->notifyAssignee($newStep);
        return $newStep;
    }

    /**
     * Bricht eine laufende Instanz ab. Alle offenen Schritte werden mit
     * decision="cancelled" geschlossen. Audit-Eintrag wird geschrieben.
     */
    public function cancelInstance(WorkflowInstance $instance, ?string $reason, int $byUserId): void
    {
        if (in_array($instance->status, [
            WorkflowInstance::STATUS_COMPLETED,
            WorkflowInstance::STATUS_CANCELLED,
            WorkflowInstance::STATUS_FAILED,
        ], true)) {
            throw new \RuntimeException('Instanz ist bereits abgeschlossen.');
        }

        DB::transaction(function () use ($instance, $reason, $byUserId) {
            $instance->stepExecutions()
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                    'completed_by' => $byUserId,
                    'decision' => 'cancelled',
                    'comment' => 'Workflow abgebrochen'.($reason ? ': '.$reason : ''),
                ]);

            $instance->update([
                'status' => WorkflowInstance::STATUS_CANCELLED,
                'completed_at' => now(),
                'current_step_key' => null,
            ]);

            $this->audit->log(
                'workflow.instance.cancelled',
                $instance,
                null,
                ['reason' => $reason],
                "Workflow-Instanz #{$instance->id} abgebrochen",
                $byUserId,
            );
            $this->dispatchWebhook(Webhook::EVENT_INSTANCE_CANCELLED, $instance, ['reason' => $reason]);
        });
    }

    /**
     * Fuehrt einen HTTP-Knoten aus: rendert URL/Headers/Body anhand der
     * Instanz-Daten und persistiert ausgewählte Response-Felder zurück.
     */
    private function executeHttpNode(WorkflowInstance $instance, array $node): bool
    {
        $d = $node['data'] ?? [];
        $context = $this->buildContext($instance);
        $method = strtoupper($d['method'] ?? 'POST');
        $url = $this->renderTemplate((string) ($d['url'] ?? ''), $context);
        if ($url === '') {
            $this->audit->log('workflow.http.failed', $instance, null, ['error' => 'empty url'], 'HTTP-Knoten ohne URL');
            return false;
        }

        // SSRF-Schutz: keine internen IPs, keine file:// und Co.
        try {
            \App\Support\SafeHttpUrl::assertSafe($url);
        } catch (\Throwable $e) {
            $this->audit->log('workflow.http.blocked', $instance, null, [
                'url' => \App\Support\SafeHttpUrl::redactForLog($url),
                'reason' => $e->getMessage(),
            ], "HTTP-Knoten blockiert: {$e->getMessage()}");
            return false;
        }

        $headers = [];
        foreach (($d['headers'] ?? []) as $h) {
            $k = trim((string) ($h['key'] ?? ''));
            if ($k === '') continue;
            $headers[$k] = $this->renderTemplate((string) ($h['value'] ?? ''), $context);
        }

        $authType = $d['auth_type'] ?? 'none';
        if ($authType === 'bearer') {
            $headers['Authorization'] = 'Bearer '.$this->renderTemplate((string) ($d['auth_token'] ?? ''), $context);
        } elseif ($authType === 'basic') {
            $user = $this->renderTemplate((string) ($d['auth_username'] ?? ''), $context);
            $pass = $this->renderTemplate((string) ($d['auth_password'] ?? ''), $context);
            $headers['Authorization'] = 'Basic '.base64_encode($user.':'.$pass);
        } elseif ($authType === 'api_key_header') {
            $headers[$d['auth_header_name'] ?? 'X-API-Key'] = $this->renderTemplate((string) ($d['auth_token'] ?? ''), $context);
        }

        $timeout = max(1, (int) ($d['timeout_seconds'] ?? 30));
        $bodyType = $d['body_type'] ?? 'json';

        try {
            $request = Http::withHeaders($headers)->timeout($timeout);
            $response = match (true) {
                $method === 'GET' || $method === 'DELETE' || $bodyType === 'none' =>
                    $request->send($method, $url),
                $bodyType === 'json' => $this->sendJsonBody($request, $method, $url, $this->renderTemplate((string) ($d['body_template'] ?? ''), $context), $headers, $timeout),
                $bodyType === 'form' => $request->asForm()->send($method, $url, [
                    'form_params' => $this->renderKeyValueArray($d['body_form'] ?? [], $context),
                ]),
                $bodyType === 'raw' => Http::withHeaders($headers)->timeout($timeout)->withBody(
                    $this->renderTemplate((string) ($d['body_template'] ?? ''), $context),
                    $headers['Content-Type'] ?? 'text/plain',
                )->send($method, $url),
                default => $request->send($method, $url),
            };
        } catch (\Throwable $e) {
            $this->audit->log('workflow.http.failed', $instance, null, [
                'url' => $url, 'method' => $method, 'error' => $e->getMessage(),
            ], "HTTP {$method} {$url} fehlgeschlagen: {$e->getMessage()}");
            return false;
        }

        $status = $response->status();
        $ok = $response->successful();

        // Response-Mapping in response.<save_as> (Namespace verhindert,
        // dass reservierte Felder wie subject_user_id überschrieben werden).
        $mapped = [];
        $json = null;
        try { $json = $response->json(); } catch (\Throwable) {}
        foreach (($d['response_mapping'] ?? []) as $m) {
            $saveAs = trim((string) ($m['save_as'] ?? ''));
            $path = trim((string) ($m['path'] ?? ''));
            if ($saveAs === '') continue;
            $value = $path === '' || $path === '$' ? $json : data_get($json ?? [], $path);
            $mapped[$saveAs] = $value;
        }
        if ($mapped) {
            $data = $instance->data ?? [];
            $data['response'] = array_merge($data['response'] ?? [], $mapped);
            // Kompatibilität: zusaetzlich top-level (deprecated, aber wird in
            // Templates noch erwartet). Überschreibt reservierte Felder nicht.
            foreach ($mapped as $k => $v) {
                if (! array_key_exists($k, ['subject_user_id', 'subject_user_email', 'subject_user_name', 'asset_id'])) {
                    $data[$k] = $v;
                }
            }
            $instance->update(['data' => $data]);
        }

        $logUrl = \App\Support\SafeHttpUrl::redactForLog($url);
        $this->audit->log($ok ? 'workflow.http.ok' : 'workflow.http.failed', $instance, null, [
            'url' => $logUrl, 'method' => $method, 'status' => $status, 'mapped' => array_keys($mapped),
        ], "HTTP {$method} {$logUrl} -> {$status}");

        return $ok;
    }

    private function sendJsonBody($request, string $method, string $url, string $rendered, array $headers, int $timeout)
    {
        // Wenn die Vorlage gültiges JSON ist, sende als JSON-Body.
        $rendered = trim($rendered);
        $decoded = $rendered === '' ? null : json_decode($rendered, true);
        if ($rendered === '') {
            return $request->send($method, $url);
        }
        if (json_last_error() === JSON_ERROR_NONE) {
            return $request->asJson()->send($method, $url, [
                \GuzzleHttp\RequestOptions::JSON => $decoded,
            ]);
        }
        // Fallback: roher String mit Content-Type JSON
        return Http::withHeaders($headers + ['Content-Type' => 'application/json'])
            ->timeout($timeout)
            ->withBody($rendered, 'application/json')
            ->send($method, $url);
    }

    private function renderKeyValueArray(array $kvs, array $context): array
    {
        $out = [];
        foreach ($kvs as $kv) {
            $k = trim((string) ($kv['key'] ?? ''));
            if ($k === '') continue;
            $out[$k] = $this->renderTemplate((string) ($kv['value'] ?? ''), $context);
        }
        return $out;
    }

    /**
     * Erzeugt aus einem HTML-Template ein PDF und hängt es als Attachment
     * an die Workflow-Instanz. Filename und Dokumenttyp werden ebenfalls
     * mit Platzhaltern aufgelöst.
     */
    private function renderPdfNode(WorkflowInstance $instance, array $node): void
    {
        $d = $node['data'] ?? [];
        $context = $this->buildContext($instance);

        $html = $this->renderTemplate((string) ($d['html_template'] ?? ''), $context);
        if (trim($html) === '') {
            $this->audit->log('workflow.pdf.failed', $instance, null, ['reason' => 'empty template'],
                'PDF-Knoten ohne HTML-Template');
            return;
        }

        $filename = $this->renderTemplate((string) ($d['filename'] ?? ''), $context);
        $filename = trim($filename) !== '' ? $filename : ('workflow-'.$instance->id.'-'.now()->format('YmdHis').'.pdf');
        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }
        $documentType = trim((string) ($d['document_type'] ?? '')) ?: null;
        $label = trim((string) ($d['label'] ?? '')) ?: null;

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $bytes = $pdf->output();
        } catch (\Throwable $e) {
            $this->audit->log('workflow.pdf.failed', $instance, null, ['error' => $e->getMessage()],
                "PDF-Erzeugung fehlgeschlagen: {$e->getMessage()}");
            return;
        }

        try {
            $att = app(AttachmentStorage::class)->storeBytes(
                $bytes, $filename, 'application/pdf', $instance, $label, null, $documentType
            );
        } catch (\Throwable $e) {
            $this->audit->log('workflow.pdf.failed', $instance, null, ['error' => $e->getMessage()],
                "PDF-Ablage fehlgeschlagen: {$e->getMessage()}");
            return;
        }

        $data = $instance->data ?? [];
        $data['pdf'] = array_merge($data['pdf'] ?? [], [
            'last_attachment_id' => $att->id,
            'last_filename' => $att->original_name,
            'last_hash' => $att->content_hash,
        ]);
        $instance->update(['data' => $data]);

        $this->audit->log('workflow.pdf.generated', $att, null, [
            'instance_id' => $instance->id,
            'filename' => $att->original_name,
            'document_type' => $documentType,
            'sha256' => $att->content_hash,
        ], "PDF erzeugt: {$att->original_name}");
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

    private function evaluateCondition(array $data, array $context): ?int
    {
        foreach ($data['branches'] ?? [] as $idx => $branch) {
            $field = (string) ($branch['field'] ?? '');
            // Unterstuetze Punktnotation, damit doc.indexed_fields.kostenstelle etc. funktioniert.
            $value = str_contains($field, '.') ? data_get($context, $field) : ($context[$field] ?? null);
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
        $quorumMode = (string) ($data['quorum_mode'] ?? 'single');

        // Quorum nur bei Rollen-/Listen-Empfängern sinnvoll (mehrere User
        // gleichzeitig). Bei Einzeluser ignorieren wir den Modus.
        if ($quorumMode !== 'single') {
            $users = $this->resolveQuorumUsers($data, $instance);
            if ($users->isNotEmpty()) {
                $this->createQuorumApproval($instance, $node, $users, $quorumMode, (int) ($data['quorum_min'] ?? $users->count()));
                return;
            }
            // Fallback: kein Quorum möglich -> wie single
        }

        $target = $this->resolveAssignee($data, $instance);
        $originalUserId = $target['user_id'] ?? null;
        $effectiveUserId = $originalUserId;

        // Vertretungsregelung: ist der Adressat heute vertreten, geht
        // die Aufgabe direkt an die Vertretung.
        $delegate = null;
        if ($originalUserId) {
            $assignee = User::find($originalUserId);
            $delegate = $assignee?->activeDelegate();
            if ($delegate) {
                $effectiveUserId = $delegate->id;
            }
        }

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => (string) $node['id'],
            'step_type' => 'approval',
            'assigned_to_user_id' => $effectiveUserId,
            'assigned_to_role_id' => $target['role_id'] ?? null,
            'assigned_at' => now(),
            'due_at' => $this->graceDeadline($data),
        ]);

        if ($delegate && $originalUserId) {
            $original = User::find($originalUserId);
            $this->audit->log('workflow.task.delegated', $step, null, [
                'from_user' => $original?->email,
                'to_user' => $delegate->email,
                'reason' => $original?->delegate_reason,
            ], "Aufgabe vertreten: {$original?->email} -> {$delegate->email}");
        }

        $this->notifyAssignee($step);
    }

    /**
     * Entscheidet, ob ein Quorum-Step (mehrere parallele Sub-Steps mit gleichem
     * step_key) jetzt schon einen Endwert hat oder noch wartet.
     *
     *  - 'all' Modus:   alle müssen approve -> approved.
     *                   Eine rejection bricht direkt ab (rejected).
     *  - 'n_of_m' Modus: ab quorum_min Approvals -> approved.
     *                   Wenn nicht mehr genug offene Stimmen für das Quorum
     *                   übrig sind -> rejected.
     *  - sonst (single): immer direkt durch.
     *
     * @return ?string  Decision oder null wenn unentschieden.
     */
    private function resolveQuorumDecision(WorkflowStepExecution $step, WorkflowInstance $instance, string $myDecision): ?string
    {
        $mode = (string) data_get($step->data_snapshot, 'quorum_mode', 'single');
        if ($mode === 'single') return $myDecision;

        $siblings = WorkflowStepExecution::where('workflow_instance_id', $instance->id)
            ->where('step_key', $step->step_key)
            ->get();
        $total = (int) data_get($step->data_snapshot, 'quorum_total', $siblings->count());
        $min = (int) data_get($step->data_snapshot, 'quorum_min', $total);

        $approved = $siblings->where('decision', 'approved')->count();
        $rejected = $siblings->where('decision', 'rejected')->count();
        $open = $siblings->whereNull('completed_at')->count();

        if ($mode === 'all') {
            if ($rejected > 0) {
                $this->cancelOpenSiblings($siblings);
                return 'rejected';
            }
            if ($open === 0 && $approved === $total) return 'approved';
            return null;
        }

        // n_of_m
        if ($approved >= $min) {
            $this->cancelOpenSiblings($siblings);
            return 'approved';
        }
        // Nicht mehr genug offene Stimmen, um das Quorum zu erreichen?
        if (($approved + $open) < $min) {
            $this->cancelOpenSiblings($siblings);
            return 'rejected';
        }
        return null;
    }

    private function cancelOpenSiblings(\Illuminate\Support\Collection $siblings): void
    {
        foreach ($siblings as $s) {
            if ($s->completed_at) continue;
            $s->forceFill([
                'completed_at' => now(),
                'decision' => 'cancelled_quorum',
            ])->save();
        }
    }

    /**
     * Erzeugt einen Wait-Step (kein Empfänger, nur due_at). Wird vom
     * Scheduler-Command 'workflow:check-due' aufgeweckt, sobald due_at
     * erreicht ist.
     */
    /**
     * Startet einen Sub-Workflow synchron — der aktuelle Workflow pausiert
     * bis die Child-Instance completed ist. Beim Abschluss feuert
     * finishChild() den parent-Step ab und schreibt das output_mapping
     * in die Parent-Daten.
     */
    private function startSubworkflow(WorkflowInstance $instance, array $node): void
    {
        $data = $node['data'] ?? [];
        $targetId = (int) ($data['target_workflow_id'] ?? 0);
        $target = Workflow::find($targetId);
        if (! $target || $target->status !== Workflow::STATUS_ACTIVE) {
            // Fehler — geh auf den Error-Output, falls vorhanden.
            $this->failSubworkflow($instance, $node, 'Ziel-Workflow nicht gefunden oder inaktiv.');
            return;
        }

        $parentStep = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => (string) $node['id'],
            'step_type' => 'subworkflow',
            'assigned_at' => now(),
            'children_count' => 1,
            'children_completed_count' => 0,
        ]);

        $childData = $this->mapFields((array) ($data['input_mapping'] ?? []), $this->buildContext($instance));
        $version = $target->versions()->latest('version_number')->first();
        if (! $version) {
            $this->failSubworkflow($instance, $node, 'Ziel-Workflow hat keine Version.');
            return;
        }

        $child = WorkflowInstance::create([
            'workflow_id' => $target->id,
            'workflow_version_id' => $version->id,
            'started_by' => $instance->started_by,
            'parent_step_execution_id' => $parentStep->id,
            'status' => WorkflowInstance::STATUS_RUNNING,
            'started_at' => now(),
            'data' => $childData,
        ]);
        $this->audit->log('workflow.subworkflow.started', $child, null, [
            'parent_instance_id' => $instance->id,
            'parent_step_key' => $node['id'],
        ], "Sub-Workflow #{$child->id} ({$target->name}) gestartet von Parent #{$instance->id}");
        $this->run($child, $this->startKey($version));
    }

    /**
     * Loop-Knoten: pro Element der Source-Liste wird eine parallele
     * Sub-Instance angelegt. Aktueller Workflow pausiert bis alle fertig.
     */
    private function startForEachLoop(WorkflowInstance $instance, array $node): void
    {
        $data = $node['data'] ?? [];
        $sourceField = trim((string) ($data['source_field'] ?? ''));
        $items = data_get($this->buildContext($instance), $sourceField, []);
        if (! is_array($items)) $items = [];
        $items = array_values($items);
        $maxIter = max(1, (int) ($data['max_iterations'] ?? 100));
        if (count($items) > $maxIter) {
            $items = array_slice($items, 0, $maxIter);
        }

        $targetId = (int) ($data['target_workflow_id'] ?? 0);
        $target = Workflow::find($targetId);
        $itemField = (string) ($data['item_field_name'] ?? '_item');
        $extraMap = (array) ($data['extra_input_mapping'] ?? []);

        $parentStep = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => (string) $node['id'],
            'step_type' => 'loop',
            'assigned_at' => now(),
            'children_count' => count($items),
            'children_completed_count' => 0,
        ]);

        // Sonderfall: leere Liste oder kein Ziel — sofort weitermachen.
        if (empty($items) || ! $target || $target->status !== Workflow::STATUS_ACTIVE) {
            $parentStep->update(['completed_at' => now(), 'decision' => 'skipped']);
            $next = $this->firstTarget($node, 'output_1');
            if ($next) $this->run($instance, $next);
            return;
        }

        $version = $target->versions()->latest('version_number')->first();
        $extraData = $this->mapFields($extraMap, $this->buildContext($instance));

        foreach ($items as $i => $item) {
            $childData = $extraData;
            $childData[$itemField] = $item;
            $childData['_loop_index'] = $i;
            $child = WorkflowInstance::create([
                'workflow_id' => $target->id,
                'workflow_version_id' => $version->id,
                'started_by' => $instance->started_by,
                'parent_step_execution_id' => $parentStep->id,
                'status' => WorkflowInstance::STATUS_RUNNING,
                'started_at' => now(),
                'data' => $childData,
            ]);
            $this->run($child, $this->startKey($version));
        }
        $this->audit->log('workflow.loop.started', null, null, [
            'parent_instance_id' => $instance->id,
            'iterations' => count($items),
            'target_workflow' => $target->name,
        ], "For-each gestartet: {$target->name} ×".count($items));
    }

    /**
     * Wird vom finishInstance()-Pfad aufgerufen wenn eine Child-Instance
     * abgeschlossen ist. Schreibt output_mapping in die Parent-Daten und
     * macht — sobald alle Children fertig sind — den parent-Step zu und
     * setzt den Workflow fort.
     */
    public function finishChild(WorkflowInstance $child): void
    {
        $parentStep = WorkflowStepExecution::find($child->parent_step_execution_id);
        if (! $parentStep) return;
        $parent = $parentStep->instance;
        if (! $parent) return;

        $parentNode = $parent->version->definition['drawflow']['Home']['data'][$parentStep->step_key] ?? null;
        if (! $parentNode) return;

        // Output-Mapping nur bei subworkflow.
        if ($parentStep->step_type === 'subworkflow') {
            $outMap = (array) data_get($parentNode, 'data.output_mapping', []);
            if ($outMap) {
                $childCtx = $this->buildContext($child);
                $parentData = $parent->data ?? [];
                foreach ($outMap as $entry) {
                    $parentKey = $entry['target'] ?? $entry['key'] ?? null;
                    $childExpr = $entry['source'] ?? $entry['value'] ?? null;
                    if ($parentKey && $childExpr) {
                        $parentData[$parentKey] = data_get($childCtx, $childExpr);
                    }
                }
                $parent->data = $parentData;
                $parent->save();
            }
        }

        // Loop-Collect: pro Child einen Wert in die Sammler-Liste schreiben,
        // damit der Aggregator-Knoten danach was zum Aggregieren hat.
        if ($parentStep->step_type === 'loop') {
            $collectFrom = (string) data_get($parentNode, 'data.collect_field', '');
            $collectInto = (string) (data_get($parentNode, 'data.collect_into', '_loop_results') ?: '_loop_results');
            if ($collectFrom !== '') {
                $value = data_get($child->data ?? [], $collectFrom);
                $parentData = $parent->data ?? [];
                $bucket = (array) ($parentData[$collectInto] ?? []);
                $bucket[] = $value;
                $parentData[$collectInto] = $bucket;
                $parent->update(['data' => $parentData]);
            }
        }

        // Children-Counter erhöhen
        $parentStep->increment('children_completed_count');
        $parentStep->refresh();

        $allDone = ($parentStep->children_completed_count ?? 0) >= ($parentStep->children_count ?? 0);
        if (! $allDone) return;

        // Alle fertig — parent-Step abschliessen, nächster Knoten.
        $childFailed = $child->status !== WorkflowInstance::STATUS_COMPLETED;
        $outputKey = 'output_1';
        if ($parentStep->step_type === 'subworkflow' && $childFailed
            && empty(data_get($parentNode, 'data.continue_on_failure', false))) {
            $outputKey = 'output_2';
        }

        $parentStep->update([
            'completed_at' => now(),
            'decision' => $childFailed ? 'sub_failed' : 'sub_ok',
        ]);
        $next = $this->firstTarget($parentNode, $outputKey);
        if ($next) {
            $this->run($parent, $next);
        } else {
            $parent->update(['status' => WorkflowInstance::STATUS_COMPLETED, 'completed_at' => now()]);
        }
    }

    private function failSubworkflow(WorkflowInstance $instance, array $node, string $reason): void
    {
        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => (string) $node['id'],
            'step_type' => 'subworkflow',
            'assigned_at' => now(),
            'completed_at' => now(),
            'decision' => 'sub_failed',
            'comment' => $reason,
        ]);
        $next = $this->firstTarget($node, 'output_2');
        if ($next) $this->run($instance, $next);
    }

    /**
     * Workert ein input_mapping-Array zu einem flachen Hash auf, der als
     * data() der Child-Instance verwendet werden kann. Jeder Eintrag hat
     * 'target' (Child-Schlüssel) und 'source' (Pfad oder Literal).
     */
    private function mapFields(array $mapping, array $ctx): array
    {
        $out = [];
        foreach ($mapping as $entry) {
            $key = $entry['target'] ?? $entry['key'] ?? null;
            $src = $entry['source'] ?? $entry['value'] ?? null;
            if (! $key) continue;
            // Wenn source-String mit "$." anfängt: Pfad. Sonst Literal.
            if (is_string($src) && str_starts_with($src, '$.')) {
                $out[$key] = data_get($ctx, substr($src, 2));
            } else {
                $out[$key] = $src;
            }
        }
        return $out;
    }

    private function startKey($version): ?string
    {
        $nodes = (array) data_get($version->definition, 'drawflow.Home.data', []);
        foreach ($nodes as $key => $node) {
            if (($node['class'] ?? null) === 'start') return (string) $key;
        }
        // Fallback: erster Knoten
        return array_key_first($nodes);
    }

    /**
     * Switch-Knoten: liefert den Index des passenden Case (0-basiert) oder
     * null für den Default-Ausgang.
     */
    private function evaluateSwitch(array $data, array $ctx): ?int
    {
        $expr = (string) ($data['expression'] ?? '');
        if ($expr === '') return null;
        $value = data_get($ctx, $expr);
        $cases = (array) ($data['cases'] ?? []);
        foreach ($cases as $i => $case) {
            $candidate = $case['value'] ?? null;
            // Wenn beide numerisch: numerischer Vergleich. Sonst String.
            if (is_numeric($value) && is_numeric($candidate)) {
                if ((float) $value === (float) $candidate) return $i;
            } else {
                if ((string) $value === (string) $candidate) return $i;
            }
        }
        return null;
    }

    /**
     * Aggregator: liest eine Liste aus instance.data, faltet sie zu einem
     * einzelnen Wert und schreibt ihn in das Ziel-Feld.
     */
    private function runAggregator(WorkflowInstance $instance, array $node): void
    {
        $data = $node['data'] ?? [];
        $source = (string) ($data['source_field'] ?? '');
        $op = (string) ($data['operation'] ?? 'sum');
        $target = (string) ($data['target_field'] ?? 'aggregated');
        if ($source === '' || $target === '') return;

        $list = data_get($instance->data ?? [], $source, []);
        if (! is_array($list)) $list = [];

        $numerics = array_values(array_filter(array_map(function ($v) {
            if (is_array($v)) return null;
            $s = is_string($v) ? str_replace([',', ' '], ['.', ''], $v) : $v;
            return is_numeric($s) ? (float) $s : null;
        }, $list), fn ($v) => $v !== null));

        $result = match ($op) {
            'sum'      => round(array_sum($numerics), 4),
            'avg'      => count($numerics) > 0 ? round(array_sum($numerics) / count($numerics), 4) : 0,
            'min'      => count($numerics) > 0 ? min($numerics) : null,
            'max'      => count($numerics) > 0 ? max($numerics) : null,
            'count'    => count($list),
            'concat'   => implode((string) ($data['separator'] ?? ', '), array_map('strval', $list)),
            'distinct' => array_values(array_unique(array_map('strval', $list))),
            default    => null,
        };

        $payload = $instance->data ?? [];
        $payload[$target] = $result;
        $instance->update(['data' => $payload]);

        $this->audit->log('workflow.aggregator', $instance, null, [
            'source' => $source, 'op' => $op, 'target' => $target,
            'input_count' => count($list), 'result' => is_array($result) ? '[…]' : $result,
        ], "Aggregator {$op} auf {$source}: ".(is_scalar($result) ? $result : 'list'));
    }

    private function createWaitStep(WorkflowInstance $instance, array $node): void
    {
        $d = $node['data'] ?? [];
        $value = max(0, (int) ($d['wait_value'] ?? 1));
        $unit = $d['wait_unit'] ?? 'days';
        $now = now();
        $due = match ($unit) {
            'minutes' => $now->copy()->addMinutes($value),
            'hours' => $now->copy()->addHours($value),
            'days' => $now->copy()->addDays($value),
            'weeks' => $now->copy()->addWeeks($value),
            'months' => $now->copy()->addMonths($value),
            default => $now->copy()->addDays($value),
        };

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $instance->id,
            'step_key' => (string) $node['id'],
            'step_type' => 'wait',
            'assigned_at' => $now,
            'due_at' => $due,
        ]);

        $this->audit->log('workflow.wait.scheduled', $step, null, [
            'value' => $value, 'unit' => $unit, 'due_at' => $due->toIso8601String(),
        ], "Workflow pausiert bis {$due->format('d.m.Y H:i')}");
    }

    /**
     * Vom Scheduler aufgerufen, wenn ein Wait-Step überfällig ist.
     * Markiert ihn als completed und faehrt den Workflow fort.
     */
    public function resumeWaitStep(WorkflowStepExecution $step): void
    {
        if ($step->completed_at) return;
        if ($step->step_type !== 'wait') return;

        $step->forceFill([
            'completed_at' => now(),
            'decision' => 'wait_done',
        ])->save();

        $instance = $step->instance()->firstOrFail();
        $version = $instance->version()->firstOrFail();
        $node = $version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        if (! $node) return;

        $this->audit->log('workflow.wait.resumed', $step, null, null,
            'Wartezeit abgelaufen, Workflow läuft weiter.');

        $next = $this->firstTarget($node, 'output_1');
        if ($next) $this->run($instance, $next);
        else {
            $instance->update(['status' => WorkflowInstance::STATUS_COMPLETED, 'completed_at' => now()]);
        }
    }

    /**
     * Set-Field-Knoten: rendert Werte aus Templates und schreibt sie
     * in instance.data. Damit kannst du z. B. doc.indexed_fields.netto
     * mit Faktor multipliziert nach instance.data.brutto schreiben.
     */
    private function setFieldsNode(WorkflowInstance $instance, array $node): void
    {
        $d = $node['data'] ?? [];
        $assignments = (array) ($d['assignments'] ?? []);
        if (! $assignments) return;

        $context = $this->buildContext($instance);
        $data = $instance->data ?? [];
        $set = [];
        foreach ($assignments as $a) {
            $key = trim((string) ($a['field'] ?? ''));
            if ($key === '') continue;
            $value = $this->renderTemplate((string) ($a['value'] ?? ''), $context);
            // numerische Auswertung (sehr einfach: nur für Ausdrücke wie "1.19*100")
            if (! empty($a['as_number']) && is_numeric(trim($value))) {
                $value = $value + 0;
            }
            $data[$key] = $value;
            $set[$key] = $value;
        }
        $instance->update(['data' => $data]);

        $this->audit->log('workflow.set_field', $instance, null, ['fields' => $set],
            'Felder gesetzt: '.implode(', ', array_keys($set)));
    }

    /**
     * Quorum: liefert die Liste der User, die in diesem Step abstimmen
     * sollen. Rolle -> alle Mitglieder. Lookup -> nicht unterstützt
     * (per definitionem nur einer). Sonst leer.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveQuorumUsers(array $data, WorkflowInstance $instance): \Illuminate\Support\Collection
    {
        $type = $data['recipient_type'] ?? null;
        if ($type === 'role' && ! empty($data['recipient_role_id'])) {
            $role = \App\Models\Role::with('users')->find($data['recipient_role_id']);
            return $role ? $role->users->filter(fn ($u) => $u->is_active)->values() : collect();
        }
        return collect();
    }

    private function createQuorumApproval(WorkflowInstance $instance, array $node, \Illuminate\Support\Collection $users, string $mode, int $min): void
    {
        $data = $node['data'] ?? [];
        $due = $this->graceDeadline($data);
        $createdSteps = collect();

        foreach ($users as $user) {
            $delegate = $user->activeDelegate();
            $effective = $delegate?->id ?? $user->id;

            $step = WorkflowStepExecution::create([
                'workflow_instance_id' => $instance->id,
                'step_key' => (string) $node['id'],
                'step_type' => 'approval',
                'assigned_to_user_id' => $effective,
                'assigned_at' => now(),
                'due_at' => $due,
                'data_snapshot' => [
                    'quorum_mode' => $mode,
                    'quorum_min' => $min,
                    'quorum_total' => $users->count(),
                ],
            ]);
            if ($delegate) {
                $this->audit->log('workflow.task.delegated', $step, null, [
                    'from_user' => $user->email, 'to_user' => $delegate->email,
                    'reason' => $user->delegate_reason,
                ], "Quorum-Aufgabe vertreten: {$user->email} -> {$delegate->email}");
            }
            $createdSteps->push($step);
        }

        $this->audit->log('workflow.quorum.created', $instance, null, [
            'step_key' => (string) $node['id'],
            'mode' => $mode,
            'min' => $min,
            'total' => $users->count(),
            'users' => $users->pluck('email')->all(),
        ], "Quorum-Aufgabe angelegt ({$mode}, {$min}/".$users->count().')');

        foreach ($createdSteps as $step) {
            $this->notifyAssignee($step);
        }
    }

    private function notifyAssignee(WorkflowStepExecution $step): void
    {
        // Teams-Channel benachrichtigen (nur einmal pro Step, nicht
        // pro Empfänger — gleicher Channel hört eh alle Member).
        $teams = app(\App\Services\TeamsNotifier::class);
        $node = data_get($step->instance->version->definition, "drawflow.Home.data.{$step->step_key}");
        $teamsUrl = (string) (data_get($node, 'data.teams_webhook_url') ?? \App\Support\Settings::get('integrations.teams_webhook_url', ''));
        if ($teamsUrl !== '' && data_get($node, 'data.notify_teams', true)) {
            $teams->sendTaskNotification($step, $teamsUrl);
        }

        $recipients = $this->stepRecipients($step);
        $workflow = $step->instance?->workflow;
        foreach ($recipients as $user) {
            if (\App\Support\NotificationPreferences::wants($user, 'task.assigned', 'in_app')) {
                \App\Models\AppNotification::send(
                    $user,
                    'task.assigned',
                    'Neue Aufgabe: '.($workflow?->name ?? 'Workflow'),
                    'Du wurdest einer Genehmigungs-Aufgabe zugewiesen.',
                    route('tasks.show', $step),
                );
            }
            if (! \App\Support\NotificationPreferences::wants($user, 'task.assigned', 'mail')) continue;
            try {
                Mail::to($user->email)->send(new WorkflowTaskAssignedMail($step, $user));
            } catch (\Throwable $e) {
                Log::warning('Task mail failed', ['to' => $user->email, 'error' => $e->getMessage()]);
            }
        }
    }

    /** @return \Illuminate\Support\Collection<User> */
    public function stepRecipients(WorkflowStepExecution $step): \Illuminate\Support\Collection
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
            'subject_user' => ['user_id' => $instance->subjectUser()?->id],
            'supervisor_of_subject' => [
                'user_id' => $instance->subjectUser()?->effectiveSupervisor()?->id,
            ],
            'supervisor_of_initiator' => [
                'user_id' => $instance->starter()->first()?->effectiveSupervisor()?->id,
            ],
            'supervisor_of_previous' => [
                'user_id' => $this->previousSupervisor($instance)?->id,
            ],
            'list_lookup' => $this->resolveFromListWithFallback($data, $instance),
            default => [],
        };
    }

    /**
     * Wie list_lookup, aber mit konfigurierbarem Fallback wenn der Lookup
     * leer ist (z. B. kein Kostenstellen-Code im Dokument).
     */
    private function resolveFromListWithFallback(array $data, WorkflowInstance $instance): array
    {
        $result = $this->resolveFromList(
            (int) ($data['list_id'] ?? 0),
            (string) ($data['lookup_source'] ?? ''),
            \App\Models\LookupList::ROLE_RESPONSIBLE,
            $instance,
        );
        if (! empty($result)) return $result;

        // Fallback aus Knoten-Konfiguration: erst User, dann Rolle.
        if (! empty($data['fallback_user_id'])) {
            return ['user_id' => (int) $data['fallback_user_id']];
        }
        if (! empty($data['fallback_role_id'])) {
            return ['role_id' => (int) $data['fallback_role_id']];
        }
        return [];
    }

    /**
     * Resolve a recipient via a lookup list. `$source` is the form-field key
     * whose value is used as the lookup key. `$role` selects which column of
     * the list contains the recipient e-mail.
     *
     * @return array{user_id?: int}
     */
    private function resolveFromList(int $listId, string $source, string $role, WorkflowInstance $instance): array
    {
        if ($listId <= 0 || $source === '') return [];
        $list = \App\Models\LookupList::find($listId);
        if (! $list) return [];

        // Source unterstützt Punktnotation: "kostenstelle" oder
        // "doc.indexed_fields.kostenstelle" — damit klappt das Routing auch,
        // wenn der Wert aus extrahierten Dokument-Feldern kommt.
        $context = $this->buildContext($instance);
        $key = str_contains($source, '.')
            ? data_get($context, $source)
            : ($instance->data[$source] ?? null);
        if ($key === null || $key === '') return [];

        $email = $list->emailForRole((string) $key, $role);
        if (! $email) return [];

        $user = User::where('email', $email)->first();
        return $user ? ['user_id' => $user->id] : [];
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

        if ($type === 'list_lookup') {
            // Same list/source as the recipient by default; escalation column.
            $listId = (int) ($data['escalation_list_id'] ?? $data['list_id'] ?? 0);
            $source = (string) ($data['escalation_source'] ?? $data['lookup_source'] ?? '');
            $resolved = $this->resolveFromList($listId, $source, \App\Models\LookupList::ROLE_ESCALATION, $instance);
            return $resolved ?: null;
        }
        return null;
    }

    /** @return array<User> */
    private function resolveRecipients(array $data, WorkflowInstance $instance): array
    {
        $type = $data['recipient_type'] ?? 'initiator';
        return match ($type) {
            'initiator' => array_filter([$instance->starter()->first()]),
            'subject_user' => array_filter([$instance->subjectUser()]),
            'supervisor_of_initiator' => array_filter([
                $instance->starter()->first()?->effectiveSupervisor(),
            ]),
            'supervisor_of_subject' => array_filter([
                $instance->subjectUser()?->effectiveSupervisor(),
            ]),
            'role' => \App\Models\Role::find($data['recipient_role_id'] ?? 0)
                ?->users?->all() ?? [],
            'user' => array_filter([User::find($data['recipient_user_id'] ?? 0)]),
            'list_lookup' => $this->resolveUsersFromList(
                (int) ($data['list_id'] ?? 0),
                (string) ($data['lookup_source'] ?? ''),
                \App\Models\LookupList::ROLE_RESPONSIBLE,
                $instance,
            ),
            default => [],
        };
    }

    /** @return array<User> */
    private function resolveUsersFromList(int $listId, string $source, string $role, WorkflowInstance $instance): array
    {
        $resolved = $this->resolveFromList($listId, $source, $role, $instance);
        if (! isset($resolved['user_id'])) return [];
        $u = User::find($resolved['user_id']);
        return $u ? [$u] : [];
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
        return preg_replace_callback('/\{\{\s*([\w_.]+)\s*\}\}/', function ($m) use ($ctx) {
            $v = data_get($ctx, $m[1]);
            if ($v === null) return '';
            if (is_bool($v)) return $v ? 'ja' : 'nein';
            if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return (string) $v;
        }, $tpl);
    }

    /**
     * Erstellt den Platzhalter-Kontext aus Instanz-Daten, Antragsteller-,
     * Subject-User-Custom-Feldern und Secrets ({{ secret.<key> }}).
     */
    private function buildContext(WorkflowInstance $instance): array
    {
        $initiator = $instance->starter()->first();
        $subject = $instance->subjectUser();

        // doc.*-Kontext aus dem zugeordneten Dokument (falls vorhanden).
        // Wird gesetzt wenn der Workflow aus dem Postkorb / IMAP heraus
        // mit einem konkreten Attachment gestartet wird.
        $doc = [];
        $attachmentId = $instance->data['doc_attachment_id'] ?? null;
        if ($attachmentId) {
            $att = \App\Models\Attachment::find($attachmentId);
            if ($att) {
                $doc = [
                    'id' => $att->id,
                    'original_name' => $att->original_name,
                    'document_type' => $att->document_type ?? '',
                    'mime_type' => $att->mime_type,
                    'size' => $att->size,
                    'indexed_fields' => $att->indexed_fields ?? [],
                ];
            }
        }

        return array_merge(
            $instance->data ?? [],
            [
                'instance_id' => $instance->id,
                'instance_started_at' => $instance->started_at?->toIso8601String(),
                'workflow_name' => $instance->workflow->name ?? '',
                'initiator' => $initiator?->name ?? '',
                'initiator_name' => $initiator?->name ?? '',
                'initiator_email' => $initiator?->email ?? '',
                'initiator_custom' => $initiator?->custom_fields ?? [],
                'subject_user_name' => $subject?->name ?? '',
                'subject_user_email' => $subject?->email ?? '',
                'subject_user_custom' => $subject?->custom_fields ?? [],
                'secret' => \App\Models\Secret::asMap(),
                'response' => $instance->data['response'] ?? [],
                'doc' => $doc,
            ],
        );
    }

    /** Dispatcht einen Webhook (best-effort, sync, mit HMAC-Signatur). */
    private function dispatchWebhook(string $event, WorkflowInstance $instance, array $extra = []): void
    {
        $hooks = Webhook::where('is_active', true)->get()->filter(
            fn ($h) => in_array($event, $h->events ?? [], true)
        );
        if ($hooks->isEmpty()) return;

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'instance' => [
                'id' => $instance->id,
                'status' => $instance->status,
                'workflow_id' => $instance->workflow_id,
                'workflow_name' => $instance->workflow?->name,
                'started_by' => $instance->starter?->email,
                'data' => $instance->data,
            ],
        ];
        if ($extra) $payload['extra'] = $this->sanitizeForWebhook($extra);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($hooks as $hook) {
            $headers = array_merge(['Content-Type' => 'application/json'], $hook->headers ?? []);
            if ($hook->secret) {
                $headers['X-OWE-Signature'] = 'sha256='.hash_hmac('sha256', $body, $hook->secret);
            }
            try {
                $resp = Http::withHeaders($headers)->timeout(10)->withBody($body, 'application/json')->post($hook->url);
                $hook->forceFill([
                    'last_called_at' => now(),
                    'failure_count' => $resp->successful() ? 0 : $hook->failure_count + 1,
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Webhook failed', ['url' => $hook->url, 'error' => $e->getMessage()]);
                $hook->forceFill(['failure_count' => $hook->failure_count + 1])->save();
            }
        }
    }

    private function sanitizeForWebhook(array $extra): array
    {
        $out = [];
        foreach ($extra as $k => $v) {
            if ($v instanceof \Illuminate\Database\Eloquent\Model) {
                $out[$k] = ['id' => $v->getKey(), 'type' => $v->getMorphClass()];
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
