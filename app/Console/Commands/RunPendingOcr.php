<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Services\OcrExtractor;
use Illuminate\Console\Command;

class RunPendingOcr extends Command
{
    protected $signature = 'ocr:run-pending {--limit=50}';
    protected $description = 'Versucht OCR für alle Attachments mit Status pending oder failed.';

    public function handle(OcrExtractor $ocr): int
    {
        $atts = Attachment::query()
            ->whereIn('ocr_status', ['pending', 'failed'])
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($atts->isEmpty()) {
            $this->info('Keine Anhänge offen.');
            return self::SUCCESS;
        }

        foreach ($atts as $a) {
            $this->line("OCR für #{$a->id} {$a->original_name}");
            $ocr->extract($a);
            $this->line('  -> '.$a->fresh()->ocr_status);
        }
        return self::SUCCESS;
    }
}
