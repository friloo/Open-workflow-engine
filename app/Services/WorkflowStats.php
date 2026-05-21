<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use Illuminate\Support\Collection;

/**
 * Aggregierte Workflow-Metriken: laufende vs. abgeschlossene Instanzen,
 * Durchlaufzeit (Median + p95), Engpässe pro Schritt, Wochen-Throughput.
 */
class WorkflowStats
{
    public function global(): array
    {
        $instances = WorkflowInstance::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')->pluck('n', 'status');

        $overdue = WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $byWeek = $this->throughputByWeek(null);

        return [
            'instances' => [
                'running' => (int) ($instances[WorkflowInstance::STATUS_RUNNING] ?? 0),
                'completed' => (int) ($instances[WorkflowInstance::STATUS_COMPLETED] ?? 0),
                'failed' => (int) ($instances[WorkflowInstance::STATUS_FAILED] ?? 0),
                'cancelled' => (int) ($instances[WorkflowInstance::STATUS_CANCELLED] ?? 0),
            ],
            'overdue_tasks' => $overdue,
            'throughput' => $byWeek,
        ];
    }

    public function forWorkflow(Workflow $workflow): array
    {
        $base = WorkflowInstance::where('workflow_id', $workflow->id);

        $byStatus = (clone $base)->selectRaw('status, count(*) as n')
            ->groupBy('status')->pluck('n', 'status');

        $durations = (clone $base)
            ->whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->orderBy('id')
            ->limit(500)
            ->get(['started_at', 'completed_at'])
            ->map(fn ($i) => max(0, $i->completed_at->getTimestamp() - $i->started_at->getTimestamp()));

        $overdue = WorkflowStepExecution::query()
            ->whereHas('instance', fn ($q) => $q->where('workflow_id', $workflow->id))
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        return [
            'instances' => [
                'running' => (int) ($byStatus[WorkflowInstance::STATUS_RUNNING] ?? 0),
                'completed' => (int) ($byStatus[WorkflowInstance::STATUS_COMPLETED] ?? 0),
                'failed' => (int) ($byStatus[WorkflowInstance::STATUS_FAILED] ?? 0),
                'cancelled' => (int) ($byStatus[WorkflowInstance::STATUS_CANCELLED] ?? 0),
            ],
            'duration' => [
                'avg' => $durations->avg(),
                'p50' => $this->percentile($durations, 0.5),
                'p95' => $this->percentile($durations, 0.95),
                'n' => $durations->count(),
            ],
            'overdue_tasks' => $overdue,
            'bottlenecks' => $this->bottlenecks($workflow),
            'throughput' => $this->throughputByWeek($workflow->id),
            'sla' => $this->slaMetrics($workflow),
            'decisions' => $this->decisionDistribution($workflow),
            'top_assignees' => $this->topAssignees($workflow),
            'suggestions' => $this->suggestions($workflow),
        ];
    }

    /**
     * SLA-Quote: wie viele Schritte mit gesetzter due_at wurden vor Frist
     * abgeschlossen? Plus durchschnittliche Verspaetung in Sekunden bei
     * den verspaeteten.
     */
    private function slaMetrics(Workflow $workflow): array
    {
        $rows = WorkflowStepExecution::query()
            ->whereHas('instance', fn ($q) => $q->where('workflow_id', $workflow->id))
            ->whereNotNull('completed_at')
            ->whereNotNull('due_at')
            ->orderBy('id')->limit(2000)
            ->get(['due_at', 'completed_at']);

        if ($rows->isEmpty()) {
            return ['n' => 0, 'on_time' => 0, 'late' => 0, 'on_time_pct' => null, 'avg_lateness_sec' => null];
        }

        $onTime = 0; $late = 0; $latenessSecs = [];
        foreach ($rows as $r) {
            $diff = $r->completed_at->getTimestamp() - $r->due_at->getTimestamp();
            if ($diff <= 0) {
                $onTime++;
            } else {
                $late++;
                $latenessSecs[] = $diff;
            }
        }
        $n = $rows->count();
        return [
            'n' => $n,
            'on_time' => $onTime,
            'late' => $late,
            'on_time_pct' => (int) round($onTime / $n * 100),
            'avg_lateness_sec' => $late > 0 ? (int) (array_sum($latenessSecs) / $late) : null,
        ];
    }

