<?php

use App\Livewire\Auth\LoginRegister;
use App\Mail\NewsletterSubscribeMail;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * docs/rewrite/first-slice.md acceptance criterion #1: register with
 * email + username + password + required privacy consent, logged in
 * immediately afterward — no email-verification gate (matches history,
 * docs/authentication/README.md §2).
 */
it('registers a new account and logs the visitor in immediately', function () {
    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'nussknacker')
        ->set('email', 'nussknacker@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', true)
        ->call('register')
        ->assertRedirect(route('projects.mine'));

    $this->assertAuthenticated();

    $user = User::where('email', 'nussknacker@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('nussknacker')
        ->and(Hash::check('Str0ng!Pass', $user->password))->toBeTrue();
});

it('rejects registration without accepting the privacy consent', function () {
    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'nussknacker')
        ->set('email', 'nussknacker@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', false)
        ->call('register')
        ->assertHasErrors(['privacy']);

    $this->assertGuest();
});

it('rejects a password that fails the historical 5-rule policy', function () {
    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'nussknacker')
        ->set('email', 'nussknacker@example.com')
        ->set('registerPassword', 'short')
        ->set('privacy', true)
        ->call('register')
        ->assertHasErrors(['registerPassword']);
});

it('shows the distinguished duplicate-username error', function () {
    User::factory()->create(['name' => 'nussknacker']);

    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'nussknacker')
        ->set('email', 'someone-else@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', true)
        ->call('register')
        ->assertHasErrors(['username']);
});

it('rejects a username containing whitespace', function () {
    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'nuss knacker')
        ->set('email', 'nussknacker@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', true)
        ->call('register')
        ->assertHasErrors(['username']);
});

/**
 * Inventory item 27, decision A-1 / BUG-011: the "newsletter" checkbox
 * requests a pending subscription with the confirmation mail — historically
 * the lead was created already confirmed, with no mail at all.
 */
function registerWithNewsletter(bool $newsletter): void
{
    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'nussknacker')
        ->set('email', 'nussknacker@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', true)
        ->set('newsletter', $newsletter)
        ->call('register')
        ->assertRedirect(route('projects.mine'));
}

it('requests a pending newsletter subscription when the checkbox is ticked', function () {
    Mail::fake();

    registerWithNewsletter(true);

    $lead = Lead::sole();
    expect($lead->email)->toBe('nussknacker@example.com')
        ->and($lead->name)->toBe('nussknacker')
        ->and($lead->source)->toBe(Lead::SOURCE_REGISTRATION)
        ->and($lead->confirmed_at)->toBeNull();
    Mail::assertQueued(NewsletterSubscribeMail::class, fn ($mail) => $mail->hasTo('nussknacker@example.com'));
});

it('creates no lead when the checkbox is not ticked', function () {
    Mail::fake();

    registerWithNewsletter(false);

    expect(Lead::count())->toBe(0);
    Mail::assertNotQueued(NewsletterSubscribeMail::class);
});

it('still registers when the newsletter side effect fails (fail-open, as historically)', function () {
    Mail::fake();
    Lead::saving(fn () => throw new RuntimeException('database hiccup'));

    registerWithNewsletter(true);

    $this->assertAuthenticated();
    expect(User::where('email', 'nussknacker@example.com')->exists())->toBeTrue();
});
