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
 * `auth0/blocked-account.mjml` (docs/email/README.md item 3). Historically
 * Auth0's Attack Protection blocks the offending IP address and e-mails the
 * targeted account owner with `{{ user.source_ip }}`/`{{ user.city }}`/
 * `{{ user.country }}` and an unblock link. Nusszopf 2 has no geo-IP lookup
 * (a new mandatory third-party/hosted dependency this self-hosted app should
 * not require — same "dependency dropped" category as ui-avatars.com and the
 * SendGrid-CDN mail logo, docs/rewrite/intentional-changes.md) — the city and
 * country clauses are dropped, the source IP is kept.
 */
class BlockedAccountMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $sourceIp,
        public readonly string $unblockUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->user->email],
            subject: 'Nusszopf – IP-Adresse blockiert',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.blocked-account',
            with: ['sourceIp' => $this->sourceIp, 'unblockUrl' => $this->unblockUrl],
        );
    }
}
