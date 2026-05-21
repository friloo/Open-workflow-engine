<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'backup:run';
    protected $description = 'Erzeugt ein ZIP-Backup (DB + Anhänge) und entfernt alte gemäß Retention.';

    public function handle(BackupService $service): int
    {
        try {
            $path = $service->create();
            $this->info('Backup angelegt: '.basename($path).' ('.number_format(filesize($path) / 1024 / 1024, 2).' MB)');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
