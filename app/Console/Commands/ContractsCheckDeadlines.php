<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Taeglich: Status aller Vertraege neu berechnen + bei erreichter
 * Kuendigungsfrist eine in-App-Benachrichtigung an den
 * Verantwortlichen senden (einmal pro Frist-Auftreten, danach 90 Tage Ruhe).
 *
 * Erwartet Cron-Eintrag in app/Console/Kernel.php oder routes/console.php:
 *   $schedule->command('contracts:check-deadlines')->dailyAt('06:00');
 */
class ContractsCheckDeadlines extends Command
{
    protected $signature = 'contracts:check-deadlines';
    protected $description = 'Vertraege: Status sync + Kuendigungs-Reminder';

    public function handle(): int
    {
        if (! Schema::hasTable('contracts')) {
            $this->warn('Tabelle contracts existiert nicht — Migration ausstehend?');
            return self::SUCCESS;
        }

        $updated = 0; $reminded = 0;
        foreach (Contract::cursor() as $c) {
            $newStatus = $c->computedStatus();
            if ($newStatus !== $c->status) {
                $c->update(['status' => $newStatus]);
                $updated++;
            }
            if ($newStatus === 'notice_due' && $c->owner_user_id) {
                // Erinnerung nur, wenn seit > 90 Tagen keine kam
                $shouldRemind = ! $c->last_reminder_at
                    || $c->last_reminder_at->lt(now()->subDays(90));
                if ($shouldRemind && Schema::hasTable('app_notifications')) {
                    AppNotification::create([
                        'user_id' => $c->owner_user_id,
                        'type' => 'contract.notice_due',
                        'title' => 'Vertrag ' . $c->name . ' - Kuendigungsfrist erreicht',
                        'body' => 'Kuendigungsfrist ' . $c->notice_period_days . ' Tage vor Ende ('
                            . $c->end_date?->format('d.m.Y') . ').',
                        'url' => route('contracts.show', $c),
                    ]);
                    $c->update(['last_reminder_at' => now()]);
                    $reminded++;
                }
            }
        }

        $this->info("Status aktualisiert: {$updated}; Erinnerungen verschickt: {$reminded}");
        return self::SUCCESS;
    }
}
