<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bringt nach einem Approval einen visuellen Stempel auf alle
 * PDF-Anhaenge der Workflow-Instance auf. Der gestempelte PDF wird
 * als NEUE VERSION desselben Attachments gespeichert (Original bleibt
 * unveraendert, Versions-Chain bleibt sauber).
 *
 * Pro Approval-Knoten konfigurierbar: data.stamp_pdf = true,
 * data.stamp_pdf_only_on = ['approved' | 'rejected' | 'both'] (default approved).
 */
class ApprovalStampService
{
    public function __construct(private readonly PdfStamper $stamper, private readonly AuditLogger $audit) {}

    /**
     * Public Entry-Point. Wird nach jedem completed approval-Step
     * aufgerufen. Liefert die Anzahl gestempelter Dateien.
     */
    public function maybeStamp(WorkflowStepExecution $step, string $decision): int
    {
        $instance = $step->instance()->firstOrFail();
        $version = $instance->version()->firstOrFail();
        $node = $version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        if (! $node) return 0;

        $stampEnabled = (bool) data_get($node, 'data.stamp_pdf', false);
        if (! $stampEnabled) return 0;

        $only = data_get($node, 'data.stamp_pdf_only_on', 'approved');
        if ($only !== 'both' && $only !== $decision) return 0;

        $pdfs = $this->collectPdfAttachments($instance);
        if ($pdfs->isEmpty()) return 0;

        $approver = $step->completed_by ? User::find($step->completed_by) : null;
        $stampData = $this->buildStampData($instance, $step, $approver, $decision);

        $count = 0;
        foreach ($pdfs as $pdf) {
            try {
                $count += $this->stampOne($pdf, $stampData, $approver?->id);
            } catch (\Throwable $e) {
                Log::warning('approval stamp failed', [
                    'attachment_id' => $pdf->id, 'error' => $e->getMessage(),
                ]);
            }
        }
        return $count;
    }

    /**
     * Sammelt PDFs der Instance (aktuelle Versionen, MIME pdf).
     *
     * @return \Illuminate\Support\Collection<int, Attachment>
     */
    private function collectPdfAttachments(WorkflowInstance $instance): \Illuminate\Support\Collection
    {
        return Attachment::query()
            ->where('attachable_type', $instance->getMorphClass())
            ->where('attachable_id', $instance->id)
            ->where('is_current_version', true)
            ->where('mime_type', 'application/pdf')
            ->get();
    }

    private function buildStampData(WorkflowInstance $instance, WorkflowStepExecution $step, ?User $approver, string $decision): array
    {
        $title = $decision === 'approved' ? 'Genehmigt' : ($decision === 'rejected' ? 'Abgelehnt' : 'Entschieden');
        $color = $decision === 'approved' ? 'emerald' : ($decision === 'rejected' ? 'rose' : 'amber');

        $lines = [
            'von '. ($approver?->name ?? 'System'),
            'am '. ($step->completed_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i')),
            'Vorgang: '. $instance->workflow?->name .' #'. $instance->id,
            'Schritt: '. $step->step_key,
        ];
        if ($step->comment) {
            // Kommentar einkuerzen, sonst sprengt der den Stempel
            $lines[] = 'Bemerkung: '. Str::limit($step->comment, 80);
        }
        return ['title' => $title, 'lines' => $lines, 'color' => $color];
    }

    private function stampOne(Attachment $pdf, array $stampData, ?int $userId): int
    {
        $disk = Storage::disk($pdf->disk);
        if (! $disk->exists($pdf->path)) {
            Log::info('approval stamp skipped: file missing', ['attachment_id' => $pdf->id]);
            return 0;
        }
        $bytes = $disk->get($pdf->path);

        try {
            $stamped = $this->stamper->stamp($bytes, $stampData);
        } catch (\Throwable $e) {
            // Manche PDFs (z. B. mit moderner Verschluesselung oder PDF >1.4)
            // koennen nicht importiert werden — sauber ueberspringen.
            Log::info('approval stamp could not parse PDF', [
                'attachment_id' => $pdf->id, 'error' => $e->getMessage(),
            ]);
            return 0;
        }

        $hash = hash('sha256', $stamped);
        $dir = 'attachments/'.now()->format('Y/m');
        $name = Str::ulid().'.pdf';
        $path = $dir.'/'.$name;
        Storage::disk($pdf->disk)->put($path, $stamped);

        DB::transaction(function () use ($pdf, $path, $stamped, $hash, $userId) {
            // Alle bisherigen Versionen der Chain inaktiv setzen
            Attachment::where('version_chain_id', $pdf->version_chain_id)
                ->update(['is_current_version' => false]);

            Attachment::create([
                'attachable_type' => $pdf->attachable_type,
                'attachable_id' => $pdf->attachable_id,
                'original_name' => 'GESTEMPELT_' . $pdf->original_name,
                'disk' => $pdf->disk,
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => strlen($stamped),
                'content_hash' => $hash,
                'label' => $pdf->label,
                'uploaded_by' => $userId,
                'document_type' => $pdf->document_type,
                'ocr_status' => 'pending',
                'version_chain_id' => $pdf->version_chain_id,
                'version_number' => (int) ($pdf->version_number ?? 1) + 1,
                'is_current_version' => true,
            ]);
        });

        $this->audit->log('attachment.stamped', $pdf, null,
            ['title' => $stampData['title']],
            'PDF gestempelt: '. $stampData['title'] .' ('. $pdf->original_name .')',
            $userId);

        return 1;
    }
}
