<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_system', 'requires_2fa'];

    protected $casts = [
        'is_system' => 'boolean',
        'requires_2fa' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Role $role) {
            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function lookupLists(): BelongsToMany
    {
        return $this->belongsToMany(LookupList::class, 'lookup_list_role')
            ->withPivot('can_edit')->withTimestamps();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }
}
