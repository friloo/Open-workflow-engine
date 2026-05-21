<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Services\AttachmentStorage;
use App\Services\AuditLogger;
use App\Services\ContractTemplateRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ContractTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('contracts.templates.index', [
            'templates' => ContractTemplate::with('type', 'creator')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('contracts.templates.form', [
            'template' => new ContractTemplate([
                'body_html' => "<h1>{{ name }}</h1>\n<p>Vertragspartner: <strong>{{ party }}</strong></p>\n<p>Beginn: {{ start_date }} · Ende: {{ end_date }}</p>\n<p>Kuendigungsfrist: {{ notice_period_days }} Tage</p>\n<p>Verantwortlich: {{ owner.name }} ({{ owner.email }})</p>",
            ]),
            'types' => ContractType::orderBy('name')->get(),
            'placeholders' => app(ContractTemplateRenderer::class)
                ->variablesFor(new Contract([
                    'name' => 'Beispiel-Vertrag', 'party' => 'Mustermann GmbH',
                    'notice_period_days' => 90,
                ])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);
        $template = ContractTemplate::create([...$data, 'created_by' => $request->user()->id]);
        $this->audit->log('contract_template.created', $template, null, ['name' => $template->name],
            "Vertrags-Vorlage {$template->name} angelegt", $request->user()->id);
        return redirect()->route('contract-templates.index')->with('status', 'Vorlage angelegt.');
    }

    public function edit(ContractTemplate $contractTemplate): View
    {
        return view('contracts.templates.form', [
            'template' => $contractTemplate,
            'types' => ContractType::orderBy('name')->get(),
            'placeholders' => app(ContractTemplateRenderer::class)
                ->variablesFor(new Contract([
                    'name' => 'Beispiel-Vertrag', 'party' => 'Mustermann GmbH',
                    'notice_period_days' => 90,
                ])),
        ]);
    }

    public function update(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        $contractTemplate->update($this->validateTemplate($request));
        $this->audit->log('contract_template.updated', $contractTemplate, null, ['name' => $contractTemplate->name],
            "Vertrags-Vorlage {$contractTemplate->name} aktualisiert", $request->user()->id);
        return redirect()->route('contract-templates.index')->with('status', 'Vorlage aktualisiert.');
    }

    public function destroy(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        $name = $contractTemplate->name;
        $contractTemplate->delete();
        $this->audit->log('contract_template.deleted', null, ['name' => $name], null,
            "Vertrags-Vorlage {$name} geloescht", $request->user()->id);
        return redirect()->route('contract-templates.index')->with('status', 'Vorlage geloescht.');
    }

    /**
     * Erzeugt aus einer Vorlage + einem Vertrag eine PDF und haengt
     * sie als Anhang an den Vertrag.
     */
    public function generate(Request $request, Contract $contract, ContractTemplateRenderer $renderer, AttachmentStorage $storage): RedirectResponse
    {
        if (! $contract->userCanManage($request->user())) abort(403);
        $data = $request->validate(['template_id' => ['required', 'exists:contract_templates,id']]);

        $template = ContractTemplate::findOrFail($data['template_id']);
        $html = $renderer->render($template, $contract);

        $pdfBytes = Pdf::loadView('contracts.templates.print', [
            'title' => $contract->name,
            'body' => $html,
        ])->setPaper('a4')->output();

        $filename = 'vertrag-' . \Illuminate\Support\Str::slug($contract->name) . '-' . now()->format('Ymd') . '.pdf';
        try {
            $att = $storage->storeBytes(
                $pdfBytes, $filename, 'application/pdf',
                $contract, 'Aus Vorlage: ' . $template->name, $request->user()->id,
                null, allowDuplicate: true,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['template' => 'PDF konnte nicht erzeugt werden: ' . $e->getMessage()]);
        }

        $this->audit->log('contract.pdf_generated', $contract, null,
            ['template_id' => $template->id, 'attachment_id' => $att->id],
            "PDF aus Vorlage {$template->name} fuer Vertrag {$contract->name} erzeugt",
            $request->user()->id);

        return redirect()->route('contracts.show', $contract)
            ->with('status', 'PDF aus Vorlage ' . $template->name . ' erzeugt und angehaengt.');
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'contract_type_id' => ['nullable', 'exists:contract_types,id'],
            'body_html' => ['required', 'string', 'max:200000'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);
    }
}
