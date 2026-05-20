<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseNote extends Model
{
    protected $fillable = ['document_case_id', 'user_id', 'body'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(DocumentCase::class, 'document_case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
