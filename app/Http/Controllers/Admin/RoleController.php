<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $roles = Role::withCount('users')->with('permissions')->orderBy('name')->get();

        return view('admin.roles.index', ['roles' => $roles]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'permissions' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        $this->audit->log('role.created', $role, null, [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('slug')->all(),
        ], "Rolle {$role->name} angelegt");

        return redirect()->route('admin.roles.index')->with('status', 'Rolle angelegt.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $oldPermissions = $role->permissions->pluck('slug')->all();

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (! $role->is_system || $role->slug !== 'admin') {
            $role->permissions()->sync($data['permissions'] ?? []);
        }

        $this->audit->log('role.updated', $role, [
            'name' => $role->getOriginal('name'),
            'permissions' => $oldPermissions,
        ], [
            'name' => $role->name,
            'permissions' => $role->fresh('permissions')->permissions->pluck('slug')->all(),
        ], "Rolle {$role->name} aktualisiert");

        return redirect()->route('admin.roles.index')->with('status', 'Rolle aktualisiert.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'Systemrollen koennen nicht geloescht werden.']);
        }

        $snapshot = ['name' => $role->name, 'slug' => $role->slug];
        $role->delete();

        $this->audit->log('role.deleted', $role, $snapshot, null, "Rolle {$snapshot['name']} geloescht");

        return redirect()->route('admin.roles.index')->with('status', 'Rolle geloescht.');
    }
}
