<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Wendet Aufbewahrungsregeln pro Dokumenttyp an.
 *
 * Regel-Format in attachments.retention:
 *   ["Rechnung" => ["min_years"=>10, "max_years"=>11, "on_expiry"=>"mark_for_review"]]
 *
 * - Solange juenger als max_years: nichts
 * - Sobald erreicht: Aktion gemäß on_expiry
 *   - mark_for_review: Audit-Eintrag, weiterhin sichtbar
 *   - archive: Soft-Delete (Datei bleibt physisch)
 *   - delete: ForceDelete (Datei und DB-Eintrag weg)
 */
class CheckDocumentRetention extends Command
{
    protected $signature = 'documents:retention-check {--dry-run : nur ausgeben, nicht ausführen}';
    protected $description = 'Wendet Aufbewahrungsregeln auf abgelaufene Dokumente an.';

    public function handle(AuditLogger $audit): int
    {
        $rules = (array) Settings::get('attachments.retention', []);
        if (empty($rules)) {
            $this->info('Keine Aufbewahrungsregeln definiert.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $stats = ['mark_for_review' => 0, 'archive' => 0, 'delete' => 0];

        foreach ($rules as $type => $rule) {
            $maxYears = $rule['max_years'] ?? null;
            if (! $maxYears) continue;
            $threshold = $now->copy()->subYears((int) $maxYears);

            Attachment::query()
                ->where('document_type', $type)
                ->where('created_at', '<', $threshold)
                ->orderBy('id')
                ->chunk(200, function ($chunk) use ($rule, $type, $audit, $dryRun, &$stats) {
                    foreach ($chunk as $att) {
                        $action = $rule['on_expiry'];
                        $this->line("#{$att->id} ({$type}, {$att->created_at->format('Y-m-d')}) -> {$action}");
                        if ($dryRun) continue;
                        $this->applyAction($att, $action, $audit);
                        $stats[$action]++;
                    }
                });
        }

        $this->info(sprintf(
            'Markiert: %d · Archiviert: %d · Endgültig gelöscht: %d',
            $stats['mark_for_review'], $stats['archive'], $stats['delete']
        ));
        return self::SUCCESS;
    }

    private function applyAction(Attachment $att, string $action, AuditLogger $audit): void
    {
        $snapshot = $att->only(['id', 'original_name', 'document_type', 'created_at']);
        match ($action) {
            'mark_for_review' => $audit->log('document.retention.review_due', $att, null, $snapshot,
                "Aufbewahrungsfrist abgelaufen: {$att->original_name}"),
            'archive' => $this->archive($att, $audit, $snapshot),
            'delete' => $this->purge($att, $audit, $snapshot),
            default => null,
        };
    }

    private function archive(Attachment $att, AuditLogger $audit, array $snapshot): void
    {
        $att->delete();
        $audit->log('document.retention.archived', null, $snapshot, null,
            "Dokument archiviert: {$snapshot['original_name']}");
    }

    private function purge(Attachment $att, AuditLogger $audit, array $snapshot): void
    {
        $att->forceDelete();
        $audit->log('document.retention.deleted', null, $snapshot, null,
            "Dokument endgültig gelöscht: {$snapshot['original_name']}");
    }
}