    /**
     * Approval-Entscheidungs-Verteilung pro Approval-Knoten:
     * wie oft approved / rejected / escalated.
     */
    private function decisionDistribution(Workflow $workflow): array
    {
        $version = $workflow->currentVersion()->first();
        $labels = [];
        foreach (($version?->definition['drawflow']['Home']['data'] ?? []) as $nodeId => $node) {
            if (($node['class'] ?? null) === 'approval') {
                $labels[(string) $nodeId] = $node['data']['label'] ?? $nodeId;
            }
        }
        if (empty($labels)) return [];

        $rows = WorkflowStepExecution::query()
            ->whereHas('instance', fn ($q) => $q->where('workflow_id', $workflow->id))
            ->where('step_type', 'approval')
            ->whereNotNull('decision')
            ->whereIn('step_key', array_keys($labels))
            ->selectRaw('step_key, decision, count(*) as n')
            ->groupBy('step_key', 'decision')
            ->get();

        $byKey = [];
        foreach ($labels as $key => $label) {
            $byKey[$key] = ['label' => $label, 'approved' => 0, 'rejected' => 0, 'escalated' => 0, 'total' => 0];
        }
        foreach ($rows as $r) {
            $d = (string) $r->decision;
            if (! in_array($d, ['approved', 'rejected', 'escalated'], true)) continue;
            $byKey[$r->step_key][$d] = (int) $r->n;
            $byKey[$r->step_key]['total'] += (int) $r->n;
        }
        $out = array_values(array_filter($byKey, fn ($x) => $x['total'] > 0));
        usort($out, fn ($a, $b) => $b['total'] <=> $a['total']);
        return $out;
    }

