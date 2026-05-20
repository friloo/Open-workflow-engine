<?php

namespace App\Http\Controllers;

use App\Services\ReportsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(private readonly ReportsService $reports) {}

    public function index(Request $request): View
    {
        $rangeDays = (int) $request->get('days', 30);
        $rangeDays = max(7, min(365, $rangeDays));
        $since = now()->subDays($rangeDays)->startOfDay();

        return view('reports.index', [
            'rangeDays' => $rangeDays,
            'volume' => $this->reports->volumeSummary($since),
            'leadTimes' => $this->reports->leadTimePerWorkflow($since),
            'slaViolations' => $this->reports->slaViolations(),
            'slowestSteps' => $this->reports->slowestSteps($since),
            'topAssignees' => $this->reports->topAssignees(),
            'daily' => $this->reports->dailyVolume($since),
        ]);
    }
}
