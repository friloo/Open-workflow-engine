<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable version snapshot. Created by {@see \App\Services\WorkflowSaver}.
 * Never update existing rows — always create a new version.
 */
class WorkflowVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'workflow_id', 'version_number', 'definition',
        'form_schema', 'change_summary', 'created_by',
    ];

    protected $casts = [
        'definition' => 'array',
        'form_schema' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Workflow versions are immutable.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Workflow versions cannot be deleted.');
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
