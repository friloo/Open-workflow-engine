<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'created_by'];

    protected static function booted(): void
    {
        static::saving(function (Tag $t) {
            if (empty($t->slug)) {
                $base = Str::slug($t->name) ?: 'tag';
                $slug = $base;
                $i = 2;
                while (self::where('slug', $slug)->where('id', '!=', $t->id ?? 0)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $t->slug = $slug;
            }
        });
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class);
    }
}
