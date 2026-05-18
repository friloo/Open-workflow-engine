<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkflowStepExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TaskReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkflowStepExecution $step,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Workflow] Erinnerung: offene Aufgabe in {$this->step->instance->workflow->name}",
        );
    }

    public function content(): Content
    {
        $expires = $this->step->due_at?->copy()->addDays(2) ?? now()->addDays(14);
        return new Content(
            view: 'emails.workflow-task-reminder',
            with: [
                'step' => $this->step,
                'recipient' => $this->recipient,
                'instance' => $this->step->instance,
                'workflow' => $this->step->instance->workflow,
                'taskUrl' => route('tasks.show', $this->step),
                'daysOpen' => (int) $this->step->assigned_at->diffInDays(now()),
                'approveUrl' => URL::temporarySignedRoute('mail-approval.show', $expires, [
                    'step' => $this->step->id, 'user' => $this->recipient->id, 'decision' => 'approved',
                ]),
                'rejectUrl' => URL::temporarySignedRoute('mail-approval.show', $expires, [
                    'step' => $this->step->id, 'user' => $this->recipient->id, 'decision' => 'rejected',
                ]),
            ],
        );
    }
}
