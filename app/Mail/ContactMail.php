<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * `sendgrid/contact.mjml` (docs/email/README.md item 5): a visitor's message
 * to a project through the "Über Nusszopf" contact path. Queued (BUG-009's
 * fix — the historical Node handler had no queue at all, relying only on
 * SendGrid's own delivery retries); a permanently-failed send lands in
 * `failed_jobs`, not silently vanishing.
 *
 * `Reply-To` set to the visitor's own address is the sixth slice's fix for
 * BUG-005 — historically replying went to `noreply@nusszopf.org`.
 */
class ContactMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $ownerEmail,
        public readonly string $visitorEmail,
        public readonly string $projectTitle,
        public readonly ?string $requestTitle,
        public readonly string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->ownerEmail],
            replyTo: [$this->visitorEmail],
            subject: 'Nusszopf – Kontaktanfrage',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact',
            with: [
                'visitorEmail' => $this->visitorEmail,
                'projectTitle' => $this->projectTitle,
                'requestTitle' => $this->requestTitle,
                // Not `message`: Laravel injects its own `$message` (Illuminate\Mail\Message) into
                // every rendered mail view, which a same-named view variable would silently shadow.
                'visitorMessage' => $this->message,
            ],
        );
    }
}
