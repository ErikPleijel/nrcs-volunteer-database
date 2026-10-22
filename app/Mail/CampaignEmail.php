<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One campaign email to one recipient. The body is already rendered per recipient
 * (placeholders, unsubscribe footer) by CampaignSendRunner, so it is sent as-is.
 */
class CampaignEmail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $htmlBody,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly ?string $replyToEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail)] : [],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody);
    }
}
