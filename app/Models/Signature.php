<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    public const LEVEL_SES = 'ses';
    public const LEVEL_AES = 'aes';
    public const LEVEL_QES = 'qes';

    public const LEVELS = [
        'none' => 'Keine Signatur',
        self::LEVEL_SES => 'Einfache E-Signatur (SES)',
        self::LEVEL_AES => 'Fortgeschrittene E-Signatur (AES, PKCS#7)',
        self::LEVEL_QES => 'Qualifizierte E-Signatur (QES, extern)',
    ];

    /**
     * Rangordnung: was deckt was ab. QES > AES > SES.
     */
    public const LEVEL_ORDER = [
        'none' => 0,
        self::LEVEL_SES => 1,
        self::LEVEL_AES => 2,
        self::LEVEL_QES => 3,
    ];

    protected $fillable = [
        'attachment_id', 'user_id', 'workflow_step_execution_id',
        'level', 'provider', 'content_hash',
        'signer_name', 'signer_email', 'signer_ip',
        'certificate_pem', 'signature_blob',
        'twofa_verified', 'signed_at', 'metadata',
    ];

    protected $casts = [
        'twofa_verified' => 'boolean',
        'signed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepExecution::class, 'workflow_step_execution_id');
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }
}
