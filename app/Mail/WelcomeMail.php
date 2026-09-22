<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * `auth0/welcome.mjml` (docs/email/README.md item 1): sent unconditionally
 * on registration, whether through the form or a first-time Google login
 * that creates a new account — historically Auth0's own signup pipeline
 * fires it regardless of connection.
 */
class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->user->email],
            subject: 'Willkommen beim Nusszopf!',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.welcome');
    }
}
