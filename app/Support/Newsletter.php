<?php

namespace App\Support;

use App\Mail\NewsletterSubscribeMail;
use App\Mail\NewsletterUnsubscribeMail;
use App\Models\Lead;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The historical `/api/newsletter` handlers (`newsletter.function.js`), one
 * method each, shared by every path that subscribes someone
 * (docs/domain/workflows.md, "newsletter lead creation, confirmation and
 * cleanup"). The corrections are documented in docs/rewrite/intentional-
 * changes.md: double opt-in on every path (BUG-011) and answers that never
 * depend on whether an address is known (BUG-032, BUG-033) — callers get
 * nothing back to branch on.
 */
final class Newsletter
{
    /** Unconfirmed leads older than this (since their latest request) are purged. */
    public const PURGE_AFTER_DAYS = 14;

    /**
     * Confirmation mails of one kind one address receives per hour, however many senders ask (P-4, SEC-04).
     * The per-IP budget of the forms cannot stop a sender that changes its address.
     */
    public const MAILS_PER_ADDRESS_PER_HOUR = 3;

    /**
     * `handleSubscribe`, for all three sources. New address → pending lead +
     * confirmation mail. Pending → consent record refreshed + a fresh mail.
     * Confirmed → nothing (and no mail).
     */
    public static function subscribe(string $email, string $name, string $source): void
    {
        $lead = Lead::query()->createOrFirst(['email' => $email], [
            'name' => Str::limit($name, 50, ''),
            'source' => $source,
            'consent_version' => self::consentVersion(),
            'requested_at' => now(),
        ]);

        if ($lead->isConfirmed()) {
            return;
        }

        if (! $lead->wasRecentlyCreated) {
            $lead->update([
                'name' => Str::limit($name, 50, ''),
                'source' => $source,
                'consent_version' => self::consentVersion(),
                'requested_at' => now(),
            ]);
        }

        if (! self::mayMail(NewsletterToken::SUBSCRIBE, $lead->email)) {
            return;
        }

        Mail::send(new NewsletterSubscribeMail(
            $lead->email,
            route('newsletter.subscribe.confirm', NewsletterToken::make(NewsletterToken::SUBSCRIBE, $lead->id)),
        ));
    }

    /**
     * `handleSubscribeConfirm`. The lead, or null when the link is invalid,
     * expired or its lead is gone (BUG-034). Idempotent: a second click keeps
     * the first `confirmed_at`.
     */
    public static function confirm(string $token): ?Lead
    {
        $payload = NewsletterToken::read(NewsletterToken::SUBSCRIBE, $token);
        $lead = $payload !== null ? Lead::find($payload['lead']) : null;

        if ($lead !== null && ! $lead->isConfirmed()) {
            $lead->update(['confirmed_at' => now()]);
        }

        return $lead;
    }

    /** `handleUnsubscribe`: mails the link only when a lead exists (BUG-033). */
    public static function requestUnsubscribe(string $email): void
    {
        $lead = Lead::where('email', $email)->first();

        if ($lead === null || ! self::mayMail(NewsletterToken::UNSUBSCRIBE, $lead->email)) {
            return;
        }

        Mail::send(new NewsletterUnsubscribeMail(
            $lead->email,
            route('newsletter.unsubscribe.confirm', NewsletterToken::make(NewsletterToken::UNSUBSCRIBE, $lead->id, $lead->email)),
        ));
    }

    /**
     * `handleUnsubscribeConfirm`: deletes the lead the link was issued for and
     * returns the address to show, or null for an invalid/expired link. Still
     * answers after the lead is gone, as historically.
     */
    public static function confirmUnsubscribe(string $token): ?string
    {
        $payload = NewsletterToken::read(NewsletterToken::UNSUBSCRIBE, $token);

        if ($payload === null || $payload['email'] === null) {
            return null;
        }

        Lead::whereKey($payload['lead'])->delete();

        return $payload['email'];
    }

    /** Profile unsubscribe and account deletion: the session already proves ownership. */
    public static function forget(string $email): void
    {
        Lead::where('email', $email)->delete();
    }

    /** Scheduled daily (routes/console.php). Returns the number of leads deleted. */
    public static function purgeUnconfirmed(): int
    {
        return Lead::whereNull('confirmed_at')
            ->where('requested_at', '<', now()->subDays(self::PURGE_AFTER_DAYS))
            ->delete();
    }

    /**
     * Counts one mail of `$purpose` to `$email` and says whether it may go out. Past the limit the caller
     * answers exactly as before and simply sends nothing, so the answer still reveals nothing.
     */
    private static function mayMail(string $purpose, string $email): bool
    {
        $key = 'newsletter-mail:'.$purpose.':'.hash('sha256', mb_strtolower($email));

        if (RateLimiter::tooManyAttempts($key, self::MAILS_PER_ADDRESS_PER_HOUR)) {
            return false;
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        return true;
    }

    public static function consentVersion(): string
    {
        return (string) Config::get('nusszopf.newsletter_consent_version');
    }
}
