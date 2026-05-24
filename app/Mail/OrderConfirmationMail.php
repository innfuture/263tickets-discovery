<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Buyer's order confirmation. Markdown email — Laravel's standard
 * template gives us a consistent look without committing to a custom
 * design. Each ticket renders the QR (via the public ticket viewer
 * URL — keeps the email lightweight) and a "Add to Apple/Google
 * Wallet" link.
 *
 * The signed link inside expires after the configured window so a
 * leaked email body alone doesn't grant indefinite access.
 */
class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $brand = optional($this->order->organization)->brand_name
            ?? optional($this->order->organization)->name
            ?? 'Tickets';

        return new Envelope(
            subject: "Your tickets — {$brand} · ".(string) ($this->order->event?->name ?? ''),
        );
    }

    public function content(): Content
    {
        $expiresIn = (int) config('storefront.fulfilment.signed_link_expiry_minutes', 60 * 24 * 7);

        return new Content(
            markdown: 'storefront.emails.order_confirmation',
            with: [
                'order' => $this->order,
                'receiptUrl' => URL::temporarySignedRoute(
                    'public.orders.show',
                    now()->addMinutes($expiresIn),
                    ['reference' => $this->order->reference],
                ),
            ],
        );
    }
}
