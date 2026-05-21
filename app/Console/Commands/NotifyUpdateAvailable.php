<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Permission;
use App\Services\Update\UpdateManager;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Prueft täglich auf Updates und schickt eine In-App-Notification an alle
 * Benutzer mit Permission system.update — aber pro Soll-SHA nur einmal,
 * damit's nicht spammt.
 */
class NotifyUpdateAvailable extends Command
{
    protected $signature = 'update:notify-available';
    protected $description = 'Pruefe auf neue OWE-Version und benachrichtige alle Admins per Glocke.';

    public function handle(UpdateManager $manager): int
    {
        $check = $manager->check();
        if (! empty($check['error'])) {
            $this->warn('Update-Check fehlgeschlagen: '.$check['error']);
            return self::SUCCESS;
        }
        if (! $check['has_update']) {
            $this->info('Schon aktuell.');
            return self::SUCCESS;
        }

        $latest = (string) $check['latest'];
        $lastNotified = (string) Settings::get('update.last_notified_sha', '');
        if ($latest === $lastNotified) {
            $this->info("Update {$latest} wurde bereits gemeldet — keine erneute Benachrichtigung.");
            return self::SUCCESS;
        }

        $permission = Permission::where('slug', 'system.update')->first();
        if (! $permission) {
            $this->error('Permission system.update nicht gefunden — Seeder laufen lassen.');
            return self::FAILURE;
        }

        $recipients = collect();
        foreach ($permission->roles()->with('users')->get() as $role) {
            foreach ($role->users as $u) {
                if ($u->is_active) $recipients->put($u->id, $u);
            }
        }

        $shortSha = substr($latest, 0, 7);
        $sent = 0;
        foreach ($recipients as $user) {
            AppNotification::send(
                $user,
                'system.update.available',
                "OWE-Update verfügbar ({$check['label']})",
                "Eine neue Version ist verfügbar: {$shortSha}…. Jetzt installieren über Verwaltung → System-Update.",
                route('admin.update.index'),
            );
            $sent++;
        }

        Settings::set('update.last_notified_sha', $latest);

        $this->info("Update {$shortSha}… an {$sent} Admin(s) gemeldet.");
        return self::SUCCESS;
    }
}
