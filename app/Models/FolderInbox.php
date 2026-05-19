<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderInbox extends Model
{
    protected $fillable = [
        'name', 'path', 'use_storage_disk',
        'document_type', 'workflow_id',
        'after_import', 'processed_subfolder',
        'extensions', 'is_active',
        'last_scan_at', 'last_status', 'last_error', 'created_by',
    ];

    protected $casts = [
        'use_storage_disk' => 'boolean',
        'is_active' => 'boolean',
        'extensions' => 'array',
        'last_scan_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function absolutePath(): string
    {
        return $this->use_storage_disk
            ? storage_path('app/'.ltrim($this->path, '/\\'))
            : $this->path;
    }
}
