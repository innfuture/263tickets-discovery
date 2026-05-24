<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\TicketTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the recipient of a buyer-to-buyer ticket transfer. Carries
 * the claim_token URL so they can accept / decline without an
 * account.
 *
 * Sender (sourceOrderItem.order.buyer_email) is in the From: line
 * description ("So-and-so sent you a ticket"). Real From: stays the
 * platform's transactional address so DKIM / SPF aren't spoofed.
 */
class TicketTransferOfferedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TicketTransfer $transfer) {}

    public function envelope(): Envelope
    {
        $eventName = optional(
            $this->transfer->offlineTicket?->event
        )->name ?? 'an event';

        return new Envelope(
            subject: "{$this->transfer->from_email} sent you a ticket to {$eventName}",
        );
    }

    public function content(): Content
    {
        $claimUrl = rtrim((string) config('storefront.public_url'), '/')
            .'/transfers/claim/'.$this->transfer->claim_token;

        return new Content(
            markdown: 'storefront.emails.ticket_transfer_offered',
            with: [
                'transfer' => $this->transfer,
                'claimUrl' => $claimUrl,
                'eventName' => optional(
                    $this->transfer->offlineTicket?->event
                )->name,
            ],
        );
    }
}
