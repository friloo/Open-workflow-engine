<?php

namespace App\Http\Controllers;

use App\Models\ContractType;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Verwaltung der Vertragsarten (Mietvertrag, Wartung, Lizenz ...).
 * Pro Typ: Default-Kuendigungsfrist + Rollen mit Sicht/Manage-Rechten.
 */
class ContractTypeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('contracts.types.index', [
            'types' => ContractType::with('roles')->withCount('contracts')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('contracts.types.form', [
            'type' => new ContractType(['default_notice_period_days' => 90, 'color' => '#64748b']),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateType($request);
        $type = ContractType::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) ?: 'typ-'.Str::random(6),
            'color' => $data['color'] ?? '#64748b',
            'default_notice_period_days' => $data['default_notice_period_days'] ?? 90,
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->syncRoles($type, $request);
        $this->audit->log('contract_type.created', $type, null, $type->only(['id', 'name']),
            'Vertragsart ' . $type->name . ' angelegt', $request->user()->id);
        return redirect()->route('contract-types.index')->with('status', 'Vertragsart angelegt.');
    }

    public function edit(ContractType $contractType): View
    {
        return view('contracts.types.form', [
            'type' => $contractType->load('roles'),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ContractType $contractType): RedirectResponse
    {
        $data = $this->validateType($request);
        $contractType->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#64748b',
            'default_notice_period_days' => $data['default_notice_period_days'] ?? 90,
            'description' => $data['description'] ?? null,
        ]);
        $this->syncRoles($contractType, $request);
        $this->audit->log('contract_type.updated', $contractType, null, $contractType->only(['id', 'name']),
            'Vertragsart ' . $contractType->name . ' aktualisiert', $request->user()->id);
        return redirect()->route('contract-types.index')->with('status', 'Vertragsart aktualisiert.');
    }

    public function destroy(Request $request, ContractType $contractType): RedirectResponse
    {
        if ($contractType->contracts()->exists()) {
            return back()->withErrors(['type' => 'Es existieren noch Vertraege dieses Typs. Erst entfernen oder umsortieren.']);
        }
        $name = $contractType->name;
        $contractType->delete();
        $this->audit->log('contract_type.deleted', null, ['name' => $name], null,
            'Vertragsart ' . $name . ' geloescht', $request->user()->id);
        return redirect()->route('contract-types.index')->with('status', 'Vertragsart geloescht.');
    }

    private function validateType(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'color' => ['nullable', 'string', 'max:16'],
            'default_notice_period_days' => ['nullable', 'integer', 'between:0,3650'],
            'description' => ['nullable', 'string', 'max:4000'],
            'roles' => ['array'],
            'roles.*.id' => ['required', 'exists:roles,id'],
            'roles.*.can_view' => ['nullable', 'boolean'],
            'roles.*.can_manage' => ['nullable', 'boolean'],
        ]);
    }

    private function syncRoles(ContractType $type, Request $request): void
    {
        $sync = [];
        foreach ((array) $request->input('roles', []) as $row) {
            $canView = ! empty($row['can_view']);
            if (! $canView) continue; // Nur explizit aktivierte Rollen
            $sync[(int) $row['id']] = ['can_manage' => ! empty($row['can_manage'])];
        }
        $type->roles()->sync($sync);
    }
}
