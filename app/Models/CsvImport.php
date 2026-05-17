<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImport extends Model
{
    protected $fillable = [
        'target', 'user_id', 'original_filename',
        'rows_total', 'rows_imported', 'rows_skipped', 'rows_failed',
        'errors', 'mapping',
    ];

    protected $casts = [
        'errors' => 'array',
        'mapping' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
