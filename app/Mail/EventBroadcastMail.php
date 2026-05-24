<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Outgoing event broadcast — organizer pushes an announcement to
 * every paid-order email for an event. Body is treated as untrusted
 * plain text; the markdown template wraps it in the platform shell
 * so DKIM/SPF stays on the transactional domain.
 */
class EventBroadcastMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'storefront.emails.event_broadcast',
            with: [
                'subjectLine' => $this->subjectLine,
                'body' => $this->body,
            ],
        );
    }
}
