<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'token_hash', 'prefix',
        'abilities', 'last_used_at', 'expires_at', 'revoked_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{token: ApiToken, plain: string}
     */
    public static function generate(User $user, string $name, ?array $abilities = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = 'owe_'.Str::random(40);
        $hash = hash('sha256', $plain);
        $token = self::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => $hash,
            'prefix' => substr($plain, 0, 8),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);
        return ['token' => $token, 'plain' => $plain];
    }

    public function isActive(): bool
    {
        if ($this->revoked_at) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function can(string $ability): bool
    {
        if (! $this->isActive()) return false;
        $abilities = $this->abilities ?? [];
        if (empty($abilities) || in_array('*', $abilities, true)) return true;
        return in_array($ability, $abilities, true);
    }
}
