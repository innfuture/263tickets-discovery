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
 * Single-use sign-in link for buyer accounts. The token plaintext
 * lives ONLY in the URL inside this email — never persisted.
 */
class BuyerMagicLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $email, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your sign-in link');
    }

    public function content(): Content
    {
        $url = rtrim((string) config('storefront.public_url'), '/')
            .'/buyer/auth/verify?token='.urlencode($this->token);

        return new Content(
            markdown: 'storefront.emails.buyer_magic_link',
            with: [
                'url' => $url,
                'ttlMinutes' => 15,
            ],
        );
    }
}
