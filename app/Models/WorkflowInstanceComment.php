<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceComment extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['workflow_instance_id', 'user_id', 'body'];
    protected $casts = ['created_at' => 'datetime'];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
