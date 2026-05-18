<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkflowStepExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowTaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkflowStepExecution $step,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        $instance = $this->step->instance;
        return new Envelope(
            subject: "[Workflow] Neue Aufgabe: {$instance->workflow->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workflow-task-assigned',
            with: [
                'step' => $this->step,
                'recipient' => $this->recipient,
                'instance' => $this->step->instance,
                'workflow' => $this->step->instance->workflow,
                'taskUrl' => route('tasks.show', $this->step),
            ],
        );
    }
}
