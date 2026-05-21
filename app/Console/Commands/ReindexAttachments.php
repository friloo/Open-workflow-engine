<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Services\FieldExtractor;
use Illuminate\Console\Command;

class ReindexAttachments extends Command
{
    protected $signature = 'documents:reindex {--type= : Nur ein bestimmter Dokumenttyp}
                                                 {--missing : Nur Anhänge ohne indexed_fields}
                                                 {--id=* : Konkrete Attachment-IDs}';
    protected $description = 'Liest Felder für bestehende Anhänge erneut aus dem OCR-Text (basierend auf dem Dokumenttyp-Schema).';

    public function handle(FieldExtractor $fields): int
    {
        $q = Attachment::query()->orderBy('id');
        if ($type = $this->option('type')) {
            $q->where('document_type', $type);
        } else {
            $q->whereNotNull('document_type');
        }
        if ($this->option('missing')) {
            $q->whereNull('indexed_fields');
        }
        if ($ids = $this->option('id')) {
            $q->whereIn('id', $ids);
        }

        $n = 0; $written = 0;
        $q->chunk(100, function ($chunk) use (&$n, &$written, $fields) {
            foreach ($chunk as $att) {
                $n++;
                $result = $fields->extractFor($att);
                if (! empty($result)) $written++;
                $this->line("#{$att->id} {$att->original_name} -> ".count($result).' Felder');
            }
        });

        $this->info("Verarbeitet: {$n} · mit Feldern: {$written}");
        return self::SUCCESS;
    }
}
