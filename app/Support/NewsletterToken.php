<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * The token in `/newsletter/subscribe/{token}` and `/newsletter/unsubscribe/{token}`.
 *
 * Historically a JWT signed with `EMAIL_SECRET`, valid for 7 days, carrying the
 * lead's id (docs/email/README.md items 6–7). Here: the same payload idea —
 * purpose, lead id, expiry — as base64url JSON plus an HMAC-SHA256 keyed from
 * `APP_KEY`, so it needs no extra secret or dependency and keeps the historical
 * single-segment URL shape (docs/rewrite/intentional-changes.md, "Newsletter
 * mechanics … are replaced"). Binding it to the lead's id means a link can
 * never confirm or delete a later lead for the same address.
 */
final class NewsletterToken
{
    public const SUBSCRIBE = 'subscribe';

    public const UNSUBSCRIBE = 'unsubscribe';

    public const LIFETIME_DAYS = 7;

    /**
     * `$email` goes into unsubscribe tokens only, as historically (`leadEmail`):
     * it lets the confirmation page name the address again after the lead is
     * already gone.
     */
    public static function make(string $purpose, string $leadId, ?string $email = null): string
    {
        $payload = self::encode((string) json_encode(array_filter([
            'p' => $purpose,
            'l' => $leadId,
            'm' => $email,
            'e' => now()->addDays(self::LIFETIME_DAYS)->getTimestamp(),
        ], fn ($value) => $value !== null)));

        return $payload.'.'.self::encode(self::sign($payload));
    }

    /**
     * `['lead' => id, 'email' => ?address]`, or null for anything that is not
     * an unexpired token of this purpose signed by this instance.
     *
     * @return array{lead: string, email: string|null}|null
     */
    public static function read(string $purpose, string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || ! hash_equals(self::encode(self::sign($parts[0])), $parts[1])) {
            return null;
        }

        $data = json_decode((string) self::decode($parts[0]), true);

        if (! is_array($data) || ($data['p'] ?? null) !== $purpose || ! is_string($data['l'] ?? null)
            || ! is_int($data['e'] ?? null) || $data['e'] < now()->getTimestamp()) {
            return null;
        }

        return ['lead' => $data['l'], 'email' => is_string($data['m'] ?? null) ? $data['m'] : null];
    }

    private static function sign(string $payload): string
    {
        $key = (string) Config::get('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7));
        }

        return hash_hmac('sha256', 'newsletter|'.$payload, $key, true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
