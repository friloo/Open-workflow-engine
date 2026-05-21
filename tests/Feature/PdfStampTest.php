<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Models\WorkflowVersion;
use App\Services\ApprovalStampService;
use App\Services\AuditLogger;
use App\Services\PdfStamper;
use App\Services\WorkflowEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdfStampTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_stamper_renders_visible_stamp(): void
    {
        $stamper = new PdfStamper();
        $minimalPdf = $this->minimalPdf();

        $stamped = $stamper->stamp($minimalPdf, [
            'title' => 'Genehmigt',
            'lines' => ['von Max', 'am 15.05.2026'],
            'color' => 'emerald',
        ]);

        $this->assertStringStartsWith('%PDF-', $stamped);
        $this->assertStringEndsWith("%%EOF\n", $stamped);
        // Gestempelte PDF muss größer sein als das Original
        $this->assertGreaterThan(strlen($minimalPdf), strlen($stamped));
    }

    public function test_stamping_creates_new_attachment_version(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Workflow mit Approval-Knoten der stamp_pdf=true hat
        $w = Workflow::create(['name' => 'Stempel-Test', 'slug' => 's', 'status' => 'active', 'created_by' => $admin->id]);
        $v = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'n1' => ['id' => 'n1', 'class' => 'approval', 'data' => [
                    'label' => 'Prüfung', 'stamp_pdf' => true, 'stamp_pdf_only_on' => 'approved',
                ]],
            ]]]]]);
        $i = WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id,
            'data' => [], 'status' => 'running', 'started_at' => now(), 'started_by' => $admin->id]);

        // PDF an Workflow-Instance hängen
        $pdfBytes = $this->minimalPdf();
        Storage::disk('local')->put('attachments/2026/05/test.pdf', $pdfBytes);
        $chainId = (string) Str::uuid();
        $original = Attachment::create([
            'attachable_type' => $i->getMorphClass(), 'attachable_id' => $i->id,
            'original_name' => 'rechnung.pdf', 'disk' => 'local',
            'path' => 'attachments/2026/05/test.pdf', 'mime_type' => 'application/pdf',
            'size' => strlen($pdfBytes), 'content_hash' => hash('sha256', $pdfBytes),
            'version_chain_id' => $chainId, 'version_number' => 1, 'is_current_version' => true,
            'ocr_status' => 'pending',
        ]);

        // Step abschliessen via Service
        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $i->id, 'step_key' => 'n1', 'step_type' => 'approval',
            'assigned_to_user_id' => $admin->id, 'completed_at' => now(),
            'completed_by' => $admin->id, 'decision' => 'approved',
        ]);

        $svc = new ApprovalStampService(new PdfStamper(), app(AuditLogger::class));
        $count = $svc->maybeStamp($step, 'approved');

        $this->assertSame(1, $count);

        // Es gibt jetzt 2 Attachments mit gleicher chain_id
        $versions = Attachment::where('version_chain_id', $chainId)->orderBy('version_number')->get();
        $this->assertCount(2, $versions);
        $this->assertFalse((bool) $versions[0]->is_current_version);
        $this->assertTrue((bool) $versions[1]->is_current_version);
        $this->assertSame(2, $versions[1]->version_number);
        $this->assertStringContainsString('GESTEMPELT_', $versions[1]->original_name);

        $this->assertDatabaseHas('audit_logs', ['event' => 'attachment.stamped']);
    }

    public function test_stamping_skipped_when_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $w = Workflow::create(['name' => 'NoStamp', 'slug' => 'ns', 'status' => 'active', 'created_by' => $admin->id]);
        $v = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'n1' => ['id' => 'n1', 'class' => 'approval', 'data' => [
                    'label' => 'Prüfung',
                    // KEIN stamp_pdf
                ]],
            ]]]]]);
        $i = WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id,
            'data' => [], 'status' => 'running', 'started_at' => now(), 'started_by' => $admin->id]);

        $pdfBytes = $this->minimalPdf();
        Storage::disk('local')->put('a/b.pdf', $pdfBytes);
        Attachment::create([
            'attachable_type' => $i->getMorphClass(), 'attachable_id' => $i->id,
            'original_name' => 'b.pdf', 'disk' => 'local', 'path' => 'a/b.pdf',
            'mime_type' => 'application/pdf', 'size' => strlen($pdfBytes),
            'content_hash' => hash('sha256', $pdfBytes),
            'version_chain_id' => (string) Str::uuid(), 'version_number' => 1, 'is_current_version' => true,
            'ocr_status' => 'pending',
        ]);

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $i->id, 'step_key' => 'n1', 'step_type' => 'approval',
            'completed_at' => now(), 'completed_by' => $admin->id, 'decision' => 'approved',
        ]);

        $svc = new ApprovalStampService(new PdfStamper(), app(AuditLogger::class));
        $this->assertSame(0, $svc->maybeStamp($step, 'approved'));
        $this->assertSame(1, Attachment::count());
    }

    public function test_stamping_respects_only_on_filter(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $admin = User::factory()->create();

        $w = Workflow::create(['name' => 'X', 'slug' => 'x', 'status' => 'active', 'created_by' => $admin->id]);
        $v = WorkflowVersion::create(['workflow_id' => $w->id, 'version_number' => 1,
            'definition' => ['drawflow' => ['Home' => ['data' => [
                'n1' => ['id' => 'n1', 'class' => 'approval', 'data' => [
                    'label' => 'Prüfung', 'stamp_pdf' => true, 'stamp_pdf_only_on' => 'approved',
                ]],
            ]]]]]);
        $i = WorkflowInstance::create(['workflow_id' => $w->id, 'workflow_version_id' => $v->id,
            'data' => [], 'status' => 'running', 'started_at' => now()]);

        $pdfBytes = $this->minimalPdf();
        Storage::disk('local')->put('a/c.pdf', $pdfBytes);
        Attachment::create([
            'attachable_type' => $i->getMorphClass(), 'attachable_id' => $i->id,
            'original_name' => 'c.pdf', 'disk' => 'local', 'path' => 'a/c.pdf',
            'mime_type' => 'application/pdf', 'size' => strlen($pdfBytes),
            'content_hash' => hash('sha256', $pdfBytes),
            'version_chain_id' => (string) Str::uuid(), 'version_number' => 1, 'is_current_version' => true,
            'ocr_status' => 'pending',
        ]);

        $step = WorkflowStepExecution::create([
            'workflow_instance_id' => $i->id, 'step_key' => 'n1', 'step_type' => 'approval',
            'completed_at' => now(), 'completed_by' => $admin->id, 'decision' => 'rejected',
        ]);

        // Konfig sagt "nur bei approved" - rejected wird nicht gestempelt
        $svc = new ApprovalStampService(new PdfStamper(), app(AuditLogger::class));
        $this->assertSame(0, $svc->maybeStamp($step, 'rejected'));
    }

    /**
     * Minimales gültiges PDF, das FPDI laden kann. Per ezpdf von Hand
     * gebaut, mit einer einzigen leeren A4-Seite.
     */
    private function minimalPdf(): string
    {
        // Wir nutzen FPDI's eigenes FPDF zum Erzeugen einer Test-PDF —
        // garantiert dass FPDI sie auch wieder lesen kann.
        $fpdf = new \setasign\Fpdi\Fpdi();
        $fpdf->AddPage();
        $fpdf->SetFont('Helvetica', '', 12);
        $fpdf->Cell(0, 10, 'Original-Dokument');
        return $fpdf->Output('S');
    }
}
