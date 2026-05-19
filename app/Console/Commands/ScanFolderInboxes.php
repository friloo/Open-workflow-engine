<?php

namespace App\Console\Commands;

use App\Models\FolderInbox;
use App\Services\FolderInboxScanner;
use Illuminate\Console\Command;

class ScanFolderInboxes extends Command
{
    protected $signature = 'folder:scan {--inbox=}';
    protected $description = 'Scannt konfigurierte Folder-Inboxen (z. B. Scanner-Ordner) und importiert neue Dateien.';

    public function handle(FolderInboxScanner $scanner): int
    {
        $query = FolderInbox::where('is_active', true);
        if ($id = $this->option('inbox')) {
            $query->whereKey($id);
        }
        $inboxes = $query->get();
        if ($inboxes->isEmpty()) {
            $this->info('Keine aktiven Folder-Inboxen.');
            return self::SUCCESS;
        }
        foreach ($inboxes as $inbox) {
            $this->line("Folder: {$inbox->name} ({$inbox->absolutePath()})");
            try {
                $s = $scanner->scan($inbox);
                $this->info("  gefunden={$s['found']}, importiert={$s['imported']}, fehler={$s['failed']}");
            } catch (\Throwable $e) {
                $this->error('  FEHLER: '.$e->getMessage());
            }
        }
        return self::SUCCESS;
    }
}
