<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * `sendgrid/newsletter/unsubscribe.mjml` (docs/email/README.md item 7); the link
 * expires after 7 days (App\Support\NewsletterToken). Historically the
 * template also received `username`, which its body never uses — not passed.
 */
class NewsletterUnsubscribeMail extends Mailable implements ShouldQueue
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
            subject: 'Nussiger Newsletter – Abmeldebestätigung',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.newsletter-unsubscribe',
            with: ['url' => $this->url],
        );
    }
}
