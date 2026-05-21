<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('q', ''));
        $users = User::with('roles', 'supervisor')
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'supervisors' => User::humans()->orderBy('name')->get(['id', 'name', 'email']),
            'customFields' => \App\Support\Settings::get('users.custom_fields', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);
        $roleIds = collect($request->input('roles', []))->map(fn ($v) => (int) $v)->all();

        $user = User::create([
            ...$data,
            'password' => $request->filled('password') ? $request->input('password') : null,
            'created_by' => $request->user()->id,
        ]);

        $user->syncRoles($roleIds, $request->user()->id);

        $this->audit->log('user.created', $user, null, $user->only([
            'id', 'name', 'email', 'is_active', 'supervisor_id',
        ]), "Benutzer {$user->email} angelegt");

        return redirect()->route('admin.users.index')->with('status', 'Benutzer angelegt.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('name')->get(),
            'supervisors' => User::humans()->where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name', 'email']),
            'customFields' => \App\Support\Settings::get('users.custom_fields', []),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateUser($request, $user);
        $original = $user->only(array_keys($data));

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $user->update($data);

        if ($request->has('roles')) {
            $roleIds = collect($request->input('roles', []))->map(fn ($v) => (int) $v)->all();
            $user->syncRoles($roleIds, $request->user()->id);
        }

        $this->audit->log('user.updated', $user, $original, $user->only(array_keys($data)), "Benutzer {$user->email} aktualisiert");

        return redirect()->route('admin.users.index')->with('status', 'Benutzer aktualisiert.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Sie koennen sich nicht selbst loeschen.']);
        }

        $snapshot = $user->only(['id', 'name', 'email']);
        $user->delete();

        $this->audit->log('user.deleted', $user, $snapshot, null, "Benutzer {$snapshot['email']} geloescht");

        return redirect()->route('admin.users.index')->with('status', 'Benutzer geloescht.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)->whereNull('deleted_at')],
            'password' => [$user ? 'nullable' : 'nullable', 'string', 'min:8'],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'employee_id' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'is_service_account' => ['nullable', 'boolean'],
            'email_notifications_enabled' => ['nullable', 'boolean'],
            'prefer_m365_supervisor' => ['nullable', 'boolean'],
            'custom_fields' => ['nullable', 'array'],
        ]) + [
            'is_active' => $request->boolean('is_active', true),
            'is_service_account' => $request->boolean('is_service_account', false),
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled', true),
            'prefer_m365_supervisor' => $request->boolean('prefer_m365_supervisor', false),
        ];

        // Custom-Felder: nur konfigurierte Keys mit korrektem Typ uebernehmen.
        $defined = \App\Support\Settings::get('users.custom_fields', []);
        $cf = [];
        $input = $request->input('custom_fields', []);
        foreach ($defined as $f) {
            $key = $f['key'];
            $val = $input[$key] ?? null;
            if ($val === '' || $val === null) continue;
            if ($f['type'] === 'number') $val = is_numeric($val) ? +$val : null;
            $cf[$key] = $val;
        }
        $data['custom_fields'] = $cf ?: null;
        return $data;
    }
}
