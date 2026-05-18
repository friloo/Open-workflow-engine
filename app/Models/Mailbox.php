<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Mailbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'host', 'port', 'encryption', 'validate_cert',
        'username', 'password_enc', 'folder',
        'document_type', 'workflow_id',
        'subject_field', 'from_field', 'body_field',
        'ai_classify', 'move_processed', 'processed_folder',
        'is_active', 'last_fetch_at', 'last_status', 'last_error',
        'created_by',
    ];

    protected $casts = [
        'port' => 'integer',
        'validate_cert' => 'boolean',
        'ai_classify' => 'boolean',
        'move_processed' => 'boolean',
        'is_active' => 'boolean',
        'last_fetch_at' => 'datetime',
    ];

    protected $hidden = ['password_enc'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailboxMessage::class);
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password_enc'] = $value === null || $value === ''
            ? null
            : Crypt::encryptString($value);
    }

    public function getPasswordAttribute(): ?string
    {
        if (empty($this->attributes['password_enc'])) return null;
        try {
            return Crypt::decryptString($this->attributes['password_enc']);
        } catch (\Throwable) {
            return null;
        }
    }
}
