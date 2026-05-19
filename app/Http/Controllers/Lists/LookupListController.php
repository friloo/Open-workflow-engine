<?php

namespace App\Http\Controllers\Lists;

use App\Http\Controllers\Controller;
use App\Models\LookupList;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LookupListController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $lists = LookupList::visibleTo($request->user())
            ->withCount('entries')->orderBy('name')->paginate(25);
        return view('lists.index', compact('lists'));
    }

    public function create(): View
    {
        return view('lists.edit', [
            'list' => new LookupList(['columns' => $this->defaultColumns()]),
            'allRoles' => \App\Models\Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateList($request);
        $rolePerms = $data['role_perms'] ?? [];
        unset($data['role_perms']);
        $data['columns'] = $this->normalizeColumns($data['columns']);
        $this->ensureKeyColumn($data['columns']);

        $list = LookupList::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);
        $this->syncRolePermissions($list, $rolePerms);
        $this->audit->log('list.created', $list, null, $list->only(['id', 'name', 'slug']),
            "Liste {$list->name} angelegt");

        return redirect()->route('lists.edit', $list)->with('status', 'Liste angelegt.');
    }

    public function edit(LookupList $list, Request $request): View
    {
        abort_unless($list->visibleForUser($request->user()), 403);
        $entries = $list->entries()->orderBy('key_value')->paginate(50);
        return view('lists.edit', [
            'list' => $list,
            'entries' => $entries,
            'allRoles' => \App\Models\Role::orderBy('name')->get(),
            'canEdit' => $list->editableByUser($request->user()),
        ]);
    }

    public function update(Request $request, LookupList $list): RedirectResponse
    {
        abort_unless($list->editableByUser($request->user()), 403);
        $data = $this->validateList($request, $list);
        $rolePerms = $data['role_perms'] ?? [];
        unset($data['role_perms']);
        $data['columns'] = $this->normalizeColumns($data['columns']);
        $this->ensureKeyColumn($data['columns']);

        $original = $list->only(array_keys($data));
        $list->update($data);
        $this->syncRolePermissions($list, $rolePerms);

        $this->audit->log('list.updated', $list, $original, $list->only(array_keys($data)),
            "Liste {$list->name} aktualisiert");

        return back()->with('status', 'Liste gespeichert.');
    }

    public function destroy(LookupList $list, Request $request): RedirectResponse
    {
        abort_unless($list->editableByUser($request->user()), 403);
        $snapshot = $list->only(['id', 'name', 'slug']);
        $list->delete();
        $this->audit->log('list.deleted', null, $snapshot, null, "Liste {$snapshot['name']} geloescht");
        return redirect()->route('lists.index')->with('status', 'Liste geloescht.');
    }

    private function syncRolePermissions(LookupList $list, array $rolePerms): void
    {
        $sync = [];
        foreach ($rolePerms as $roleId => $perm) {
            $roleId = (int) $roleId;
            if ($roleId <= 0) continue;
            $access = (string) ($perm['access'] ?? 'none');
            if ($access === 'none') continue;
            $sync[$roleId] = ['can_edit' => $access === 'edit'];
        }
        $list->roles()->sync($sync);
    }

    private function defaultColumns(): array
    {
        return [
            ['key' => 'kostenstelle', 'label' => 'Kostenstelle', 'type' => 'text', 'role' => LookupList::ROLE_KEY],
            ['key' => 'responsible_email', 'label' => 'Verantwortlich (E-Mail)', 'type' => 'email', 'role' => LookupList::ROLE_RESPONSIBLE],
            ['key' => 'escalation_email', 'label' => 'Eskalation (E-Mail)', 'type' => 'email', 'role' => LookupList::ROLE_ESCALATION],
        ];
    }

    private function validateList(Request $request, ?LookupList $list = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', Rule::unique('lookup_lists', 'slug')->ignore($list?->id)->whereNull('deleted_at')],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns.*.label' => ['required', 'string', 'max:128'],
            'columns.*.type' => ['required', 'in:text,email,number'],
            'columns.*.role' => ['required', 'in:key,responsible,escalation,other'],
            'role_perms' => ['nullable', 'array'],
            'role_perms.*.access' => ['nullable', 'in:none,view,edit'],
        ]);
    }

    private function normalizeColumns(array $cols): array
    {
        // Re-index numerically and trim
        return array_values(array_map(function ($c) {
            return [
                'key' => $c['key'],
                'label' => $c['label'],
                'type' => $c['type'],
                'role' => $c['role'],
            ];
        }, $cols));
    }

    private function ensureKeyColumn(array $cols): void
    {
        $keyCount = collect($cols)->where('role', LookupList::ROLE_KEY)->count();
        if ($keyCount !== 1) {
            abort(redirect()->back()->withInput()->withErrors([
                'columns' => 'Es muss genau eine Spalte mit Rolle "Schluessel" geben.',
            ]));
        }
    }
}
