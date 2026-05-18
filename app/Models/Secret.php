<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Secret extends Model
{
    protected $fillable = ['key', 'value', 'description', 'created_by'];
    protected $hidden = ['value'];

    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public function setValueAttribute($v): void
    {
        $this->attributes['value'] = ($v === null || $v === '')
            ? null : Crypt::encryptString($v);
    }

    public function getValueAttribute($v): ?string
    {
        if (! $v) return null;
        try {
            return Crypt::decryptString($v);
        } catch (\Throwable) {
            return null;
        }
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string,string>
     */
    public static function asMap(): array
    {
        if (self::$cache !== null) return self::$cache;
        self::$cache = [];
        foreach (static::query()->get(['key', 'value']) as $row) {
            if ($row->value !== null) self::$cache[$row->key] = $row->value;
        }
        return self::$cache;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }
}
