<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepExecution extends Model
{
    protected $fillable = [
        'workflow_instance_id', 'step_key', 'step_type',
        'assigned_to_user_id', 'assigned_to_role_id',
        'assigned_at', 'due_at', 'completed_at', 'completed_by',
        'decision', 'comment', 'data_snapshot', 'escalated_from_step_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'data_snapshot' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_to_role_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
