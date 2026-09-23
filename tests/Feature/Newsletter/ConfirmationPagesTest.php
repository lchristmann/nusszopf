<?php

use App\Models\Lead;
use App\Support\NewsletterToken;

/**
 * `/newsletter/subscribe/{token}` and `/newsletter/unsubscribe/{token}`
 * (docs/design/screen-specs.md). An invalid link is a 404 rendered in place
 * (the historical 307 to `/404` is replaced), including BUG-034's vanished lead.
 */
function subscribeUrl(Lead $lead): string
{
    return route('newsletter.subscribe.confirm', NewsletterToken::make(NewsletterToken::SUBSCRIBE, $lead->id));
}

function unsubscribeUrl(Lead $lead): string
{
    return route('newsletter.unsubscribe.confirm', NewsletterToken::make(NewsletterToken::UNSUBSCRIBE, $lead->id, $lead->email));
}

it('confirms the subscription and shows the historical page', function () {
    $lead = Lead::factory()->create(['email' => 'nuss@example.com']);

    $this->get(subscribeUrl($lead))
        ->assertOk()
        ->assertSee('Juhuu! Nussige News!')
        ->assertSee('nuss@example.com')
        ->assertSee('wurde zum Newsletter angemeldet. Schön, dass Du mit dabei bist!')
        ->assertSee('Zum Nusszopf')
        ->assertSee('<meta name="robots" content="noindex,nofollow" />', false);

    expect($lead->fresh()->confirmed_at)->not->toBeNull();
});

it('is idempotent: a second click keeps the first confirmation time', function () {
    $lead = Lead::factory()->create();
    $url = subscribeUrl($lead);

    $this->get($url)->assertOk();
    $first = $lead->fresh()->confirmed_at;

    $this->travel(1)->day();
    $this->get($url)->assertOk();

    expect($lead->fresh()->confirmed_at->equalTo($first))->toBeTrue();
});

it('answers 404 for an unknown, tampered or expired subscribe link', function () {
    $lead = Lead::factory()->create();
    $url = subscribeUrl($lead);

    $this->get(route('newsletter.subscribe.confirm', 'not-a-token'))->assertNotFound();
    $this->get($url.'x')->assertNotFound();

    $this->travel(8)->days();
    $this->get($url)->assertNotFound();

    expect($lead->fresh()->confirmed_at)->toBeNull();
});

it('answers 404 for a valid subscribe link whose lead is gone (BUG-034)', function () {
    $lead = Lead::factory()->create();
    $url = subscribeUrl($lead);
    $lead->delete();

    $this->get($url)->assertNotFound()->assertDontSee('wurde zum Newsletter angemeldet');
});

it('never lets an unsubscribe link confirm, or a subscribe link unsubscribe', function () {
    $lead = Lead::factory()->create();
    $unsubscribeToken = NewsletterToken::make(NewsletterToken::UNSUBSCRIBE, $lead->id, $lead->email);
    $subscribeToken = NewsletterToken::make(NewsletterToken::SUBSCRIBE, $lead->id);

    $this->get(route('newsletter.subscribe.confirm', $unsubscribeToken))->assertNotFound();
    $this->get(route('newsletter.unsubscribe.confirm', $subscribeToken))->assertNotFound();

    expect($lead->fresh())->not->toBeNull()->and($lead->fresh()->confirmed_at)->toBeNull();
});

it('does not confirm a later lead for the same address with an old link', function () {
    $old = Lead::factory()->create(['email' => 'nuss@example.com']);
    $url = subscribeUrl($old);
    $old->delete();
    $new = Lead::factory()->create(['email' => 'nuss@example.com']);

    $this->get($url)->assertNotFound();
    expect($new->fresh()->confirmed_at)->toBeNull();
});

it('unsubscribes via the mailed link and shows the historical page', function () {
    $lead = Lead::factory()->confirmed()->create(['email' => 'nuss@example.com']);

    $this->get(unsubscribeUrl($lead))
        ->assertOk()
        ->assertSee('Schade Marmelade')
        ->assertSee('nuss@example.com')
        ->assertSee('wurde vom Newsletter abgemeldet.')
        ->assertSee('Feedback senden')
        ->assertSee('mailto:mail@nusszopf.org?subject=Sponsorship%20%7C%20Partnerschaft%20%7C%20Feedback', false);

    expect(Lead::find($lead->id))->toBeNull();
});

it('keeps answering an unsubscribe link after the lead is gone, as historically', function () {
    $lead = Lead::factory()->confirmed()->create(['email' => 'nuss@example.com']);
    $url = unsubscribeUrl($lead);

    $this->get($url)->assertOk();
    $this->get($url)->assertOk()->assertSee('nuss@example.com');
});

it('does not delete a later lead for the same address with an old unsubscribe link', function () {
    $old = Lead::factory()->confirmed()->create(['email' => 'nuss@example.com']);
    $url = unsubscribeUrl($old);
    $old->delete();
    $new = Lead::factory()->confirmed()->create(['email' => 'nuss@example.com']);

    $this->get($url)->assertOk();
    expect($new->fresh())->not->toBeNull();
});

it('answers 404 for an unknown, tampered or expired unsubscribe link', function () {
    $lead = Lead::factory()->confirmed()->create();
    $url = unsubscribeUrl($lead);

    $this->get(route('newsletter.unsubscribe.confirm', 'not-a-token'))->assertNotFound();
    $this->get($url.'x')->assertNotFound();

    $this->travel(8)->days();
    $this->get($url)->assertNotFound();

    expect($lead->fresh())->not->toBeNull();
});
