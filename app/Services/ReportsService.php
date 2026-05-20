<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liefert KPI-Daten fuer das Reports-Dashboard:
 * - Workflow-Durchlaufzeiten (Median + Mittel)
 * - SLA-Verletzungen (Tasks die ihr due_at ueberschritten haben)
 * - Top-Verzoegerer-Knoten (welcher Knoten haelt am meisten auf)
 * - Volumen-Trends (Instanzen pro Tag/Woche/Monat)
 * - Top-Empfaenger (offene Aufgaben pro User / Rolle)
 *
 * Alle Methoden akzeptieren ein optionales Zeitfenster (Carbon-Datum
 * "ab"). Default: letzte 30 Tage.
 */
class ReportsService
{
    public function __construct() {}

    public function defaultSince(): Carbon
    {
        return now()->subDays(30)->startOfDay();
    }

    /** Anzahl Instanzen + Abschlussquote im Zeitfenster. */
    public function volumeSummary(?Carbon $since = null): array
    {
        $since = $since ?? $this->defaultSince();
        $base = WorkflowInstance::query()->where('started_at', '>=', $since);

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $cancelled = (clone $base)->where('status', 'cancelled')->count();
        $failed = (clone $base)->where('status', 'failed')->count();
        $running = (clone $base)->where('status', 'running')->count();

        $completionRate = $total > 0 ? round($completed / $total * 100, 1) : 0.0;

        return compact('total', 'completed', 'cancelled', 'failed', 'running', 'completionRate');
    }

    /**
     * Durchlaufzeiten pro Workflow: Median + Mittelwert in Stunden,
     * basierend auf completed_at - started_at von abgeschlossenen Instanzen.
     *
     * @return Collection<int, array{workflow_id:int, name:string, n:int, median_h:float, avg_h:float}>
     */
    public function leadTimePerWorkflow(?Carbon $since = null): Collection
    {
        $since = $since ?? $this->defaultSince();

        $rows = WorkflowInstance::query()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('started_at', '>=', $since)
            ->with('workflow:id,name')
            ->get(['id', 'workflow_id', 'started_at', 'completed_at']);

        $byWorkflow = $rows->groupBy('workflow_id');

        return $byWorkflow->map(function (Collection $group) {
            $hours = $group->map(fn ($i) => $i->started_at->diffInMinutes($i->completed_at) / 60)
                ->sort()->values();
            $first = $group->first();
            return [
                'workflow_id' => $first->workflow_id,
                'name' => $first->workflow?->name ?? '—',
                'n' => $hours->count(),
                'median_h' => round(self::median($hours->all()), 2),
                'avg_h' => round($hours->avg(), 2),
            ];
        })->sortByDesc('median_h')->values();
    }

    /**
     * SLA-Verletzungen: aktuell offene Steps die ihr due_at ueberschritten
     * haben. Pro Workflow zaehlen.
     *
     * @return Collection<int, array{workflow_id:int, name:string, overdue:int}>
     */
    public function slaViolations(): Collection
    {
        return WorkflowStepExecution::query()
            ->whereNull('workflow_step_executions.completed_at')
            ->whereNotNull('workflow_step_executions.due_at')
            ->where('workflow_step_executions.due_at', '<', now())
            ->join('workflow_instances', 'workflow_step_executions.workflow_instance_id', '=', 'workflow_instances.id')
            ->join('workflows', 'workflow_instances.workflow_id', '=', 'workflows.id')
            ->groupBy('workflows.id', 'workflows.name')
            ->select('workflows.id as workflow_id', 'workflows.name', DB::raw('COUNT(*) as overdue'))
            ->orderByDesc('overdue')
            ->get()
            ->map(fn ($r) => [
                'workflow_id' => (int) $r->workflow_id,
                'name' => $r->name,
                'overdue' => (int) $r->overdue,
            ]);
    }

