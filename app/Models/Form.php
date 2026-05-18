<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'schema',
        'is_public', 'public_slug', 'workflow_id', 'created_by',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Form $f) {
            if (empty($f->slug)) {
                $f->slug = static::uniqueSlug($f->name);
            }
            if ($f->is_public && empty($f->public_slug)) {
                $f->public_slug = $f->slug;
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'formular';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
