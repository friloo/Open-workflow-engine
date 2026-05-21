<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'url', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    public static function send(?User $user, string $type, string $title, ?string $body = null, ?string $url = null): ?self
    {
        if (! $user) return null;
        $n = self::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);

        // Best-effort Web-Push (verschluckt Fehler — In-App-Nachricht ist
        // schon gespeichert und reicht aus, wenn Push scheitert).
        try {
            $sender = app(\App\Services\WebPushSender::class);
            if ($sender->isEnabled()) {
                $sender->sendToUser($user, $title, $body, $url);
            }
        } catch (\Throwable $e) {
            \Log::warning('webpush dispatch failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return $n;
    }
}
