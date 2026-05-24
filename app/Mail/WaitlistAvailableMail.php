<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when waitlist inventory frees up. Carries a deep link to the
 * event's storefront page with a short conversion window — once the
 * window lapses, the slot is offered to the next waitlist entry.
 */
class WaitlistAvailableMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public WaitlistEntry $entry) {}

    public function envelope(): Envelope
    {
        $eventName = optional($this->entry->event)->name ?? 'an event';

        return new Envelope(
            subject: "Good news — tickets are available for {$eventName}",
        );
    }

    public function content(): Content
    {
        $eventSlug = $this->entry->event?->slug;
        $convertUrl = rtrim((string) config('storefront.public_url'), '/')
            .'/events/'.$eventSlug.'?waitlist='.$this->entry->uuid;

        return new Content(
            markdown: 'storefront.emails.waitlist_available',
            with: [
                'entry' => $this->entry,
                'eventName' => optional($this->entry->event)->name,
                'convertUrl' => $convertUrl,
            ],
        );
    }
}
