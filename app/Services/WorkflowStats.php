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
        ];
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
