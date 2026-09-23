<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * `sendgrid/newsletter/subscribe.mjml` (docs/email/README.md item 6); the link
 * expires after 7 days (App\Support\NewsletterToken). Historically the
 * template also received `username`, which its body never uses — not passed.
 */
class NewsletterSubscribeMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $email,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->email],
            subject: 'Nussiger Newsletter – Anmeldebestätigung',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.newsletter-subscribe',
            with: ['url' => $this->url],
        );
    }
}
