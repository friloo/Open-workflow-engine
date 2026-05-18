<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name', 'type', 'user_id', 'valid_until', 'last_review_at',
        'workflow_id', 'lead_time_days', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'last_review_at' => 'datetime',
        'lead_time_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /**
     * Asset ist zur Pruefung faellig: valid_until - lead_time_days <= heute,
     * und entweder noch nie geprueft oder das letzte Review liegt vor dem
     * aktuellen Pruefzyklus.
     */
    public function isDue(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) return false;
        if (! $this->valid_until) return false;
        $threshold = $this->valid_until->copy()->subDays((int) $this->lead_time_days);
        if (now()->lt($threshold)) return false;
        // Bereits in aktuellem Zyklus geprueft (last_review_at nach threshold)?
        if ($this->last_review_at && $this->last_review_at->gte($threshold)) return false;
        return true;
    }
}
