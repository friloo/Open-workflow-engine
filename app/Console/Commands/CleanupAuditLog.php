<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DSGVO-Aufbewahrung: anonymisiert IP/User-Agent in alten Audit-Eintraegen,
 * ohne die Hashkette zu zerstoeren — die Felder ip_address und user_agent
 * sind nicht Teil der hash-bildung im AuditLogger.
 */
class CleanupAuditLog extends Command
{
    protected $signature = 'audit:cleanup {--days=730 : Aufbewahrungsdauer in Tagen}';
    protected $description = 'Anonymisiert IP/User-Agent in Audit-Eintraegen, die aelter als X Tage sind.';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $count = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->where(function ($q) {
                $q->whereNotNull('ip_address')->orWhereNotNull('user_agent');
            })
            ->update(['ip_address' => null, 'user_agent' => null]);
        $this->info("Anonymisiert: {$count} Eintraege aelter als {$days} Tage.");
        return self::SUCCESS;
    }
}
