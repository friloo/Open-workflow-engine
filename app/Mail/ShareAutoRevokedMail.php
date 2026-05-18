<?php

namespace App\Mail;

use App\Models\ShareLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShareAutoRevokedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShareLink $share) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Freigabe widerrufen] '.($this->share->attachment?->original_name ?: 'Dokument'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.share-auto-revoked',
            with: [
                'share' => $this->share,
                'attachment' => $this->share->effectiveAttachment(),
            ],
        );
    }
}
