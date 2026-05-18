<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\WorkflowSimulator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SimulationController extends Controller
{
    public function __construct(private readonly WorkflowSimulator $simulator) {}

    public function show(Workflow $workflow): View
    {
        $version = $workflow->currentVersion()->first();
        return view('workflows.simulate', [
            'workflow' => $workflow,
            'formSchema' => $version?->form_schema ?? [],
            'trace' => null,
            'error' => null,
            'inputData' => [],
        ]);
    }

    public function run(Request $request, Workflow $workflow): View
    {
        $version = $workflow->currentVersion()->first();
        $formSchema = $version?->form_schema ?? [];

        $input = (array) $request->input('data', []);
        // Strings trimmen, leere wegfiltern
        $input = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $input),
            fn ($v) => $v !== '' && $v !== null);

        $result = $this->simulator->simulate($workflow, $input, $request->user());

        return view('workflows.simulate', [
            'workflow' => $workflow,
            'formSchema' => $formSchema,
            'trace' => $result['trace'],
            'error' => $result['error'],
            'inputData' => $input,
        ]);
    }
}
