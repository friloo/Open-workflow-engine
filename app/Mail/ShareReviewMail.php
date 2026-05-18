<?php

namespace App\Mail;

use App\Models\ShareLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ShareReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShareLink $share) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Freigabe-Pruefung] '.($this->share->attachment?->original_name ?: 'Dokument'));
    }

    public function content(): Content
    {
        $expiry = now()->addDays((int) \App\Support\Settings::get('shares.review_grace_days', 3));

        return new Content(
            view: 'emails.share-review',
            with: [
                'share' => $this->share,
                'attachment' => $this->share->effectiveAttachment(),
                'confirmUrl' => URL::temporarySignedRoute('shares.review.confirm', $expiry, ['share' => $this->share->id]),
                'revokeUrl' => URL::temporarySignedRoute('shares.review.revoke', $expiry, ['share' => $this->share->id]),
                'autoRevokeAt' => $expiry,
            ],
        );
    }
}
