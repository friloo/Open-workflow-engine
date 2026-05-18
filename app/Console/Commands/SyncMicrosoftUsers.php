<?php

namespace App\Console\Commands;

use App\Services\MicrosoftGraphSync;
use Illuminate\Console\Command;

class SyncMicrosoftUsers extends Command
{
    protected $signature = 'm365:sync-users {--role=employee : Default-Rolle fuer neue Benutzer}';
    protected $description = 'Importiert/aktualisiert Benutzer aus Microsoft 365 (Graph API).';

    public function handle(MicrosoftGraphSync $sync): int
    {
        try {
            $result = $sync->syncUsers($this->option('role'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Neu: {$result['created']} · Aktualisiert: {$result['updated']} · Fehler: ".count($result['errors']));
        foreach ($result['errors'] as $err) $this->warn(' ! '.$err);
        return self::SUCCESS;
    }
}
