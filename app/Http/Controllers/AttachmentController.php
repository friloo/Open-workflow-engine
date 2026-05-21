<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Contract;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Services\AttachmentStorage;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:15360'],
            'label' => ['nullable', 'string', 'max:128'],
            'document_type' => ['nullable', 'string', 'max:64'],
        ]);

        $attachable = $this->resolveAttachable($type, $id, $request);
        $this->ensureUploadAllowed($attachable, $request);

        try {
            $attachment = $this->storage->store(
                $request->file('file'),
                $attachable,
                $request->input('label'),
                $request->user()->id,
                $request->input('document_type'),
                null,
                (bool) $request->boolean('force'),
            );
        } catch (\App\Exceptions\DuplicateAttachmentException $e) {
            return back()->withErrors([
                'file' => $e->getMessage().' Original: '.route('documents.show', $e->original).'. '
                    .'Trotzdem hochladen? Setze beim erneuten Senden das Feld "force=1".',
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $this->audit->log('attachment.uploaded', $attachable, null, [
            'attachment_id' => $attachment->id,
            'name' => $attachment->original_name,
            'mime' => $attachment->mime_type,
            'size' => $attachment->size,
            'sha256' => $attachment->content_hash,
        ], "Datei {$attachment->original_name} hochgeladen (sha256: ".substr($attachment->content_hash, 0, 12).")");

        return back()->with('status', "Datei {$attachment->original_name} hochgeladen.");
    }

    public function verifyAll(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (! $request->user()->hasAnyPermission(['audit.verify', 'system.settings'])) abort(403);
        $result = app(\App\Services\AttachmentStorage::class)->verifyAll();
        $this->audit->log('attachments.integrity_checked', null, null, [
            'checked' => $result['checked'], 'broken' => count($result['broken']),
        ], "Integritätsprüfung: {$result['checked']} geprüft, ".count($result['broken'])." auffällig", $request->user()->id);
        return back()->with('status', "Integrität geprüft: {$result['checked']} Dateien, ".count($result['broken'])." auffällig.")
            ->with('integrityBroken', $result['broken']);
    }

    public function download(Attachment $attachment, Request $request)
    {
        $this->ensureReadAllowed($attachment, $request);
        return $this->storage->streamDownload($attachment);
    }

    public function destroy(Attachment $attachment, Request $request): RedirectResponse
    {
        $this->ensureUploadAllowed($attachment->attachable, $request);
        $snapshot = $attachment->only(['id', 'original_name', 'attachable_type', 'attachable_id']);
        $attachment->delete();
        $this->audit->log('attachment.deleted', null, $snapshot, null, "Datei {$snapshot['original_name']} gelöscht");
        return back()->with('status', 'Datei gelöscht.');
    }

    private function resolveAttachable(string $type, int $id, Request $request)
    {
        return match ($type) {
            'asset' => Asset::findOrFail($id),
            'form-submission' => FormSubmission::findOrFail($id),
            'instance' => WorkflowInstance::findOrFail($id),
            'contract' => Contract::findOrFail($id),
            default => abort(404),
        };
    }

    private function ensureUploadAllowed($attachable, Request $request): void
    {
        $user = $request->user();
        if ($attachable instanceof Asset) {
            if (! $user->hasPermission('assets.manage')) abort(403);
            return;
        }
        if ($attachable instanceof WorkflowInstance) {
            // Initiator, Workflow-Designer, oder aktueller/früherer Assignee
            // eines Steps auf dieser Instanz dürfen Dateien anhängen.
            if ($attachable->started_by === $user->id) return;
            if ($user->hasAnyPermission(['workflows.design'])) return;
            $isAssignee = WorkflowStepExecution::where('workflow_instance_id', $attachable->id)
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id)
                      ->orWhereIn('assigned_to_role_id', $user->roles->pluck('id'));
                })->exists();
            if ($isAssignee) return;
            abort(403);
        }
        if ($attachable instanceof FormSubmission) {
            if (! $user->hasPermission('forms.manage')) abort(403);
            return;
        }
        if ($attachable instanceof Contract) {
            // Wer den Vertrag bearbeiten darf, darf auch Anhänge hochladen
            if (! $attachable->userCanManage($user)) abort(403);
            return;
        }
        abort(403);
    }

    private function ensureReadAllowed(Attachment $a, Request $request): void
    {
        $user = $request->user();
        $att = $a->attachable;

        // Admins/Workflow-Designer und Asset-/Form-Manager dürfen alles
        if ($user->hasAnyPermission(['workflows.design', 'audit.view'])) return;

        if ($att instanceof Asset) {
            if ($user->hasPermission('assets.view')) return;
            if ($att->user_id === $user->id) return;
        }
        if ($att instanceof WorkflowInstance) {
            // Initiator darf eigene Daten sehen, ebenso Bearbeiter offener Steps
            if ($att->started_by === $user->id) return;
            $isAssignee = WorkflowStepExecution::where('workflow_instance_id', $att->id)
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id)
                      ->orWhereIn('assigned_to_role_id', $user->roles->pluck('id'));
                })->exists();
            if ($isAssignee) return;
        }
        if ($att instanceof FormSubmission) {
            if ($user->hasPermission('forms.view')) return;
        }
        if ($att instanceof Contract) {
            // Wer den Vertrag sehen darf, darf auch dessen Anhänge laden
            $visible = Contract::query()->visibleTo($user)->whereKey($att->id)->exists();
            if ($visible) return;
        }
        abort(403);
    }
}