    /**
     * Welcher Knoten in welchem Workflow blockiert am laengsten?
     * Misst Aufgaben-Bearbeitungszeit (completed_at - assigned_at) im
     * Zeitfenster.
     *
     * @return Collection<int, array{workflow_name:string, step_key:string, n:int, median_h:float}>
     */
    public function slowestSteps(?Carbon $since = null, int $limit = 10): Collection
    {
        $since = $since ?? $this->defaultSince();

        $rows = WorkflowStepExecution::query()
            ->whereNotNull('workflow_step_executions.completed_at')
            ->whereNotNull('workflow_step_executions.assigned_at')
            ->where('workflow_step_executions.completed_at', '>=', $since)
            ->join('workflow_instances', 'workflow_step_executions.workflow_instance_id', '=', 'workflow_instances.id')
            ->join('workflows', 'workflow_instances.workflow_id', '=', 'workflows.id')
            ->select(
                'workflow_step_executions.step_key',
                'workflow_step_executions.assigned_at',
                'workflow_step_executions.completed_at',
                'workflows.name as workflow_name',
            )
            ->get();

        $grouped = $rows->groupBy(fn ($r) => $r->workflow_name.'#'.$r->step_key);

        return $grouped->map(function (Collection $group) {
            $hours = $group->map(fn ($r) => Carbon::parse($r->assigned_at)->diffInMinutes(Carbon::parse($r->completed_at)) / 60)
                ->sort()->values();
            $first = $group->first();
            return [
                'workflow_name' => $first->workflow_name,
                'step_key' => $first->step_key,
                'n' => $hours->count(),
                'median_h' => round(self::median($hours->all()), 2),
            ];
        })->sortByDesc('median_h')->take($limit)->values();
    }

    /**
     * Top-N Mitarbeiter mit den meisten offenen Aufgaben.
     *
     * @return Collection<int, array{user_id:?int, name:string, open:int, overdue:int}>
     */
    public function topAssignees(int $limit = 10): Collection
    {
        return WorkflowStepExecution::query()
            ->whereNull('completed_at')
            ->whereNotNull('assigned_to_user_id')
            ->leftJoin('users', 'workflow_step_executions.assigned_to_user_id', '=', 'users.id')
            ->groupBy('users.id', 'users.name')
            ->select(
                'users.id as user_id',
                'users.name',
                DB::raw('COUNT(*) as open'),
                DB::raw('SUM(CASE WHEN workflow_step_executions.due_at IS NOT NULL AND workflow_step_executions.due_at < CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as overdue'),
            )
            ->orderByDesc('open')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id ? (int) $r->user_id : null,
                'name' => $r->name ?? 'Unbekannt',
                'open' => (int) $r->open,
                'overdue' => (int) $r->overdue,
            ]);
    }

    /**
     * Volumen pro Tag im Zeitfenster — fuer ein einfaches Line-Chart.
     *
     * @return Collection<int, array{date:string, started:int, completed:int}>
     */
    public function dailyVolume(?Carbon $since = null): Collection
    {
        $since = $since ?? now()->subDays(29)->startOfDay();

        // SQLite: date(...) — MySQL: DATE(...). Beide reagieren auf die
        // gleiche SQL-Funktion via DB::raw und liefern YYYY-MM-DD-Strings.
        $started = WorkflowInstance::query()
            ->where('started_at', '>=', $since)
            ->select(DB::raw("DATE(started_at) as d"), DB::raw('COUNT(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        $completed = WorkflowInstance::query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->select(DB::raw("DATE(completed_at) as d"), DB::raw('COUNT(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        $out = collect();
        $cur = $since->copy();
        $end = now()->endOfDay();
        while ($cur->lessThanOrEqualTo($end)) {
            $key = $cur->format('Y-m-d');
            $out->push([
                'date' => $key,
                'started' => (int) ($started[$key] ?? 0),
                'completed' => (int) ($completed[$key] ?? 0),
            ]);
            $cur->addDay();
        }
        return $out;
    }

    private static function median(array $values): float
    {
        if (empty($values)) return 0.0;
        sort($values);
        $n = count($values);
        $mid = (int) floor($n / 2);
        return $n % 2 ? (float) $values[$mid] : (float) (($values[$mid - 1] + $values[$mid]) / 2);
    }
}
