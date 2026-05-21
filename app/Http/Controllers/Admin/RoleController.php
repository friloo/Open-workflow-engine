<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LookupList;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\DocumentTypes;
use App\Support\Settings;
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

    public function create(Request $request): View
    {
        // Optional: aus existierender Rolle kopieren — Permissions, Doc-Types,
        // List-Access werden uebernommen, Name+Slug bleiben leer.
        $copyFromId = (int) $request->get('copy_from', 0);
        $copyFrom = $copyFromId ? Role::with('permissions')->find($copyFromId) : null;
        $selectedPermissions = $copyFrom ? $copyFrom->permissions->pluck('slug')->all() : [];

        return view('admin.roles.create', [
            'permissions' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'documentTypes' => DocumentTypes::all(),
            'roleDocumentTypes' => $copyFrom ? DocumentTypes::roleMapping()[$copyFrom->slug] ?? [] : [],
            'lists' => LookupList::orderBy('name')->get(['id', 'name', 'description']),
            'roleListAccess' => $copyFrom ? LookupList::whereHas('roles', fn ($q) => $q->where('roles.id', $copyFrom->id))->pluck('lookup_lists.id')->all() : [],
            'copyFrom' => $copyFrom,
            'selectedPermissions' => $selectedPermissions,
            'allRoles' => Role::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);
        $this->syncDocumentTypes($role, $data['document_types'] ?? []);
        $this->syncLists($role, $data['list_access'] ?? []);

        $this->audit->log('role.created', $role, null, [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('slug')->all(),
            'document_types' => $data['document_types'] ?? [],
            'lists' => array_keys($data['list_access'] ?? []),
        ], "Rolle {$role->name} angelegt");

        return redirect()->route('admin.roles.index')->with('status', 'Rolle angelegt.');
    }

    public function edit(Role $role): View
    {
        // Listen-Pivot: pro Listen-ID merken ob view-only (in pivot) oder view+edit (can_edit=1).
        $roleListAccess = [];
        foreach ($role->lookupLists()->withPivot('can_edit')->get() as $list) {
            $roleListAccess[$list->id] = [
                'access' => true,
                'can_edit' => (bool) $list->pivot->can_edit,
            ];
        }

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'documentTypes' => DocumentTypes::all(),
            'roleDocumentTypes' => DocumentTypes::roleMapping()[$role->slug] ?? [],
            'lists' => LookupList::orderBy('name')->get(['id', 'name', 'description']),
            'roleListAccess' => $roleListAccess,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $oldPermissions = $role->permissions->pluck('slug')->all();
        $oldDocTypes = DocumentTypes::roleMapping()[$role->slug] ?? [];

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (! $role->is_system || $role->slug !== 'admin') {
            $role->permissions()->sync($data['permissions'] ?? []);
        }
        $this->syncDocumentTypes($role, $data['document_types'] ?? []);
        $this->syncLists($role, $data['list_access'] ?? []);

        $this->audit->log('role.updated', $role, [
            'name' => $role->getOriginal('name'),
            'permissions' => $oldPermissions,
            'document_types' => $oldDocTypes,
        ], [
            'name' => $role->name,
            'permissions' => $role->fresh('permissions')->permissions->pluck('slug')->all(),
            'document_types' => $data['document_types'] ?? [],
            'lists' => array_keys($data['list_access'] ?? []),
        ], "Rolle {$role->name} aktualisiert");

        return redirect()->route('admin.roles.index')->with('status', 'Rolle aktualisiert.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
            'document_types' => ['array'],
            'document_types.*' => ['string', 'max:64'],
            'list_access' => ['array'],
            'list_access.*.access' => ['nullable', 'in:1'],
            'list_access.*.can_edit' => ['nullable', 'in:1'],
        ]);
    }

    /**
     * Schreibt die fuer diese Rolle erlaubten Dokumenttypen ins globale
     * Settings-Mapping (attachments.role_document_types). Andere Rollen
     * werden nicht beruehrt.
     */
    private function syncDocumentTypes(Role $role, array $types): void
    {
        $clean = array_values(array_unique(array_filter(array_map('trim', $types))));
        $mapping = (array) Settings::get('attachments.role_document_types', []);
        if (empty($clean)) {
            unset($mapping[$role->slug]);
        } else {
            $mapping[$role->slug] = $clean;
        }
        Settings::set('attachments.role_document_types', $mapping, auth()->id());
    }

    /**
     * Schreibt die Listen-Zugriffe als Pivot. $access ist ein Hash
     * list_id => ['access' => '1'?, 'can_edit' => '1'?]. Ohne 'access'-
     * Flag wird die Liste vom Pivot entfernt.
     */
    private function syncLists(Role $role, array $access): void
    {
        $sync = [];
        foreach ($access as $listId => $perm) {
            if (empty($perm['access'])) continue;
            $sync[(int) $listId] = ['can_edit' => ! empty($perm['can_edit'])];
        }
        $role->lookupLists()->sync($sync);
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
