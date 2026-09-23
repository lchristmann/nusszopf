<?php

use App\Livewire\Newsletter\SubscribeForm;
use App\Mail\NewsletterSubscribeMail;
use App\Models\Lead;
use App\Support\Newsletter;
use App\Support\NewsletterToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The public sign-up form (`NewsletterForm.js`; placed on Home in slice 10)
 * and the shared `Newsletter::subscribe()` every path uses. Double opt-in
 * (BUG-011) and a neutral, idempotent answer for a known address (BUG-032).
 */
beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('newsletter:127.0.0.1');
});

function subscribeViaForm(string $email = 'nuss@example.com', string $name = 'Nuss'): Testable
{
    return Livewire::test(SubscribeForm::class)
        ->set('name', $name)
        ->set('email', $email)
        ->set('privacy', true)
        ->call('subscribe');
}

it('creates a pending lead with a consent record and mails the confirmation link', function () {
    Config::set('nusszopf.newsletter_consent_version', '2026-09');

    subscribeViaForm()
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Anmeldung.');

    $lead = Lead::sole();
    expect($lead->email)->toBe('nuss@example.com')
        ->and($lead->name)->toBe('Nuss')
        ->and($lead->confirmed_at)->toBeNull()
        ->and($lead->source)->toBe(Lead::SOURCE_FORM)
        ->and($lead->consent_version)->toBe('2026-09')
        ->and($lead->requested_at)->not->toBeNull();

    Mail::assertQueued(NewsletterSubscribeMail::class, function (NewsletterSubscribeMail $mail) use ($lead) {
        $token = basename(parse_url($mail->url, PHP_URL_PATH));

        return $mail->hasTo('nuss@example.com')
            && str_contains($mail->url, '/newsletter/subscribe/')
            && NewsletterToken::read(NewsletterToken::SUBSCRIBE, $token)['lead'] === $lead->id;
    });
});

it('stores no IP address or anything beyond the consent record', function () {
    subscribeViaForm();

    expect(array_keys(Lead::sole()->getAttributes()))->toEqualCanonicalizing([
        'id', 'email', 'name', 'source', 'consent_version', 'requested_at', 'confirmed_at', 'created_at', 'updated_at',
    ]);
});

it('validates with the historical copy', function (array $input, string $field, string $message) {
    Livewire::test(SubscribeForm::class)
        ->set(array_merge(['name' => 'Nuss', 'email' => 'nuss@example.com', 'privacy' => true], $input))
        ->call('subscribe')
        ->assertHasErrors([$field])
        ->assertSee($message);

    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'missing name' => [['name' => ''], 'name', 'Gib einen Namen ein'],
    'long name' => [['name' => str_repeat('a', 51)], 'name', 'Maximal 50 Zeichen'],
    'missing email' => [['email' => ''], 'email', 'Gib eine E-Mail-Adresse ein'],
    'invalid email' => [['email' => 'nuss'], 'email', 'Keine valide E-Mail-Adresse'],
    'no consent' => [['privacy' => false], 'privacy', 'Stimme den Datenschutzbestimmungen zu'],
]);

it('answers a pending address identically, refreshes its consent record and resends the mail (BUG-032)', function () {
    $lead = Lead::factory()->create([
        'email' => 'nuss@example.com',
        'source' => Lead::SOURCE_REGISTRATION,
        'consent_version' => 'old',
        'requested_at' => now()->subDays(5),
    ]);
    Config::set('nusszopf.newsletter_consent_version', 'new');

    subscribeViaForm()
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Anmeldung.');

    $lead->refresh();
    expect(Lead::count())->toBe(1)
        ->and($lead->source)->toBe(Lead::SOURCE_FORM)
        ->and($lead->consent_version)->toBe('new')
        ->and($lead->requested_at->isToday())->toBeTrue()
        ->and($lead->confirmed_at)->toBeNull();
    Mail::assertQueued(NewsletterSubscribeMail::class, 1);
});

it('answers a confirmed address identically, changes nothing and sends no mail (BUG-032)', function () {
    $lead = Lead::factory()->confirmed()->create(['email' => 'nuss@example.com', 'confirmed_at' => now()->subMonth()]);
    $before = $lead->fresh()->toArray();

    subscribeViaForm()
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Anmeldung.');

    expect($lead->fresh()->toArray())->toBe($before);
    Mail::assertNothingQueued();
});

it('is throttled to 10 attempts per 15 minutes per IP (Preserve)', function () {
    foreach (range(1, 10) as $i) {
        subscribeViaForm("n{$i}@example.com")->assertDispatched('toast', type: 'success');
    }

    subscribeViaForm('eleven@example.com')->assertDispatched('toast', type: 'error', message: SubscribeForm::error());
    expect(Lead::where('email', 'eleven@example.com')->exists())->toBeFalse();

    $this->travel(15)->minutes();
    subscribeViaForm('eleven@example.com')->assertDispatched('toast', type: 'success');
});

it('shows the historical error toast when the subscription cannot be stored', function () {
    Lead::saving(fn () => throw new RuntimeException('database down'));

    subscribeViaForm()->assertDispatched('toast', type: 'error', message: SubscribeForm::error());
    Mail::assertNothingQueued();
});

it('keeps the name within the 50-character column on every path', function () {
    Newsletter::subscribe('long@example.com', str_repeat('x', 80), Lead::SOURCE_PROFILE);

    expect(mb_strlen(Lead::sole()->name))->toBe(50);
});
