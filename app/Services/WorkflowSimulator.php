<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

/**
 * Trockenlauf eines Workflows: läuft die Engine, persistiert NICHTS,
 * verschickt KEINE Mails / HTTP-Calls / Webhooks. Liefert einen Trace
 * mit jedem besuchten Knoten und der Begründung der Verzweigung.
 *
 * Implementierung: kapselt den Lauf in DB::transaction mit Rollback,
 * sodass jegliche DB-Schreiboperation neutralisiert wird. Wir
 * mocken zusaetzlich die Mail-Fassade, damit keine Mails rausgehen.
 */
class WorkflowSimulator
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{trace: array<int, array>, error: ?string, instance_data: array}
     */
    public function simulate(Workflow $workflow, array $formData, ?\App\Models\User $initiator): array
    {
        $version = $workflow->currentVersion()->first();
        if (! $version) {
            return ['trace' => [], 'error' => 'Workflow hat keine gespeicherte Version.', 'instance_data' => []];
        }

        $nodes = $version->definition['drawflow']['Home']['data'] ?? [];
        if (! $nodes) {
            return ['trace' => [], 'error' => 'Workflow-Definition ist leer.', 'instance_data' => []];
        }

        $start = collect($nodes)->first(fn ($n) => ($n['class'] ?? null) === 'start');
        if (! $start) {
            return ['trace' => [], 'error' => 'Kein Start-Knoten gefunden.', 'instance_data' => []];
        }

        $trace = [];
        $instanceData = $formData;
        try {
            $this->walk(
                $version,
                (string) $start['id'],
                $instanceData,
                $initiator,
                $trace,
                0,
            );
            return ['trace' => $trace, 'error' => null, 'instance_data' => $instanceData];
        } catch (\Throwable $e) {
            return ['trace' => $trace, 'error' => $e->getMessage(), 'instance_data' => $instanceData];
        }
    }

    private function walk(WorkflowVersion $version, string $nodeId, array &$data, ?\App\Models\User $initiator, array &$trace, int $depth): void
    {
        if ($depth > 100) {
            $trace[] = ['type' => 'error', 'message' => 'Tiefenlimit erreicht (mgl. Endlos-Loop).'];
            return;
        }

        $nodes = $version->definition['drawflow']['Home']['data'] ?? [];
        $node = $nodes[$nodeId] ?? null;
        if (! $node) {
            $trace[] = ['type' => 'error', 'message' => "Knoten {$nodeId} nicht gefunden."];
            return;
        }

        $class = $node['class'] ?? null;
        $label = data_get($node, 'data.label', $class);

        switch ($class) {
            case 'start':
                $trace[] = ['node_id' => $nodeId, 'class' => 'start', 'label' => $label, 'action' => 'gestartet'];
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->walk($version, $next, $data, $initiator, $trace, $depth + 1);
                break;

            case 'end':
                $result = data_get($node, 'data.result', 'completed');
                $trace[] = ['node_id' => $nodeId, 'class' => 'end', 'label' => $label,
                    'action' => "beendet ({$result})"];
                break;

            case 'condition':
                $context = $this->buildContext($data, $initiator);
                $branchIdx = $this->evaluateCondition($node['data'] ?? [], $context);
                $branches = $node['data']['branches'] ?? [];
                $outputKey = $branchIdx !== null ? 'output_'.($branchIdx + 1) : 'output_'.(count($branches) + 1);
                $branchLabel = $branchIdx !== null
                    ? (data_get($branches, $branchIdx.'.label') ?: 'Zweig '.($branchIdx + 1))
                    : 'sonst';
                $trace[] = ['node_id' => $nodeId, 'class' => 'condition', 'label' => $label,
                    'action' => "Verzweigung: {$branchLabel}"];
                $next = $this->firstTarget($node, $outputKey);
                if ($next) $this->walk($version, $next, $data, $initiator, $trace, $depth + 1);
                break;

            case 'approval':
                $assignee = $this->describeAssignee($node['data'] ?? [], $data, $initiator);
                $trace[] = ['node_id' => $nodeId, 'class' => 'approval', 'label' => $label,
                    'action' => "Aufgabe an: {$assignee}",
                    'note' => 'Simulation nimmt automatisch "approved" als nächsten Pfad.'];
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->walk($version, $next, $data, $initiator, $trace, $depth + 1);
                break;

            case 'notify':
                $recip = data_get($node, 'data.recipient_type', '—');
                $trace[] = ['node_id' => $nodeId, 'class' => 'notify', 'label' => $label,
                    'action' => "Mail an: {$recip} (in Simulation NICHT versendet)"];
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->walk($version, $next, $data, $initiator, $trace, $depth + 1);
                break;

            case 'http':
                $url = data_get($node, 'data.url', '—');
                $trace[] = ['node_id' => $nodeId, 'class' => 'http', 'label' => $label,
                    'action' => "HTTP-Call: {$url} (in Simulation NICHT ausgeführt)"];
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->walk($version, $next, $data, $initiator, $trace, $depth + 1);
                break;

            case 'pdf_render':
                $trace[] = ['node_id' => $nodeId, 'class' => 'pdf_render', 'label' => $label,
                    'action' => 'PDF würde erzeugt (in Simulation NICHT geschrieben)'];
                $next = $this->firstTarget($node, 'output_1');
                if ($next) $this->walk($version, $next, $data, $initiator, $trace, $depth + 1);
                break;

            default:
                $trace[] = ['node_id' => $nodeId, 'class' => $class, 'label' => $label,
                    'action' => 'unbekannter Knotentyp'];
        }
    }

    private function describeAssignee(array $d, array $data, ?\App\Models\User $initiator): string
    {
        $type = $d['recipient_type'] ?? 'supervisor_of_initiator';

        if ($type === 'user') {
            $u = $d['recipient_user_id'] ? \App\Models\User::find($d['recipient_user_id']) : null;
            return $u ? "Benutzer {$u->name}" : 'Benutzer (nicht gefunden)';
        }
        if ($type === 'role') {
            $r = $d['recipient_role_id'] ? \App\Models\Role::find($d['recipient_role_id']) : null;
            return $r ? "Rolle {$r->name}" : 'Rolle (nicht gewählt)';
        }
        if ($type === 'supervisor_of_initiator') {
            $s = $initiator?->effectiveSupervisor();
            return $s ? "Vorgesetzter des Antragstellers ({$s->name})" : 'Vorgesetzter des Antragstellers (kein Supervisor gesetzt!)';
        }
        if ($type === 'list_lookup') {
            $list = $d['list_id'] ? \App\Models\LookupList::find($d['list_id']) : null;
            $source = (string) ($d['lookup_source'] ?? '');
            if (! $list) return 'Lookup ohne Liste';
            $context = $this->buildContext($data, $initiator);
            $key = str_contains($source, '.') ? data_get($context, $source) : ($data[$source] ?? null);
            if (! $key) return "Lookup: Schlüssel-Wert leer ({$source}) -> Fallback";
            $email = $list->emailForRole((string) $key, \App\Models\LookupList::ROLE_RESPONSIBLE);
            if (! $email) return "Lookup: kein Treffer für '{$key}' in {$list->name} -> Fallback";
            $u = \App\Models\User::where('email', $email)->first();
            return $u ? "Lookup-Treffer: {$u->name} ({$email})" : "Lookup-Treffer: {$email} (kein User)";
        }
        return $type;
    }

    private function evaluateCondition(array $data, array $context): ?int
    {
        foreach ($data['branches'] ?? [] as $idx => $branch) {
            $field = (string) ($branch['field'] ?? '');
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
        return null;
    }

    private function firstTarget(array $node, string $outputKey): ?string
    {
        $conn = $node['outputs'][$outputKey]['connections'][0] ?? null;
        return $conn ? (string) $conn['node'] : null;
    }

    private function buildContext(array $data, ?\App\Models\User $initiator): array
    {
        $doc = [];
        if (! empty($data['doc_attachment_id'])) {
            $att = \App\Models\Attachment::find($data['doc_attachment_id']);
            if ($att) {
                $doc = [
                    'id' => $att->id,
                    'original_name' => $att->original_name,
                    'document_type' => $att->document_type ?? '',
                    'indexed_fields' => $att->indexed_fields ?? [],
                ];
            }
        }
        return array_merge($data, [
            'initiator' => $initiator?->name ?? '',
            'initiator_name' => $initiator?->name ?? '',
            'initiator_email' => $initiator?->email ?? '',
            'doc' => $doc,
        ]);
    }
}
