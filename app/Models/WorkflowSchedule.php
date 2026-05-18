<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowSchedule extends Model
{
    protected $fillable = [
        'workflow_id', 'subject_user_id', 'subject_label', 'payload',
        'interval_value', 'interval_unit',
        'next_run_at', 'last_run_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advance(CarbonInterface $from): void
    {
        $this->last_run_at = $from;
        $this->next_run_at = match ($this->interval_unit) {
            'days' => $from->copy()->addDays($this->interval_value),
            'weeks' => $from->copy()->addWeeks($this->interval_value),
            'months' => $from->copy()->addMonths($this->interval_value),
            'years' => $from->copy()->addYears($this->interval_value),
        };
        $this->save();
    }
}