    /**
     * Top-10 Bearbeiter nach abgeschlossenen Schritten + ihre
     * Durchschnitts-Bearbeitungszeit. Zeigt, wer hilft den Workflow
     * zu treiben und wer ggf. ueberlastet ist.
     */
    private function topAssignees(Workflow $workflow): array
    {
        $rows = WorkflowStepExecution::query()
            ->whereHas('instance', fn ($q) => $q->where('workflow_id', $workflow->id))
            ->whereNotNull('completed_at')
            ->whereNotNull('completed_by')
            ->with('completedBy:id,name')
            ->orderBy('id')->limit(2000)
            ->get(['completed_by', 'assigned_at', 'completed_at']);

        $byUser = [];
        foreach ($rows as $r) {
            $uid = $r->completed_by;
            if (! $uid) continue;
            $sec = max(0, $r->completed_at->getTimestamp() - $r->assigned_at->getTimestamp());
            $byUser[$uid]['name'] = $r->completedBy?->name ?? "User #{$uid}";
            $byUser[$uid]['secs'][] = $sec;
        }
        $out = [];
        foreach ($byUser as $uid => $u) {
            $coll = collect($u['secs']);
            $out[] = [
                'name' => $u['name'],
                'count' => $coll->count(),
                'avg' => $coll->avg(),
            ];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($out, 0, 10);
    }

    /**
     * Heuristische Hinweise: 'dieser Schritt dauert immer > X Tage',
     * 'dieser Approval wird zu 80% genehmigt -> automatisierbar?',
     * 'SLA-Quote ist unter 70%'. Stumpf, aber wertvoll.
     */
    private function suggestions(Workflow $workflow): array
    {
        $hints = [];

        $bottlenecks = $this->bottlenecks($workflow);
        foreach ($bottlenecks as $b) {
            if (($b['avg'] ?? 0) >= 5 * 86400 && $b['n'] >= 5) {
                $hints[] = [
                    'tone' => 'amber',
                    'text' => 'Schritt „'.$b['label'].'" dauert im Durchschnitt mehr als 5 Tage (n='.$b['n'].'). Erwäge Parallel-Approval oder eine zweite Eskalationsstufe.',
                ];
            }
        }

        $sla = $this->slaMetrics($workflow);
        if (($sla['n'] ?? 0) >= 10 && $sla['on_time_pct'] !== null && $sla['on_time_pct'] < 70) {
            $hints[] = [
                'tone' => 'rose',
                'text' => "SLA-Quote bei {$sla['on_time_pct']}% (von {$sla['n']} fristigen Schritten). Fristen sind zu knapp oder Reminder zu schwach.",
            ];
        }

        foreach ($this->decisionDistribution($workflow) as $d) {
            if ($d['total'] >= 10 && $d['approved'] / $d['total'] >= 0.95) {
                $hints[] = [
                    'tone' => 'emerald',
                    'text' => 'Approval „'.$d['label'].'" wird zu '.(int) round($d['approved'] / $d['total'] * 100).'% genehmigt ('.$d['approved'].'/'.$d['total'].'). Kandidat für Automatisierung oder Wegfall.',
                ];
            }
            if ($d['total'] >= 10 && $d['rejected'] / $d['total'] >= 0.5) {
                $hints[] = [
                    'tone' => 'rose',
                    'text' => 'Approval „'.$d['label'].'" wird zu '.(int) round($d['rejected'] / $d['total'] * 100).'% abgelehnt. Eingangs-Validierung vorziehen, um Aufwand zu sparen.',
                ];
            }
        }

        if (empty($hints)) {
            $hints[] = ['tone' => 'slate', 'text' => 'Keine Auffälligkeiten erkannt — der Workflow läuft ruhig.'];
        }
        return $hints;
    }

    /**
     * Schritte mit der längsten durchschnittlichen Bearbeitungszeit
     * (assigned_at -> completed_at).
     */
    private function bottlenecks(Workflow $workflow): array
    {
        $version = $workflow->currentVersion()->first();
        $labels = [];
        foreach (($version?->definition['drawflow']['Home']['data'] ?? []) as $nodeId => $node) {
            $labels[(string) $nodeId] = $node['data']['label'] ?? $nodeId;
        }

        $rows = WorkflowStepExecution::query()
            ->whereHas('instance', fn ($q) => $q->where('workflow_id', $workflow->id))
            ->whereNotNull('completed_at')
            ->orderBy('id')->limit(1000)
            ->get(['step_key', 'assigned_at', 'completed_at']);

        $byKey = [];
        foreach ($rows as $r) {
            $sec = max(0, $r->completed_at->getTimestamp() - $r->assigned_at->getTimestamp());
            $byKey[$r->step_key][] = $sec;
        }

        $out = [];
        foreach ($byKey as $key => $secs) {
            $coll = collect($secs);
            $out[] = [
                'step_key' => $key,
                'label' => $labels[$key] ?? $key,
                'n' => $coll->count(),
                'avg' => $coll->avg(),
                'p95' => $this->percentile($coll, 0.95),
            ];
        }
        usort($out, fn ($a, $b) => ($b['avg'] ?? 0) <=> ($a['avg'] ?? 0));
        return array_slice($out, 0, 5);
    }

    /**
     * Anzahl pro ISO-Woche der letzten 12 Wochen
     * (started_at). Liefert immer 12 Einträge.
     *
     * @return array<int, array{week: string, started: int, completed: int}>
     */
    public function throughputByWeek(?int $workflowId): array
    {
        $weeks = collect(range(11, 0))->map(fn ($i) => now()->startOfWeek()->subWeeks($i));
        $out = [];
        foreach ($weeks as $start) {
            $end = $start->copy()->endOfWeek();
            $startedQ = WorkflowInstance::query()
                ->whereBetween('started_at', [$start, $end]);
            $completedQ = WorkflowInstance::query()
                ->whereBetween('completed_at', [$start, $end])
                ->where('status', WorkflowInstance::STATUS_COMPLETED);
            if ($workflowId) {
                $startedQ->where('workflow_id', $workflowId);
                $completedQ->where('workflow_id', $workflowId);
            }
            $out[] = [
                'week' => $start->isoFormat('GGGG-[W]WW'),
                'started' => $startedQ->count(),
                'completed' => $completedQ->count(),
            ];
        }
        return $out;
    }

    private function percentile(Collection $values, float $p): ?float
    {
        if ($values->isEmpty()) return null;
        $sorted = $values->sort()->values();
        $idx = (int) floor(($sorted->count() - 1) * $p);
        return (float) $sorted->get($idx);
    }
}
