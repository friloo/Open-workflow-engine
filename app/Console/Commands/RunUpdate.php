<?php

namespace App\Console\Commands;

use App\Services\Update\UpdateManager;
use Illuminate\Console\Command;

class RunUpdate extends Command
{
    protected $signature = 'owe:update {--check : Nur prüfen, nicht updaten}';
    protected $description = 'Prueft auf neue Version (Channel-spezifisch) und installiert sie.';

    public function handle(UpdateManager $manager): int
    {
        $check = $manager->check();
        $this->line('Channel:  '.$check['label']);
        $this->line('Aktuell:  '.($check['current'] ?? 'unbekannt'));
        $this->line('Verfügbar: '.($check['latest'] ?? '—'));
        if (! empty($check['error'])) {
            $this->error('Fehler: '.$check['error']);
            return self::FAILURE;
        }
        if (! $check['has_update']) {
            $this->info('Schon aktuell.');
            return self::SUCCESS;
        }
        if ($this->option('check')) {
            $this->info('Update verfügbar (nur Check).');
            return self::SUCCESS;
        }
        try {
            $result = $manager->run();
            $this->info('Update abgeschlossen: '.$result['version']);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
