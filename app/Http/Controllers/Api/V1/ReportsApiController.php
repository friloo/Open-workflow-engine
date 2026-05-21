<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: KPIs als JSON für BI-Tools (PowerBI, Grafana, Metabase).
 * Token-Ability: reports.view
 */
class ReportsApiController extends Controller
{
    public function __construct(private readonly ReportsService $reports) {}

    public function kpis(Request $request): JsonResponse
    {
        $rangeDays = max(7, min(365, (int) $request->get('days', 30)));
        $since = now()->subDays($rangeDays)->startOfDay();

        return response()->json([
            'range_days' => $rangeDays,
            'since' => $since->toIso8601String(),
            'volume' => $this->reports->volumeSummary($since),
            'lead_times' => $this->reports->leadTimePerWorkflow($since)->all(),
            'sla_violations' => $this->reports->slaViolations()->all(),
            'slowest_steps' => $this->reports->slowestSteps($since)->all(),
            'top_assignees' => $this->reports->topAssignees()->all(),
            'daily_volume' => $this->reports->dailyVolume($since)->all(),
        ]);
    }
}
