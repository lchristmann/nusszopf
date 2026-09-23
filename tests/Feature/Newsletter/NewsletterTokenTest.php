<?php

use App\Support\NewsletterToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The 7-day link token (docs/rewrite/intentional-changes.md, "Newsletter
 * mechanics … are replaced"): tamper-proof, expiring, purpose-bound.
 */
it('round-trips the lead id, and the address for unsubscribe tokens only', function () {
    $id = (string) Str::uuid();

    expect(NewsletterToken::read(NewsletterToken::SUBSCRIBE, NewsletterToken::make(NewsletterToken::SUBSCRIBE, $id)))
        ->toBe(['lead' => $id, 'email' => null])
        ->and(NewsletterToken::read(NewsletterToken::UNSUBSCRIBE, NewsletterToken::make(NewsletterToken::UNSUBSCRIBE, $id, 'a@example.com')))
        ->toBe(['lead' => $id, 'email' => 'a@example.com']);
});

it('is a single URL path segment', function () {
    $token = NewsletterToken::make(NewsletterToken::UNSUBSCRIBE, (string) Str::uuid(), 'a+b/c@example.com');

    expect($token)->toMatch('/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/');
});

it('rejects a token for the other purpose', function () {
    $token = NewsletterToken::make(NewsletterToken::SUBSCRIBE, (string) Str::uuid());

    expect(NewsletterToken::read(NewsletterToken::UNSUBSCRIBE, $token))->toBeNull();
});

it('rejects a tampered payload or signature', function () {
    $token = NewsletterToken::make(NewsletterToken::SUBSCRIBE, (string) Str::uuid());
    [$payload, $signature] = explode('.', $token);

    $forged = rtrim(strtr(base64_encode((string) json_encode(['p' => 'subscribe', 'l' => (string) Str::uuid(), 'e' => now()->addYear()->getTimestamp()])), '+/', '-_'), '=');

    expect(NewsletterToken::read(NewsletterToken::SUBSCRIBE, $forged.'.'.$signature))->toBeNull()
        ->and(NewsletterToken::read(NewsletterToken::SUBSCRIBE, $payload.'.'.strrev($signature)))->toBeNull()
        ->and(NewsletterToken::read(NewsletterToken::SUBSCRIBE, $payload))->toBeNull()
        ->and(NewsletterToken::read(NewsletterToken::SUBSCRIBE, 'garbage'))->toBeNull()
        ->and(NewsletterToken::read(NewsletterToken::SUBSCRIBE, ''))->toBeNull();
});

it('rejects a token signed by another instance', function () {
    $token = NewsletterToken::make(NewsletterToken::SUBSCRIBE, (string) Str::uuid());

    Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    expect(NewsletterToken::read(NewsletterToken::SUBSCRIBE, $token))->toBeNull();
});

it('expires after 7 days', function () {
    $id = (string) Str::uuid();
    $token = NewsletterToken::make(NewsletterToken::SUBSCRIBE, $id);

    $this->travel(7)->days();
    expect(NewsletterToken::read(NewsletterToken::SUBSCRIBE, $token))->not->toBeNull();

    $this->travel(1)->minute();
    expect(NewsletterToken::read(NewsletterToken::SUBSCRIBE, $token))->toBeNull();
});
