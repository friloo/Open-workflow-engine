<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Quartals-Review für Vertrags-Owner:
 * Pro Owner eine Zusammenfassung aller seiner Verträge (Status,
 * Frist, Ende) in seinem Postkorb + per Mail. Soll als
 * Audit-Selbstkontrolle dienen — der Verantwortliche bestätigt
 * implizit durch die Sichtung, dass die Vertragsdaten noch stimmen.
 *
 * Empfohlener Cron-Eintrag in routes/console.php:
 *   Schedule::command('contracts:quarterly-review')
 *       ->cron('0 8 1 1,4,7,10 *')   // 1. jeden Quartals-Monats 08:00
 *       ->withoutOverlapping();
 */
class ContractsQuarterlyReview extends Command
{
    protected $signature = 'contracts:quarterly-review {--dry-run : nichts versenden, nur Anzahl ausgeben}';
    protected $description = 'Quartals-Audit-Mail an alle Vertrags-Owner mit Liste ihrer Verträge';

    public function handle(): int
    {
        if (! Schema::hasTable('contracts')) {
            $this->warn('Tabelle contracts fehlt — Migration ausstehend?');
            return self::SUCCESS;
        }

        // Pro Owner alle Verträge bündeln
        $contracts = Contract::query()
            ->whereNotNull('owner_user_id')
            ->with('type')
            ->orderBy('end_date')
            ->get()
            ->groupBy('owner_user_id');

        $dry = (bool) $this->option('dry-run');
        $sent = 0;
        foreach ($contracts as $ownerId => $list) {
            $owner = User::find($ownerId);
            if (! $owner || ! $owner->is_active) continue;

            // In-App-Notification (alle bekommen die)
            if (! $dry) {
                AppNotification::send(
                    $owner,
                    'contract.quarterly_review',
                    'Quartals-Prüfung: ' . $list->count() . ' Verträge',
                    'Bitte sichten — Stand: ' . now()->format('d.m.Y'),
                    route('contracts.index', ['filter' => 'all']),
                );
            }

            // Mail nur wenn aktiv abonniert
            if ($owner->email_notifications_enabled && ! $dry) {
                try {
                    $body = View::make('emails.contracts-quarterly-review', [
                        'owner' => $owner,
                        'contracts' => $list,
                        'date' => now(),
                    ])->render();
                    Mail::raw('', function ($m) use ($owner, $body, $list) {
                        $m->to($owner->email)
                          ->subject('Quartals-Prüfung Verträge: ' . $list->count() . ' Einträge')
                          ->html($body);
                    });
                } catch (\Throwable $e) {
                    $this->warn("Mail an {$owner->email} fehlgeschlagen: " . $e->getMessage());
                }
            }
            $sent++;
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Review-Reminder an {$sent} Vertrags-Owner.");
        return self::SUCCESS;
    }
}
