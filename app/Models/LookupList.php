<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LookupList extends Model
{
    use SoftDeletes;

    public const ROLE_KEY = 'key';
    public const ROLE_RESPONSIBLE = 'responsible';
    public const ROLE_ESCALATION = 'escalation';
    public const ROLE_OTHER = 'other';

    protected $fillable = ['name', 'slug', 'description', 'columns', 'created_by'];
    protected $casts = ['columns' => 'array'];

    protected static function booted(): void
    {
        static::saving(function (LookupList $list) {
            if (empty($list->slug)) {
                $list->slug = static::uniqueSlug($list->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'liste';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LookupListEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'lookup_list_role')->withPivot('can_edit')->withTimestamps();
    }

    /**
     * Sichtbarkeit: wenn die Liste KEINE Rollen-Eintraege hat, ist sie fuer
     * alle mit lists.view sichtbar (Backward-Compat). Wenn Eintraege da sind,
     * darf nur sehen wer in mindestens einer der zugeordneten Rollen ist.
     * Admins sehen alles.
     */
    public function visibleForUser(?User $user): bool
    {
        if (! $user) return false;
        if ($user->hasRole('admin')) return true;
        if (! $user->hasPermission('lists.view')) return false;
        if ($this->roles()->count() === 0) return true;
        $userRoleIds = $user->roles->pluck('id')->all();
        return $this->roles()->whereIn('roles.id', $userRoleIds)->exists();
    }

    /**
     * Editierbar nur wenn lists.manage UND (entweder Admin / keine
     * Einschraenkungen / can_edit-Pivot fuer eine User-Rolle).
     */
    public function editableByUser(?User $user): bool
    {
        if (! $user) return false;
        if ($user->hasRole('admin')) return true;
        if (! $user->hasPermission('lists.manage')) return false;
        if ($this->roles()->count() === 0) return true;
        $userRoleIds = $user->roles->pluck('id')->all();
        return $this->roles()
            ->wherePivot('can_edit', true)
            ->whereIn('roles.id', $userRoleIds)
            ->exists();
    }

    /** Scope: nur Listen die der User sehen darf. */
    public function scopeVisibleTo($query, ?User $user)
    {
        if (! $user) return $query->whereRaw('1=0');
        if ($user->hasRole('admin')) return $query;
        $roleIds = $user->roles->pluck('id')->all();
        return $query->where(function ($q) use ($roleIds) {
            // Listen ohne Rollen-Beschraenkung
            $q->whereDoesntHave('roles');
            // ODER mit Rollen-Treffer
            if ($roleIds) {
                $q->orWhereHas('roles', fn ($r) => $r->whereIn('roles.id', $roleIds));
            }
        });
    }

    /** @return array<string,mixed>|null */
    public function lookup(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') return null;
        // Erst exakte Suche, dann case-insensitive Fallback
        $entry = $this->entries()->where('key_value', $key)->first()
            ?? $this->entries()->whereRaw('LOWER(key_value) = ?', [mb_strtolower($key)])->first();
        return $entry?->data;
    }

    public function keyColumn(): ?array
    {
        return collect($this->columns)->firstWhere('role', self::ROLE_KEY);
    }

    public function columnForRole(string $role): ?array
    {
        return collect($this->columns)->firstWhere('role', $role);
    }

    public function emailForRole(string $key, string $role): ?string
    {
        $col = $this->columnForRole($role);
        if (! $col) return null;
        $row = $this->lookup($key);
        if (! $row) return null;
        $v = $row[$col['key']] ?? null;
        return $v ? strtolower((string) $v) : null;
    }
}
