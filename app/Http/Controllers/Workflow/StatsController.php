<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\WorkflowStats;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __construct(private readonly WorkflowStats $stats) {}

    public function index(): View
    {
        return view('workflows.stats.index', [
            'global' => $this->stats->global(),
            'workflows' => Workflow::orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function show(Workflow $workflow): View
    {
        return view('workflows.stats.show', [
            'workflow' => $workflow,
            'stats' => $this->stats->forWorkflow($workflow),
        ]);
    }
}
