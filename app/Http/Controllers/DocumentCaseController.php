<?php

namespace App\Http\Controllers;

use App\Models\DocumentCase;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentCaseController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $query = DocumentCase::withCount('attachments')->orderBy('name');
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")->orWhere('reference', 'like', "%{$q}%");
            });
        }
        return view('cases.index', ['cases' => $query->paginate(25), 'q' => $q]);
    }

    public function create(): View
    {
        return view('cases.edit', ['case' => new DocumentCase()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCase($request);
        $case = DocumentCase::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('case.created', $case, null, $case->only(['id', 'name', 'reference']),
            "Akte {$case->name} angelegt");
        return redirect()->route('cases.show', $case)->with('status', 'Akte angelegt.');
    }

    public function show(DocumentCase $case): View
    {
        return view('cases.show', [
            'case' => $case->load(['attachments' => fn ($q) => $q->where('is_current_version', true)->orderByDesc('id')]),
        ]);
    }

    public function edit(DocumentCase $case): View
    {
        return view('cases.edit', ['case' => $case]);
    }

    public function update(Request $request, DocumentCase $case): RedirectResponse
    {
        $data = $this->validateCase($request);
        $original = $case->only(array_keys($data));
        $case->update($data);
        $this->audit->log('case.updated', $case, $original, $case->only(array_keys($data)),
            "Akte {$case->name} aktualisiert");
        return redirect()->route('cases.show', $case)->with('status', 'Akte gespeichert.');
    }

    public function close(Request $request, DocumentCase $case): RedirectResponse
    {
        $case->update(['closed_at' => $case->closed_at ? null : now()]);
        return back();
    }

    public function destroy(DocumentCase $case): RedirectResponse
    {
        $name = $case->name;
        $case->delete();
        $this->audit->log('case.deleted', null, ['name' => $name], null, "Akte {$name} geloescht");
        return redirect()->route('cases.index')->with('status', "Akte {$name} geloescht.");
    }

    private function validateCase(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);
    }
}
