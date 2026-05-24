<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StakeholderMagicLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $email, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sign in to your stakeholder portal');
    }

    public function content(): Content
    {
        $url = rtrim((string) config('storefront.public_url'), '/')
            .'/stakeholder/auth/verify?token='.urlencode($this->token);

        return new Content(
            markdown: 'storefront.emails.stakeholder_magic_link',
            with: ['url' => $url, 'ttlMinutes' => 30],
        );
    }
}
