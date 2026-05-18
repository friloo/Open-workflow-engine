<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox_id', 'uid', 'message_id', 'from_email', 'from_name',
        'subject', 'received_at', 'workflow_instance_id',
        'attachment_count', 'status', 'error',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'attachment_count' => 'integer',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }
}
