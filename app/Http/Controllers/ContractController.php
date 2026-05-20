<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filter = (string) $request->get('filter', 'all'); // all|active|notice_due|expired
        $q = trim((string) $request->get('q', ''));

        $query = Contract::query()->with(['owner']);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('party', 'like', "%{$q}%")
                  ->orWhere('category', 'like', "%{$q}%");
            });
        }
        if (in_array($filter, ['active', 'notice_due', 'expired'], true)) {
            $query->where('status', $filter);
        }

        $contracts = $query->orderByRaw("
            CASE status
                WHEN 'notice_due' THEN 1
                WHEN 'expired' THEN 2
                WHEN 'active' THEN 3
                ELSE 4
            END
        ")->orderBy('end_date')->paginate(20)->withQueryString();

        $counts = [
            'all' => Contract::count(),
            'active' => Contract::where('status', 'active')->count(),
            'notice_due' => Contract::where('status', 'notice_due')->count(),
            'expired' => Contract::where('status', 'expired')->count(),
        ];

        return view('contracts.index', compact('contracts', 'counts', 'filter', 'q'));
    }

    public function create(): View
    {
        return view('contracts.form', [
            'contract' => new Contract(['notice_period_days' => 90, 'status' => 'active']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateContract($request);
        $data['created_by'] = $request->user()->id;
        $contract = Contract::create($data);
        $contract->update(['status' => $contract->computedStatus()]);

        $this->audit->log('contract.created', $contract, null, $data,
            'Vertrag ' . $contract->name . ' angelegt', $request->user()->id);

        return redirect()->route('contracts.show', $contract)->with('status', 'Vertrag angelegt.');
    }

    public function show(Contract $contract): View
    {
        $contract->load(['owner', 'creator', 'attachment']);
        return view('contracts.show', compact('contract'));
    }

    public function edit(Contract $contract): View
    {
        return view('contracts.form', [
            'contract' => $contract,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $data = $this->validateContract($request);
        $before = $contract->getAttributes();
        $contract->update($data);
        $contract->update(['status' => $contract->computedStatus()]);

        $this->audit->log('contract.updated', $contract, $before, $data,
            'Vertrag ' . $contract->name . ' aktualisiert', $request->user()->id);

        return redirect()->route('contracts.show', $contract)->with('status', 'Vertrag aktualisiert.');
    }

    public function destroy(Request $request, Contract $contract): RedirectResponse
    {
        $name = $contract->name;
        $contract->delete();
        $this->audit->log('contract.deleted', $contract, null, ['name' => $name],
            'Vertrag ' . $name . ' geloescht (Soft-Delete)', $request->user()->id);
        return redirect()->route('contracts.index')->with('status', 'Vertrag geloescht.');
    }

    private function validateContract(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:65535'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notice_period_days' => ['required', 'integer', 'between:0,3650'],
            'auto_renew' => ['nullable', 'boolean'],
            'auto_renew_months' => ['nullable', 'integer', 'between:1,120'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);
    }
}
