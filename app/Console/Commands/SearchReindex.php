<?php

namespace App\Console\Commands;

use App\Services\Search\DocumentSearch;
use Illuminate\Console\Command;

/**
 * Schiebt alle vorhandenen Anhänge in den externen Such-Index
 * (MeiliSearch). Wird einmal nach Aktivierung des Backends gebraucht
 * oder wenn der Index korrupt / verloren ist.
 *
 * Nutzung:
 *   php artisan search:reindex
 */
class SearchReindex extends Command
{
    protected $signature = 'search:reindex';
    protected $description = 'Befüllt den externen Such-Index (MeiliSearch) komplett neu.';

    public function handle(DocumentSearch $search): int
    {
        if ($search->driver() === 'database') {
            $this->warn('SEARCH_DRIVER=database — nichts zu indexieren. Setze SEARCH_DRIVER=meilisearch in der .env.');
            return self::SUCCESS;
        }
        $h = $search->health();
        if (! ($h['ok'] ?? false)) {
            $this->error('MeiliSearch nicht erreichbar: '.($h['error'] ?? 'unbekannt'));
            return self::FAILURE;
        }

        $this->info('Starte Re-Index ...');
        $start = microtime(true);
        $count = $search->reindexAll(200, function ($n) {
            $this->line("  {$n} Anhänge indexiert ...");
        });
        $dur = round(microtime(true) - $start, 1);
        $this->info("Fertig: {$count} Anhänge in {$dur}s.");
        return self::SUCCESS;
    }
}
