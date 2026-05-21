<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Role;
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
        $typeFilter = (int) $request->get('type', 0);
        $q = trim((string) $request->get('q', ''));
        $user = $request->user();

        $query = Contract::query()->visibleTo($user)->with(['owner', 'type']);
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
        if ($typeFilter > 0) {
            $query->where('contract_type_id', $typeFilter);
        }

        $contracts = $query->orderByRaw("
            CASE status
                WHEN 'notice_due' THEN 1
                WHEN 'expired' THEN 2
                WHEN 'active' THEN 3
                ELSE 4
            END
        ")->orderBy('end_date')->paginate(20)->withQueryString();

        // Counts respektieren ebenfalls die Sichtbarkeit
        $base = fn () => Contract::query()->visibleTo($user);
        $counts = [
            'all' => $base()->count(),
            'active' => $base()->where('status', 'active')->count(),
            'notice_due' => $base()->where('status', 'notice_due')->count(),
            'expired' => $base()->where('status', 'expired')->count(),
        ];

        $types = ContractType::orderBy('name')->get();

        return view('contracts.index', compact('contracts', 'counts', 'filter', 'q', 'types', 'typeFilter'));
    }

    public function create(): View
    {
        return view('contracts.form', [
            'contract' => new Contract(['notice_period_days' => 90, 'status' => 'active']),
            'users' => User::humans()->orderBy('name')->get(['id', 'name']),
            'types' => ContractType::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateContract($request);
        $extraRoles = $data['extra_roles'] ?? [];
        unset($data['extra_roles']);
        $data['created_by'] = $request->user()->id;
        $contract = Contract::create($data);
        $contract->update(['status' => $contract->computedStatus()]);
        $this->syncExtraRoles($contract, $extraRoles);

        $this->audit->log('contract.created', $contract, null, $data,
            'Vertrag ' . $contract->name . ' angelegt', $request->user()->id);

        return redirect()->route('contracts.show', $contract)->with('status', 'Vertrag angelegt.');
    }

    public function show(Contract $contract, Request $request): View
    {
        $user = $request->user();
        // Wirkliche Sichtbarkeits-Prüfung (Route-Middleware prüft nur die globale Permission)
        if (! Contract::query()->visibleTo($user)->whereKey($contract->id)->exists()) {
            abort(403, 'Kein Zugriff auf diesen Vertrag.');
        }
        $contract->load(['owner', 'creator', 'attachment', 'type', 'roles', 'attachments.uploader', 'cases']);
        // Auswahl-Liste für "an Akte heften": nur offene Akten, ohne die bereits verknüpften
        $availableCases = \App\Models\DocumentCase::whereNull('closed_at')
            ->whereNotIn('id', $contract->cases->pluck('id'))
            ->orderBy('name')->limit(200)->get(['id', 'name', 'reference']);
        return view('contracts.show', [
            'contract' => $contract,
            'canManage' => $contract->userCanManage($user),
            'availableCases' => $availableCases,
        ]);
    }

    public function edit(Contract $contract, Request $request): View
    {
        if (! $contract->userCanManage($request->user())) {
            abort(403, 'Kein Bearbeitungsrecht für diesen Vertrag.');
        }
        return view('contracts.form', [
            'contract' => $contract->load('roles'),
            'users' => User::humans()->orderBy('name')->get(['id', 'name']),
            'types' => ContractType::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        if (! $contract->userCanManage($request->user())) {
            abort(403, 'Kein Bearbeitungsrecht für diesen Vertrag.');
        }
        $data = $this->validateContract($request);
        $extraRoles = $data['extra_roles'] ?? [];
        unset($data['extra_roles']);
        $before = $contract->getAttributes();
        $contract->update($data);
        $contract->update(['status' => $contract->computedStatus()]);
        $this->syncExtraRoles($contract, $extraRoles);

        $this->audit->log('contract.updated', $contract, $before, $data,
            'Vertrag ' . $contract->name . ' aktualisiert', $request->user()->id);

        return redirect()->route('contracts.show', $contract)->with('status', 'Vertrag aktualisiert.');
    }

    public function destroy(Request $request, Contract $contract): RedirectResponse
    {
        if (! $contract->userCanManage($request->user())) {
            abort(403, 'Kein Bearbeitungsrecht für diesen Vertrag.');
        }
        $name = $contract->name;
        $contract->delete();
        $this->audit->log('contract.deleted', $contract, null, ['name' => $name],
            'Vertrag ' . $name . ' gelöscht (Soft-Delete)', $request->user()->id);
        return redirect()->route('contracts.index')->with('status', 'Vertrag gelöscht.');
    }

    private function validateContract(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'contract_type_id' => ['nullable', 'exists:contract_types,id'],
            'description' => ['nullable', 'string', 'max:65535'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notice_period_days' => ['required', 'integer', 'between:0,3650'],
            'auto_renew' => ['nullable', 'boolean'],
            'auto_renew_months' => ['nullable', 'integer', 'between:1,120'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'extra_roles' => ['array'],
            'extra_roles.*.id' => ['required', 'exists:roles,id'],
            'extra_roles.*.can_view' => ['nullable', 'boolean'],
            'extra_roles.*.can_manage' => ['nullable', 'boolean'],
        ]);
    }

    private function syncExtraRoles(Contract $contract, array $extraRoles): void
    {
        $sync = [];
        foreach ($extraRoles as $row) {
            if (empty($row['can_view'])) continue;
            $sync[(int) $row['id']] = ['can_manage' => ! empty($row['can_manage'])];
        }
        $contract->roles()->sync($sync);
    }

    /**
     * Bulk-Aktion: mehrere Verträge in einem Schritt ändern.
     * Erlaubt: Owner setzen, an Akte heften, Status forcen (Admin).
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contract_ids' => ['required', 'array', 'min:1'],
            'contract_ids.*' => ['integer', 'exists:contracts,id'],
            'action' => ['required', 'in:set_owner,attach_case,detach_case,recompute_status,delete'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'document_case_id' => ['nullable', 'exists:document_cases,id'],
        ]);

        $user = $request->user();
        $contracts = Contract::query()->visibleTo($user)->whereIn('id', $data['contract_ids'])->get();
        if ($contracts->isEmpty()) {
            return back()->withErrors(['contract_ids' => 'Keine sichtbaren/erreichbaren Verträge.']);
        }

        $touched = 0;
        $skipped = 0;
        foreach ($contracts as $c) {
            if (! $c->userCanManage($user)) { $skipped++; continue; }
            switch ($data['action']) {
                case 'set_owner':
                    $c->update(['owner_user_id' => $data['owner_user_id'] ?: null]);
                    $touched++;
                    break;
                case 'attach_case':
                    if (! empty($data['document_case_id'])) {
                        $c->cases()->syncWithoutDetaching([$data['document_case_id']]);
                        $touched++;
                    }
                    break;
                case 'detach_case':
                    if (! empty($data['document_case_id'])) {
                        $c->cases()->detach($data['document_case_id']);
                        $touched++;
                    }
                    break;
                case 'recompute_status':
                    $c->update(['status' => $c->computedStatus()]);
                    $touched++;
                    break;
                case 'delete':
                    $c->delete();
                    $touched++;
                    break;
            }
        }

        $this->audit->log('contract.bulk.' . $data['action'], null, null, [
            'count' => $touched, 'skipped' => $skipped, 'ids' => $contracts->pluck('id')->all(),
        ], "Bulk-Aktion '{$data['action']}' auf {$touched} Verträgen", $user->id);

        $msg = "Bulk-Aktion abgeschlossen: {$touched} bearbeitet.";
        if ($skipped > 0) $msg .= " {$skipped} übersprungen (keine Bearbeitungsrechte).";
        return back()->with('status', $msg);
    }

    /**
     * Vom Vertrag aus eine Akte anhängen — Gegenstueck zu
     * DocumentCaseController::attachContract.
     */
    public function attachCase(Request $request, Contract $contract): RedirectResponse
    {
        if (! $contract->userCanManage($request->user())) abort(403);
        $data = $request->validate(['document_case_id' => ['required', 'exists:document_cases,id']]);
        $contract->cases()->syncWithoutDetaching([$data['document_case_id']]);
        $this->audit->log('contract.case_attached', $contract, null, $data,
            'Vertrag ' . $contract->name . ' an Akte geheftet', $request->user()->id);
        return back()->with('status', 'Akte zugeordnet.');
    }

    public function detachCase(Request $request, Contract $contract, int $caseId): RedirectResponse
    {
        if (! $contract->userCanManage($request->user())) abort(403);
        $contract->cases()->detach($caseId);
        return back()->with('status', 'Akte entfernt.');
    }
}
