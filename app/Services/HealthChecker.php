<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Mailbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Updater\UpdaterFactory;

/**
 * Sammelt Health-Checks für die Admin-Übersicht.
 *
 * Pro Check: name, status ('ok' | 'warn' | 'fail'), message, details.
 */
class HealthChecker
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<int, array{name:string,status:string,message:string,details?:array}> */
    public function all(): array
    {
        return [
            $this->checkDatabase(),
            $this->checkDiskSpace(),
            $this->checkMail(),
            $this->checkMailboxes(),
            $this->checkOcrBacklog(),
            $this->checkFailedJobs(),
            $this->checkAuditChain(),
            $this->checkScheduler(),
            $this->checkUpdate(),
            $this->checkPhp(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $count = (int) DB::table('users')->count();
            return ['name' => 'Datenbank', 'status' => 'ok', 'message' => 'Verbunden.', 'details' => ['users' => $count]];
        } catch (\Throwable $e) {
            return ['name' => 'Datenbank', 'status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function checkDiskSpace(): array
    {
        $path = storage_path('app');
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if (! $free || ! $total) {
            return ['name' => 'Speicherplatz', 'status' => 'warn', 'message' => 'Konnte nicht ermittelt werden.'];
        }
        $pct = 100 - (int) round(($free / $total) * 100);
        $status = $pct > 90 ? 'fail' : ($pct > 80 ? 'warn' : 'ok');
        return [
            'name' => 'Speicherplatz',
            'status' => $status,
            'message' => "{$pct}% belegt · ".$this->bytes($free).' frei',
            'details' => ['free' => $free, 'total' => $total],
        ];
    }

    private function checkMail(): array
    {
        $mailer = (string) config('mail.default');
        $hostKey = "mail.mailers.{$mailer}.host";
        if (in_array($mailer, ['log', 'array', 'null'], true)) {
            return ['name' => 'Mail-Versand', 'status' => 'warn', 'message' => "Mailer = {$mailer} (kein echter Versand)."];
        }
        $host = (string) config($hostKey);
        if ($host === '') {
            return ['name' => 'Mail-Versand', 'status' => 'warn', 'message' => "SMTP-Host nicht konfiguriert ({$mailer})."];
        }
        return ['name' => 'Mail-Versand', 'status' => 'ok', 'message' => "SMTP {$mailer} -> {$host}"];
    }

    private function checkMailboxes(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('mailboxes')) {
            return ['name' => 'IMAP-Postfächer', 'status' => 'ok', 'message' => 'Keine Postfächer konfiguriert.'];
        }
        $mailboxes = Mailbox::where('is_active', true)->get();
        if ($mailboxes->isEmpty()) {
            return ['name' => 'IMAP-Postfächer', 'status' => 'ok', 'message' => 'Keine aktiven Postfächer.'];
        }
        $errors = $mailboxes->filter(fn ($m) => ! empty($m->last_error));
        $stale = $mailboxes->filter(fn ($m) => ! $m->last_fetch_at || $m->last_fetch_at->lt(now()->subHours(2)));
        if ($errors->isNotEmpty()) {
            return ['name' => 'IMAP-Postfächer', 'status' => 'fail',
                'message' => "{$errors->count()} Postfächer mit Fehlern: ".$errors->pluck('name')->join(', ')];
        }
        if ($stale->isNotEmpty()) {
            return ['name' => 'IMAP-Postfächer', 'status' => 'warn',
                'message' => "{$stale->count()} Postfächer seit über 2h nicht abgefragt."];
        }
        return ['name' => 'IMAP-Postfächer', 'status' => 'ok', 'message' => "{$mailboxes->count()} aktiv, alle aktuell."];
    }

    private function checkOcrBacklog(): array
    {
        $pending = Attachment::where('ocr_status', 'pending')->count();
        if ($pending === 0) {
            return ['name' => 'OCR-Backlog', 'status' => 'ok', 'message' => 'Kein Backlog.'];
        }
        return [
            'name' => 'OCR-Backlog',
            'status' => $pending > 500 ? 'warn' : 'ok',
            'message' => "{$pending} ausstehend (läuft nächtlich)",
        ];
    }

    private function checkFailedJobs(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
            return ['name' => 'Failed Jobs', 'status' => 'ok', 'message' => 'Keine Queue-Tabelle.'];
        }
        $n = (int) DB::table('failed_jobs')->count();
        return [
            'name' => 'Failed Jobs',
            'status' => $n > 0 ? 'warn' : 'ok',
            'message' => $n > 0 ? "{$n} fehlgeschlagene Jobs in der Queue." : 'Keine fehlgeschlagenen Jobs.',
        ];
    }

    private function checkAuditChain(): array
    {
        try {
            $broken = $this->audit->verifyChain();
            if ($broken === null) {
                return ['name' => 'Audit-Kette', 'status' => 'ok', 'message' => 'Hashkette intakt.'];
            }
            return ['name' => 'Audit-Kette', 'status' => 'fail',
                'message' => "Bruch bei Eintrag #{$broken['id']}.", 'details' => $broken];
        } catch (\Throwable $e) {
            return ['name' => 'Audit-Kette', 'status' => 'warn', 'message' => $e->getMessage()];
        }
    }

    private function checkScheduler(): array
    {
        $sentinel = storage_path('framework/schedule-last-run');
        if (! is_file($sentinel)) {
            return ['name' => 'Scheduler', 'status' => 'warn',
                'message' => 'Noch kein Lauf erkennbar. Cron eingerichtet?'];
        }
        $ts = (int) trim((string) @file_get_contents($sentinel));
        $age = time() - $ts;
        if ($age > 600) {
            return ['name' => 'Scheduler', 'status' => 'fail',
                'message' => 'Letzter Lauf vor '.gmdate('H:i:s', $age).'. Cron prüfen!'];
        }
        return ['name' => 'Scheduler', 'status' => 'ok',
            'message' => 'Letzter Lauf vor '.gmdate('H:i:s', $age).'.'];
    }

    private function checkUpdate(): array
    {
        try {
            $um = UpdaterFactory::create(DB::connection());
            if ($um->isInMaintenance()) {
                return ['name' => 'System-Update', 'status' => 'warn', 'message' => 'Wartungsmodus aktiv.'];
            }
            $check = $um->checkForUpdates();
            if (! empty($check['has_update'])) {
                return ['name' => 'System-Update', 'status' => 'warn',
                    'message' => "Update verfügbar ({$check['channel']}) → ".substr((string) ($check['latest_sha'] ?? '—'), 0, 7)];
            }
            $current = $check['current_sha'] ?? null;
            return ['name' => 'System-Update', 'status' => 'ok',
                'message' => 'Aktuell ('.($current ? substr($current, 0, 7) : 'unbekannt').').'];
        } catch (\Throwable $e) {
            return ['name' => 'System-Update', 'status' => 'warn', 'message' => $e->getMessage()];
        }
    }

    private function checkPhp(): array
    {
        $needed = ['pdo', 'mbstring', 'openssl', 'json', 'zip', 'fileinfo', 'curl'];
        $missing = array_values(array_filter($needed, fn ($e) => ! extension_loaded($e)));
        if ($missing) {
            return ['name' => 'PHP', 'status' => 'fail', 'message' => 'Fehlende Extensions: '.implode(', ', $missing)];
        }
        return ['name' => 'PHP', 'status' => 'ok',
            'message' => 'PHP '.PHP_VERSION.' · '.count($needed).' Extensions OK'];
    }

    private function bytes(float $b): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
        return number_format($b, 1, ',', '.').' '.$units[$i];
    }
}
