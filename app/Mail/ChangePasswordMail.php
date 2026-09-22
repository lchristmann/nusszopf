<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * `auth0/change-password.mjml` (docs/email/README.md item 2), sent by
 * `App\Models\User::sendPasswordResetNotification()` instead of Laravel's
 * default reset notification, so it rides the shared mail layout like every
 * other Nusszopf 2 mail.
 */
class ChangePasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->recipientEmail],
            subject: 'Nusszopf – Neues Passwort erstellen',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.change-password',
            with: ['url' => $this->url],
        );
    }
}
