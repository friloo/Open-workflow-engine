<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareLink extends Model
{
    protected $fillable = [
        'token', 'attachment_id', 'created_by', 'follow_versions',
        'password_hash', 'note', 'expires_at', 'max_downloads', 'download_count',
        'is_revoked', 'revoked_at', 'revoke_reason',
        'last_review_sent_at', 'last_review_response_at', 'review_response',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'follow_versions' => 'boolean',
        'expires_at' => 'datetime',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
        'is_revoked' => 'boolean',
        'revoked_at' => 'datetime',
        'last_review_sent_at' => 'datetime',
        'last_review_response_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ShareLink $s) {
            if (empty($s->token)) $s->token = Str::random(40);
        });
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(ShareLinkAccess::class)->orderByDesc('accessed_at');
    }

    /**
     * Liefert die Attachment, die im Share angezeigt werden soll —
     * entweder die feste Version oder die aktuelle Chain-Spitze.
     */
    public function effectiveAttachment(): ?Attachment
    {
        $att = $this->attachment;
        if (! $att) return null;
        if ($this->follow_versions) {
            return Attachment::where('version_chain_id', $att->version_chain_id)
                ->where('is_current_version', true)->first() ?? $att;
        }
        return $att;
    }

    public function setPassword(?string $plain): void
    {
        $this->password_hash = ($plain === null || $plain === '') ? null : Hash::make($plain);
    }

    public function checkPassword(string $plain): bool
    {
        return $this->password_hash && Hash::check($plain, $this->password_hash);
    }

    public function isActive(): bool
    {
        if ($this->is_revoked) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_downloads !== null && $this->download_count >= $this->max_downloads) return false;
        return true;
    }

    public function statusLabel(): string
    {
        if ($this->is_revoked) return 'widerrufen';
        if ($this->expires_at && $this->expires_at->isPast()) return 'abgelaufen';
        if ($this->max_downloads && $this->download_count >= $this->max_downloads) return 'Download-Limit erreicht';
        return 'aktiv';
    }

    public function revoke(?string $reason = null): void
    {
        $this->forceFill([
            'is_revoked' => true,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();
    }

    public static function maxAllowedExpiry(): \Carbon\Carbon
    {
        $days = max(1, (int) Settings::get('shares.max_expiry_days', 90));
        return now()->addDays($days);
    }

    public static function defaultExpiry(): \Carbon\Carbon
    {
        $days = max(1, (int) Settings::get('shares.default_expiry_days', 14));
        return now()->addDays(min($days, (int) Settings::get('shares.max_expiry_days', 90)));
    }
}
