<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Verschickt Browser-Push-Notifications via VAPID an alle aktiven
 * Subscriptions eines Users. Setup:
 *  1. Admin generiert VAPID-Keys via Command `push:generate-vapid`
 *  2. Keys werden als Settings auth.push.vapid_public / private gespeichert
 *  3. Frontend liest den public key aus dem Meta-Tag und ruft
 *     PushManager.subscribe() auf
 *
 * Wenn VAPID-Keys oder die Extension fehlen, ist Push einfach disabled —
 * die App funktioniert weiter, nur ohne Push.
 */
class WebPushSender
{
    public function isEnabled(): bool
    {
        return ! empty(Settings::get('auth.push.vapid_public'))
            && ! empty(Settings::get('auth.push.vapid_private'))
            && class_exists(WebPush::class);
    }

    public function publicKey(): ?string
    {
        return Settings::get('auth.push.vapid_public');
    }

    /**
     * Sendet eine Notification an alle aktiven Subscriptions des Users.
     * Tote Subscriptions (HTTP 410/404) werden automatisch entfernt.
     */
    public function sendToUser(User $user, string $title, ?string $body = null, ?string $url = null): int
    {
        if (! $this->isEnabled()) return 0;

        $subs = PushSubscription::where('user_id', $user->id)->get();
        if ($subs->isEmpty()) return 0;

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => 'mailto:' . (Settings::get('mail.from_address') ?: 'admin@example.com'),
                'publicKey' => Settings::get('auth.push.vapid_public'),
                'privateKey' => Settings::get('auth.push.vapid_private'),
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?: config('app.url'),
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;
        foreach ($subs as $s) {
            $sub = Subscription::create([
                'endpoint' => $s->endpoint,
                'publicKey' => $s->public_key,
                'authToken' => $s->auth_token,
                'contentEncoding' => 'aesgcm',
            ]);
            $webPush->queueNotification($sub, $payload);
            $sent++;
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                $code = $report->getResponse()?->getStatusCode();
                // 404/410 = Subscription tot, sicher loeschen
                if (in_array($code, [404, 410], true)) {
                    PushSubscription::where('endpoint', (string) $report->getEndpoint())->delete();
                }
                Log::info('webpush failure', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                    'status' => $code,
                ]);
            } else {
                PushSubscription::where('endpoint', (string) $report->getEndpoint())
                    ->update(['last_used_at' => now()]);
            }
        }

        return $sent;
    }
}
