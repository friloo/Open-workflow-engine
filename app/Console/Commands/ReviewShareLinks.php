<?php

namespace App\Console\Commands;

use App\Mail\ShareAutoRevokedMail;
use App\Mail\ShareReviewMail;
use App\Models\ShareLink;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ReviewShareLinks extends Command
{
    protected $signature = 'shares:review {--limit=200}';
    protected $description = 'Versendet periodische Prüfungs-Mails für aktive Freigabe-Links und widerruft, wenn nicht reagiert wird.';

    public function handle(AuditLogger $audit): int
    {
        $interval = max(1, (int) Settings::get('shares.review_interval_days', 7));
        $grace = max(1, (int) Settings::get('shares.review_grace_days', 3));

        $now = now();
        $limit = (int) $this->option('limit');

        // 1) Auto-Revoke: Mail wurde gesendet, Frist überschritten, keine Reaktion.
        $overdue = ShareLink::where('is_revoked', false)
            ->whereNotNull('last_review_sent_at')
            ->where(function ($q) use ($grace) {
                $q->whereNull('last_review_response_at')
                  ->orWhereColumn('last_review_response_at', '<', 'last_review_sent_at');
            })
            ->where('last_review_sent_at', '<', $now->copy()->subDays($grace))
            ->limit($limit)->get();

        $revoked = 0;
        foreach ($overdue as $s) {
            $s->revoke('Automatisch widerrufen — keine Reaktion auf Prüfung.');
            $audit->log('share.auto_revoked', $s, null, [
                'last_review_sent_at' => $s->last_review_sent_at?->toIso8601String(),
            ], 'Freigabe automatisch widerrufen (keine Antwort)');
            if ($s->creator?->email_notifications_enabled && $s->creator?->email) {
                try { Mail::to($s->creator->email)->send(new ShareAutoRevokedMail($s)); }
                catch (\Throwable $e) { $this->warn("Mail an {$s->creator->email}: {$e->getMessage()}"); }
            }
            $revoked++;
        }

        // 2) Prüfungs-Mail senden:
        //   a) noch nie geprüft und Link läuft seit > interval Tagen
        //   b) letzte Prüfung > interval Tage her UND noch keine Antwort
        $due = ShareLink::with('creator', 'attachment')
            ->where('is_revoked', false)
            ->where(function ($q) use ($now, $interval) {
                $q->where(function ($qq) use ($now, $interval) {
                    $qq->whereNull('last_review_sent_at')
                       ->where('created_at', '<', $now->copy()->subDays($interval));
                })->orWhere(function ($qq) use ($now, $interval) {
                    $qq->whereNotNull('last_review_sent_at')
                       ->where('last_review_sent_at', '<', $now->copy()->subDays($interval));
                });
            })
            ->limit($limit)->get();

        $sent = 0;
        foreach ($due as $s) {
            if (! $s->creator) continue;
            \App\Models\AppNotification::send(
                $s->creator,
                'share.review_due',
                'Freigabe prüfen: '.($s->attachment?->original_name ?? 'Dokument'),
                'Die Freigabe muss bestätigt oder widerrufen werden.',
                route('shares.index'),
            );
            if (! $s->creator->email) continue;
            if (! $s->creator->email_notifications_enabled) continue;
            try {
                Mail::to($s->creator->email)->send(new ShareReviewMail($s));
                $s->forceFill(['last_review_sent_at' => $now])->save();
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("Prüfungs-Mail an {$s->creator->email}: {$e->getMessage()}");
            }
        }

        $this->info("Prüfungs-Mails: {$sent} · Auto-widerrufen: {$revoked}");
        return self::SUCCESS;
    }
}
