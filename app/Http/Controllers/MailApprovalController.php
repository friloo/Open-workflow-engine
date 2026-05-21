<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkflowStepExecution;
use App\Services\AuditLogger;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Genehmigung per Mail: jeder Empfänger erhält einen signierten Link
 * (Approve / Reject) mit seiner User-ID. Klick zeigt eine Bestätigungs-
 * Seite mit Aufgaben-Kontext; erst der Klick auf "Bestätigen" schliesst
 * den Schritt ab. Schuetzt vor Vorschau-Crawlern und Fehlklicks.
 */
class MailApprovalController extends Controller
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, WorkflowStepExecution $step, User $user): View
    {
        $this->assertEligible($step, $user);

        $decision = $request->query('decision');
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 400, 'Unbekannte Entscheidung.');

        $step->load(['instance.workflow', 'instance.starter', 'instance.version']);
        $node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;

        return view('mail-approval.confirm', [
            'step' => $step,
            'user' => $user,
            'decision' => $decision,
            'node' => $node,
            'fullUrl' => $request->fullUrl(),
        ]);
    }

    public function submit(Request $request, WorkflowStepExecution $step, User $user): RedirectResponse|View
    {
        $this->assertEligible($step, $user);

        $decision = $request->query('decision');
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 400);

        $node = $step->instance->version->definition['drawflow']['Home']['data'][$step->step_key] ?? null;
        $requireOnApproval = (bool) data_get($node, 'data.require_comment_on_approval', false);
        $requireOnRejection = (bool) data_get($node, 'data.require_comment_on_rejection', false);
        $commentRule = 'nullable';
        if ($decision === 'approved' && $requireOnApproval) $commentRule = 'required';
        if ($decision === 'rejected' && $requireOnRejection) $commentRule = 'required';

        $data = $request->validate([
            'comment' => [$commentRule, 'string', 'max:2000'],
        ], [
            'comment.required' => $decision === 'rejected'
                ? 'Bitte gib eine Begründung für die Ablehnung ein.'
                : 'Bitte gib einen Kommentar zur Genehmigung ein.',
        ]);

        $this->engine->completeStep($step, $decision, $data['comment'] ?? null, $user->id);
        $this->audit->log('workflow.step.completed_via_mail', $step, null, [
            'decision' => $decision,
            'instance_id' => $step->workflow_instance_id,
            'via' => 'signed_mail_link',
        ], "Schritt per Mail-Link entschieden ({$decision}) durch {$user->email}", $user->id);

        return view('mail-approval.done', [
            'decision' => $decision,
            'user' => $user,
            'workflowName' => $step->instance->workflow->name ?? '',
        ]);
    }

    private function assertEligible(WorkflowStepExecution $step, User $user): void
    {
        if (! request()->hasValidSignature()) {
            abort(403, 'Link ist ungültig oder abgelaufen.');
        }
        if (! $user->is_active) {
            abort(403, 'Konto ist deaktiviert.');
        }
        if ($step->completed_at) {
            abort(410, 'Diese Aufgabe wurde bereits abgeschlossen.');
        }
        if ($step->assigned_to_user_id && $step->assigned_to_user_id === $user->id) return;
        if ($step->assigned_to_role_id && $user->roles->pluck('id')->contains($step->assigned_to_role_id)) return;
        abort(403, 'Du bist für diese Aufgabe nicht zustaendig.');
    }
}
