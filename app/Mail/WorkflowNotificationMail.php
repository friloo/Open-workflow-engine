<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public WorkflowInstance $instance,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workflow-notification',
            with: [
                'bodyText' => $this->body,
                'instance' => $this->instance,
                'recipient' => $this->recipient,
            ],
        );
    }
}
