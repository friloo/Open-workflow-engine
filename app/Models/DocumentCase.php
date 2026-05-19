<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * "Akte" — Sammlung von zusammengehoerigen Dokumenten. Ein Dokument
 * kann in mehreren Akten liegen (z. B. ein Vertrag in der Kunden-Akte
 * UND in der Vertraege-Sammlung des Justiziariats).
 */
class DocumentCase extends Model
{
    protected $fillable = ['name', 'description', 'reference', 'created_by', 'closed_at'];
    protected $casts = ['closed_at' => 'datetime'];

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class)->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
