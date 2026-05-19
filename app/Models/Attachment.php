<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCase::class, 'attachment_document_case')->withTimestamps();
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
     * Darf $user dieses Dokument im Detail / Preview ansehen?
     *
     * Erlaubt wenn:
     * 1. Sein Rollen-Doku-Type-Mapping greift (DocumentTypes::canViewType), ODER
     * 2. das Dokument an einer WorkflowInstance haengt und er Assignee
     *    eines Steps darin ist oder war (auch wenn der Step abgeschlossen ist
     *    — dadurch behaelt er Kontext nach seiner Entscheidung), ODER
     * 3. das Dokument an einem Asset haengt das ihm gehoert oder fuer das er
     *    assets.view hat.
     *
     * Damit kann z. B. ein Buchhalter Rechnungen genehmigen, ohne dass die
     * Rolle 'Buchhaltung' generell Zugriff auf den Doku-Type 'Rechnung' hat
     * — der Zugriff entsteht durch die zugewiesene Aufgabe.
     */
    public function visibleTo(?\App\Models\User $user): bool
    {
        if (! $user) return false;
        if (\App\Support\DocumentTypes::canViewType($user, $this->document_type)) return true;

        $att = $this->attachable;
        if ($att instanceof \App\Models\WorkflowInstance) {
            if ($att->started_by === $user->id) return true;
            return \App\Models\WorkflowStepExecution::where('workflow_instance_id', $att->id)
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id)
                      ->orWhereIn('assigned_to_role_id', $user->roles->pluck('id'));
                })->exists();
        }
        if ($att instanceof \App\Models\Asset) {
            if ($att->user_id === $user->id) return true;
            return $user->hasPermission('assets.view');
        }
        return false;
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
