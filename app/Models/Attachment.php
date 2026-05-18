<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = [
        'attachable_type', 'attachable_id', 'original_name', 'disk',
        'path', 'mime_type', 'size', 'label', 'uploaded_by',
    ];

    protected $casts = ['size' => 'integer'];

    protected static function booted(): void
    {
        static::deleting(function (Attachment $a) {
            try {
                Storage::disk($a->disk)->delete($a->path);
            } catch (\Throwable) {
                // best-effort
            }
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sizeFormatted(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $s = $this->size;
        $u = 0;
        while ($s >= 1024 && $u < count($units) - 1) { $s /= 1024; $u++; }
        return round($s, $u === 0 ? 0 : 1).' '.$units[$u];
    }

    public function isImage(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
