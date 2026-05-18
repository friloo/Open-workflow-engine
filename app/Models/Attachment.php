<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attachable_type', 'attachable_id', 'original_name', 'disk',
        'path', 'mime_type', 'size', 'content_hash', 'label', 'uploaded_by',
        'document_type', 'ocr_status', 'ocr_text', 'ocr_extracted_at', 'ocr_tool',
        'indexed_fields', 'indexed_at',
        'version_chain_id', 'version_number', 'is_current_version',
    ];

    protected $casts = [
        'size' => 'integer',
        'ocr_extracted_at' => 'datetime',
        'indexed_fields' => 'array',
        'indexed_at' => 'datetime',
        'version_number' => 'integer',
        'is_current_version' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (Attachment $a) {
            // Hash und Pfad sind revisionssicher — duerfen nicht veraendert werden.
            foreach (['content_hash', 'path', 'size', 'attachable_type', 'attachable_id'] as $f) {
                if ($a->isDirty($f) && $a->getOriginal($f) !== null) {
                    throw new \RuntimeException("Attachment-Feld {$f} ist unveraenderlich.");
                }
            }
        });

        static::deleting(function (Attachment $a) {
            // Nur bei force-delete physisch loeschen (revisionssicher).
            if ($a->isForceDeleting()) {
                try { Storage::disk($a->disk)->delete($a->path); } catch (\Throwable) {}
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

    /**
     * Alle Versionen des gleichen logischen Dokuments (chronologisch).
     */
    public function versions()
    {
        return Attachment::where('version_chain_id', $this->version_chain_id)
            ->orderBy('version_number');
    }

    public function scopeCurrentVersions($query)
    {
        return $query->where('is_current_version', true);
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

    /**
     * Liest die Datei und vergleicht ihren SHA-256 mit dem gespeicherten Hash.
     * Liefert true wenn intakt, false bei Manipulation oder fehlender Datei.
     */
    public function verifyContent(): bool
    {
        if (! $this->content_hash) return false;
        $disk = Storage::disk($this->disk);
        if (! $disk->exists($this->path)) return false;
        $stream = $disk->readStream($this->path);
        $ctx = hash_init('sha256');
        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) { fclose($stream); return false; }
            hash_update($ctx, $chunk);
        }
        fclose($stream);
        return hash_equals($this->content_hash, hash_final($ctx));
    }
}
