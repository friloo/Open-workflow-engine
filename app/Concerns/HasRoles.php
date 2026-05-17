<?php

namespace App\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function hasRole(string|array $slugs): bool
    {
        $slugs = is_array($slugs) ? $slugs : [$slugs];

        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->permissions()->contains('slug', $slug);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->permissions()->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function permissions(): Collection
    {
        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->unique('id')
            ->values();
    }

    public function assignRole(Role|string $role, ?int $assignedBy = null): void
    {
        $role = $role instanceof Role ? $role : Role::where('slug', $role)->firstOrFail();

        $this->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ],
        ]);

        $this->load('roles.permissions');
    }

    public function removeRole(Role|string $role): void
    {
        $role = $role instanceof Role ? $role : Role::where('slug', $role)->firstOrFail();

        $this->roles()->detach($role->id);
        $this->load('roles.permissions');
    }

    public function syncRoles(array $roleIds, ?int $assignedBy = null): void
    {
        $payload = collect($roleIds)->mapWithKeys(fn ($id) => [
            $id => ['assigned_by' => $assignedBy, 'assigned_at' => now()],
        ])->all();

        $this->roles()->sync($payload);
        $this->load('roles.permissions');
    }
}
