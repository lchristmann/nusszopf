<?php

use App\Livewire\Profile\Profile;
use App\Mail\NewsletterSubscribeMail;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * The Profile newsletter subsection (`profile.js`): subscribe form until the
 * lead is confirmed, unsubscribe button afterwards. Subscribing is double
 * opt-in now (BUG-011) — historically it confirmed the lead on the spot.
 */
beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('newsletter:127.0.0.1');
});

it('shows the subscribe form without a lead and while the lead is pending', function (bool $pending) {
    $user = User::factory()->create();
    if ($pending) {
        Lead::factory()->create(['email' => $user->email]);
    }

    $this->actingAs($user)->get(route('profile'))
        ->assertOk()
        ->assertSee('Wir versorgen Dich mit backfrischen Nusszopf')
        ->assertSee('btn_newsletter-subscribe_settings-page', false)
        ->assertDontSee('btn_newsletter-unsubscribe_settings-page', false);
})->with(['no lead' => false, 'pending lead' => true]);

it('shows the unsubscribe button once the lead is confirmed', function () {
    $user = User::factory()->create();
    Lead::factory()->confirmed()->create(['email' => $user->email]);

    $this->actingAs($user)->get(route('profile'))
        ->assertSee('Du möchtest dich vom nussigsten Newsletter aller Zeiten abmelden?')
        ->assertSee('btn_newsletter-unsubscribe_settings-page', false)
        ->assertDontSee('btn_newsletter-subscribe_settings-page', false);
});

it('requires the privacy consent', function () {
    Livewire::actingAs(User::factory()->create())->test(Profile::class)
        ->call('subscribeNewsletter')
        ->assertHasErrors(['newsletterPrivacy'])
        ->assertSee('Stimme den Datenschutzbestimmungen zu');

    expect(Lead::count())->toBe(0);
});

it('requests a pending subscription for the account address and mails the link (BUG-011)', function () {
    $user = User::factory()->create(['name' => 'nussknacker']);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('newsletterPrivacy', true)
        ->call('subscribeNewsletter')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Anmeldung.')
        ->assertNotDispatched('toast', message: 'Du bist jetzt angemeldet!');

    $lead = Lead::sole();
    expect($lead->email)->toBe($user->email)
        ->and($lead->name)->toBe('nussknacker')
        ->and($lead->source)->toBe(Lead::SOURCE_PROFILE)
        ->and($lead->confirmed_at)->toBeNull();
    Mail::assertQueued(NewsletterSubscribeMail::class, fn ($mail) => $mail->hasTo($user->email));
});

it('unsubscribes the own address only, immediately', function () {
    $user = User::factory()->create();
    Lead::factory()->confirmed()->create(['email' => $user->email]);
    $other = Lead::factory()->confirmed()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->call('unsubscribeNewsletter')
        ->assertDispatched('toast', type: 'success', message: 'Du bist jetzt abgemeldet!');

    expect(Lead::where('email', $user->email)->exists())->toBeFalse()
        ->and($other->fresh())->not->toBeNull();
});

it('needs a session: a guest cannot reach the subsection', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
});
