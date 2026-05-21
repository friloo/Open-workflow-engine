<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Akte" / Aktendeckel — bündelt zusammengehörige Dokumente,
 * Workflow-Vorgänge, Verträge und Notizen.
 *
 * Ein Dokument/Vorgang/Vertrag kann in mehreren Akten liegen
 * (z. B. ein Vertrag in der Kunden-Akte UND in der
 * Verträge-Sammlung des Justiziariats).
 */
class DocumentCase extends Model
{
    protected $fillable = ['name', 'description', 'reference', 'created_by', 'closed_at'];
    protected $casts = ['closed_at' => 'datetime'];

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class)->withTimestamps();
    }

    public function workflowInstances(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkflowInstance::class,
            'case_workflow_instance',
            'document_case_id',
            'workflow_instance_id',
        )->withTimestamps();
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(
            Contract::class,
            'case_contract',
            'document_case_id',
            'contract_id',
        )->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class, 'document_case_id')->orderByDesc('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
