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
 * There is no historical equivalent (decision A-3,
 * docs/rewrite/decisions-register.md — historical Nusszopf never verified an
 * e-mail address). This is new copy for a new, deliberately non-blocking
 * feature: verifying only gates publishing the personal contact address and
 * the future newsletter subscription, never login/registration.
 */
class VerifyEmailMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->user->email],
            subject: 'Nusszopf – Bestätige deine E-Mail-Adresse',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verify-email',
            with: ['url' => $this->url],
        );
    }
}
