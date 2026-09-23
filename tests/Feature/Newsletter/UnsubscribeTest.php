<?php

use App\Livewire\Newsletter\SubscribeForm;
use App\Livewire\Newsletter\UnsubscribeByEmail;
use App\Mail\NewsletterUnsubscribeMail;
use App\Models\Lead;
use App\Support\NewsletterToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * `/newsletter/unsubscribe/lead` (`pages/newsletter/unsubscribe/lead.js`):
 * the same answer for every valid address (BUG-033), the mail only for a
 * subscribed one.
 */
beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('newsletter:127.0.0.1');
});

it('renders the historical page', function () {
    $this->get(route('newsletter.unsubscribe'))
        ->assertOk()
        ->assertSee('Newsletter&shy;abmeldung', false)
        ->assertSee('Bitte trage die E-Mail-Adresse ein, die Du abmelden möchtest:')
        ->assertSee('Abmelden')
        ->assertSee('<meta name="robots" content="noindex,nofollow" />', false);
});

it('mails the unsubscribe link to a subscribed address', function (bool $confirmed) {
    $lead = Lead::factory()->create(['email' => 'nuss@example.com', 'confirmed_at' => $confirmed ? now() : null]);

    Livewire::test(UnsubscribeByEmail::class)
        ->set('email', 'nuss@example.com')
        ->call('unsubscribe')
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Abmeldung.');

    Mail::assertQueued(NewsletterUnsubscribeMail::class, function (NewsletterUnsubscribeMail $mail) use ($lead) {
        $token = basename(parse_url($mail->url, PHP_URL_PATH));

        return $mail->hasTo('nuss@example.com')
            && NewsletterToken::read(NewsletterToken::UNSUBSCRIBE, $token) === ['lead' => $lead->id, 'email' => 'nuss@example.com'];
    });
    expect($lead->fresh())->not->toBeNull();
})->with(['confirmed' => true, 'pending' => false]);

it('answers an unknown address identically and sends nothing (BUG-033)', function () {
    Livewire::test(UnsubscribeByEmail::class)
        ->set('email', 'stranger@example.com')
        ->call('unsubscribe')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Abmeldung.');

    Mail::assertNothingQueued();
});

it('validates with the historical copy', function (string $email, string $message) {
    Livewire::test(UnsubscribeByEmail::class)
        ->set('email', $email)
        ->call('unsubscribe')
        ->assertHasErrors(['email'])
        ->assertSee($message);
})->with([
    'missing' => ['', 'Gib eine E-Mail-Adresse ein.'],
    'invalid' => ['nuss', 'Keine valide E-Mail-Adresse.'],
]);

it('shares one throttle budget with the sign-up form (Preserve)', function () {
    foreach (range(1, 5) as $i) {
        Livewire::test(SubscribeForm::class)->set(['name' => 'N', 'email' => "n{$i}@example.com", 'privacy' => true])->call('subscribe');
        Livewire::test(UnsubscribeByEmail::class)->set('email', "n{$i}@example.com")->call('unsubscribe');
    }

    Livewire::test(UnsubscribeByEmail::class)
        ->set('email', 'n1@example.com')
        ->call('unsubscribe')
        ->assertDispatched('toast', type: 'error', message: SubscribeForm::ERROR);
});
