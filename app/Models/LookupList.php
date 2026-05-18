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
