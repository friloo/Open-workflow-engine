<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IncomingWebhook extends Model
{
    protected $fillable = [
        'name', 'token', 'secret_enc', 'workflow_id',
        'field_mappings', 'is_active', 'created_by',
        'last_called_at', 'call_count', 'failure_count', 'last_error',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'is_active' => 'boolean',
        'last_called_at' => 'datetime',
        'call_count' => 'integer',
        'failure_count' => 'integer',
    ];

    protected $hidden = ['secret_enc'];

    protected static function booted(): void
    {
        static::creating(function (IncomingWebhook $w) {
            if (empty($w->token)) {
                $w->token = self::makeToken();
            }
        });
    }

    public static function makeToken(): string
    {
        do {
            $t = Str::random(32);
        } while (self::where('token', $t)->exists());
        return $t;
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function setSecretAttribute(?string $value): void
    {
        $this->attributes['secret_enc'] = empty($value) ? null : Crypt::encryptString($value);
    }

    public function getSecretAttribute(): ?string
    {
        if (empty($this->attributes['secret_enc'])) return null;
        try {
            return Crypt::decryptString($this->attributes['secret_enc']);
        } catch (\Throwable) {
            return null;
        }
    }
}
