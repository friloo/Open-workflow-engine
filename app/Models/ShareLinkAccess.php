<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLinkAccess extends Model
{
    public const UPDATED_AT = null;
    public $timestamps = false;

    protected $fillable = ['share_link_id', 'ip_address', 'user_agent', 'action', 'success', 'accessed_at'];

    protected $casts = [
        'success' => 'boolean',
        'accessed_at' => 'datetime',
    ];

    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(ShareLink::class);
    }
}
