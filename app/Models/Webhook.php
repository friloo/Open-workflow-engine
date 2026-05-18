<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Webhook extends Model
{
    public const EVENT_INSTANCE_STARTED = 'instance.started';
    public const EVENT_INSTANCE_COMPLETED = 'instance.completed';
    public const EVENT_INSTANCE_CANCELLED = 'instance.cancelled';
    public const EVENT_INSTANCE_FAILED = 'instance.failed';
    public const EVENT_STEP_COMPLETED = 'step.completed';
    public const EVENT_STEP_ESCALATED = 'step.escalated';

    public const ALL_EVENTS = [
        self::EVENT_INSTANCE_STARTED,
        self::EVENT_INSTANCE_COMPLETED,
        self::EVENT_INSTANCE_CANCELLED,
        self::EVENT_INSTANCE_FAILED,
        self::EVENT_STEP_COMPLETED,
        self::EVENT_STEP_ESCALATED,
    ];

    protected $fillable = [
        'name', 'url', 'events', 'headers', 'secret', 'is_active',
        'last_called_at', 'failure_count', 'created_by',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'last_called_at' => 'datetime',
    ];

    public function setSecretAttribute($value): void
    {
        $this->attributes['secret'] = ($value === null || $value === '')
            ? null : Crypt::encryptString($value);
    }

    public function getSecretAttribute($value): ?string
    {
        if (! $value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
