<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit log. Each entry is chained via `prev_hash` so that
 * tampering with a row (or removing one) breaks the integrity chain.
 *
 * Never update or delete records directly. Use {@see \App\Services\AuditLogger}.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'prev_hash',
        'hash',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Audit logs are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit logs cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
