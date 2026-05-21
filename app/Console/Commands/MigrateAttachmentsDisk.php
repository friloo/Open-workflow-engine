<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Verschiebt bestehende Anhänge vom aktuellen Disk auf einen anderen
 * (z. B. lokal → S3 / MinIO). Streamt die Dateien chunk-weise, prüft
 * den SHA-256 nach dem Schreiben, löscht erst dann das Original.
 *
 * Nutzung:
 *   php artisan attachments:migrate-disk s3
 *   php artisan attachments:migrate-disk s3 --dry-run
 *   php artisan attachments:migrate-disk s3 --from=local
 *
 * Lauft idempotent. Wird der Lauf unterbrochen, kann er einfach noch
 * mal gestartet werden — schon migrierte Files (disk = ziel) werden
 * übersprungen.
 */
class MigrateAttachmentsDisk extends Command
{
    protected $signature = 'attachments:migrate-disk
        {to : Ziel-Disk (z. B. s3, local, minio)}
        {--from= : Quelle-Disk (Default: alle ausser Ziel)}
        {--dry-run : Nur zeigen, was passieren würde}';

    protected $description = 'Verschiebt Anhänge vom aktuellen Disk auf einen anderen (z. B. lokal → S3).';

    public function handle(): int
    {
        $to = $this->argument('to');
        $from = $this->option('from');
        $dry = (bool) $this->option('dry-run');

        if (! in_array($to, array_keys(config('filesystems.disks', [])), true)) {
            $this->error("Disk '{$to}' nicht in config/filesystems.php konfiguriert.");
            return self::FAILURE;
        }

        $q = Attachment::query()->where('disk', '!=', $to);
        if ($from) $q->where('disk', $from);

        $total = (clone $q)->count();
        if ($total === 0) {
            $this->info('Nichts zu migrieren — alle Anhänge sind bereits auf '.$to.'.');
            return self::SUCCESS;
        }

        $this->info(sprintf('%s%d Anhänge -> Disk "%s"',
            $dry ? '[DRY-RUN] ' : '', $total, $to));

        $migrated = 0; $skipped = 0; $broken = [];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($q->cursor() as $att) {
            try {
                $srcDisk = Storage::disk($att->disk);
                if (! $srcDisk->exists($att->path)) {
                    $broken[] = "ID {$att->id}: Quelle fehlt ({$att->disk}:{$att->path})";
                    $bar->advance();
                    continue;
                }

                if ($dry) {
                    $migrated++;
                    $bar->advance();
                    continue;
                }

                // Streamen statt komplett in RAM laden — groß-Files-safe.
                $stream = $srcDisk->readStream($att->path);
                if (! Storage::disk($to)->writeStream($att->path, $stream)) {
                    $broken[] = "ID {$att->id}: Schreiben auf Ziel fehlgeschlagen";
                    fclose($stream);
                    $bar->advance();
                    continue;
                }
                if (is_resource($stream)) fclose($stream);

                // Integrität prüfen: SHA-256 nach Schreiben.
                $writtenHash = hash_file('sha256',
                    'sha256://'.$to // wir nutzen den Storage-Path stattdessen
                );
                // Storage::path() ist nicht universell verfügbar; sicherer
                // Weg: nochmal lesen und hashen.
                $content = Storage::disk($to)->get($att->path);
                $writtenHash = hash('sha256', $content);
                unset($content);

                if ($writtenHash !== $att->content_hash) {
                    Storage::disk($to)->delete($att->path);
                    $broken[] = "ID {$att->id}: Hash-Mismatch nach Schreiben (Quelle behalten)";
                    $bar->advance();
                    continue;
                }

                // Erst nach erfolgreichem Verify die Quelle löschen.
                $srcDisk->delete($att->path);
                $att->update(['disk' => $to]);
                $migrated++;
            } catch (\Throwable $e) {
                $broken[] = "ID {$att->id}: ".$e->getMessage();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Migriert: {$migrated}".($dry ? ' (dry-run)' : ''));
        if ($skipped > 0) $this->line("Übersprungen: {$skipped}");
        if ($broken) {
            $this->warn('Fehlgeschlagen: '.count($broken));
            foreach (array_slice($broken, 0, 20) as $b) $this->line(' - '.$b);
            if (count($broken) > 20) $this->line('  … weitere '.(count($broken) - 20).' Fehler');
        }
        return self::SUCCESS;
    }
}
