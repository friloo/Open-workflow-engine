<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\AuditLogger;
use App\Services\WorkflowSimulator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SimulationController extends Controller
{
    public function __construct(
        private readonly WorkflowSimulator $simulator,
        private readonly AuditLogger $audit,
    ) {}

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
        $result = $this->execute($request, $workflow);

        return view('workflows.simulate', [
            'workflow' => $workflow,
            'formSchema' => $result['formSchema'],
            'trace' => $result['trace'],
            'error' => $result['error'],
            'inputData' => $result['input'],
        ]);
    }

    /**
     * Pre-Production-Check als PDF: laeuft die Simulation und rendert
     * Eingabe + Trace in ein druckbares Layout fuer Reviews.
     */
    public function pdf(Request $request, Workflow $workflow): Response
    {
        $result = $this->execute($request, $workflow);
        $version = $workflow->currentVersion()->first();

        $pdf = Pdf::loadView('workflows.simulate_pdf', [
            'workflow' => $workflow,
            'version' => $version,
            'trace' => $result['trace'] ?? [],
            'error' => $result['error'],
            'inputData' => $result['input'],
            'generatedAt' => now(),
            'generatedBy' => $request->user()->name,
        ])->setPaper('a4');

        $this->audit->log('workflow.simulation_exported', $workflow, null,
            ['version' => $version?->version, 'steps' => count($result['trace'] ?? [])],
            "Simulations-PDF fuer Workflow '{$workflow->name}' erzeugt", $request->user()->id);

        $filename = 'workflow-simulation-' . $workflow->id . '-' . now()->format('Ymd-Hi') . '.pdf';
        return $pdf->download($filename);
    }

    private function execute(Request $request, Workflow $workflow): array
    {
        $version = $workflow->currentVersion()->first();
        $formSchema = $version?->form_schema ?? [];

        $input = (array) $request->input('data', []);
        $input = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $input),
            fn ($v) => $v !== '' && $v !== null);

        $result = $this->simulator->simulate($workflow, $input, $request->user());

        return [
            'formSchema' => $formSchema,
            'trace' => $result['trace'],
            'error' => $result['error'],
            'input' => $input,
        ];
    }
}
