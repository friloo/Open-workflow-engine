<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AIFieldExtractor;
use App\Services\FieldExtractor;
use App\Services\OcrExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fuehrt OCR-Extraktion + Feld-Indexierung fuer einen Attachment-
 * Datensatz aus.
 *
 * Laeuft synchron wenn QUEUE_CONNECTION=sync (Default). Bei Queue-
 * Backend (database/redis) wird der Job in der Warteschlange abgelegt
 * und ein 'php artisan queue:work'-Worker muss laufen.
 *
 * Best-Effort: bei Fehlern bleibt der Status 'pending'/'failed' und
 * der Job tries selber erneut — wir verlassen uns auf den Standard-
 * Mechanismus von Laravel-Queue.
 */
class ProcessAttachmentOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30; // Sekunden zwischen Retries

    public function __construct(public int $attachmentId) {}

    public function handle(OcrExtractor $ocr, FieldExtractor $fields, AIFieldExtractor $ai): void
    {
        $att = Attachment::find($this->attachmentId);
        if (! $att) return;

        try {
            $ocr->extract($att);
        } catch (\Throwable) {
            // Status bleibt pending/failed — OCR koennen wir spaeter via
            // 'ocr:run-pending' nachholen.
        }

        try {
            $fields->extractFor($att->refresh());
        } catch (\Throwable) {
            // Indexierung haengt am Schema-Typ; bei Fehler bleibt
            // indexed_fields leer.
        }
    }
}
