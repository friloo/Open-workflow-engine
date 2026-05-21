<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore {file : Dateiname (z.B. owe-2026-05-30_120000.zip)}
                                            {--force : Bestätigung überspringen}';
    protected $description = 'Stellt DB und Anhänge aus einem Backup wieder her. ACHTUNG: überschreibt!';

    public function handle(BackupService $service): int
    {
        $file = (string) $this->argument('file');
        if (! $this->option('force')) {
            if (! $this->confirm("Backup '{$file}' wirklich einspielen? DB und Anhänge werden überschrieben!")) {
                $this->warn('Abgebrochen.');
                return self::FAILURE;
            }
        }
        try {
            $service->restore($file);
            $this->info('Backup wiederhergestellt: '.$file);
            $this->line('Empfehlung: php artisan migrate --force (falls Schema neuer als Backup).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
