<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_id', 'event', 'response_code', 'ok', 'duration_ms',
        'error', 'response_excerpt', 'sent_at',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
