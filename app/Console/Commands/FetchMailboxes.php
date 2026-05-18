<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Services\MailboxFetcher;
use Illuminate\Console\Command;

class FetchMailboxes extends Command
{
    protected $signature = 'mail:fetch {--mailbox= : Nur dieses Postfach abrufen}';
    protected $description = 'Holt Mails aus konfigurierten IMAP-Postfaechern und verarbeitet sie.';

    public function handle(MailboxFetcher $fetcher): int
    {
        $query = Mailbox::query()->where('is_active', true);
        if ($id = $this->option('mailbox')) {
            $query->whereKey($id);
        }
        $mailboxes = $query->get();

        if ($mailboxes->isEmpty()) {
            $this->info('Keine aktiven Postfaecher.');
            return self::SUCCESS;
        }

        foreach ($mailboxes as $mailbox) {
            $this->line("Postfach: {$mailbox->name} ({$mailbox->host})");
            try {
                $stats = $fetcher->fetch($mailbox);
                $this->info(sprintf(
                    '  abgerufen=%d, verarbeitet=%d, uebersprungen=%d, fehler=%d',
                    $stats['fetched'], $stats['processed'], $stats['skipped'], $stats['failed']
                ));
            } catch (\Throwable $e) {
                $this->error('  FEHLER: '.$e->getMessage());
            }
        }
        return self::SUCCESS;
    }
}
