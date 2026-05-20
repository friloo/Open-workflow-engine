<?php

namespace App\Console\Commands;

use App\Support\Settings;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class PushGenerateVapid extends Command
{
    protected $signature = 'push:generate-vapid {--force : ohne Rueckfrage ueberschreiben}';
    protected $description = 'Generiert VAPID-Schluesselpaar fuer Web-Push und speichert es in Settings.';

    public function handle(): int
    {
        if (Settings::get('auth.push.vapid_public') && ! $this->option('force')) {
            $this->warn('VAPID-Keys existieren bereits. Mit --force ueberschreiben.');
            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();
        Settings::set('auth.push.vapid_public', $keys['publicKey']);
        Settings::set('auth.push.vapid_private', $keys['privateKey']);

        $this->info('VAPID-Keys erzeugt und in Settings gespeichert.');
        $this->line('  Public:  ' . substr($keys['publicKey'], 0, 24) . '…');
        $this->line('Push ist jetzt aktiv — User koennen unter Profil "Push-Benachrichtigungen aktivieren".');
        return self::SUCCESS;
    }
}
