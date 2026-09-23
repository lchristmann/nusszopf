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

it('shows the distinguished duplicate-username error toast', function () {
    User::factory()->create(['name' => 'nussknacker']);

    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'nussknacker')
        ->set('email', 'someone-else@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', true)
        ->call('register')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'error', message: 'Der Username existiert leider schon.');

    expect(User::where('email', 'someone-else@example.com')->exists())->toBeFalse();
});

it('answers a taken e-mail address with the generic error toast, as Auth0 did', function () {
    User::factory()->create(['email' => 'vergeben@example.com']);

    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'neuling')
        ->set('email', 'vergeben@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', true)
        ->call('register')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

    expect(User::where('name', 'neuling')->exists())->toBeFalse();
    $this->assertGuest();
});

it('shows the historical loading toasts once the form is valid', function () {
    Livewire::test(LoginRegister::class)
        ->set('tab', 'register')
        ->set('username', 'neuling')
        ->set('email', 'neuling@example.com')
        ->set('registerPassword', 'Str0ng!Pass')
        ->set('privacy', true)
        ->call('register')
        ->assertDispatched('toast', type: 'loading', message: 'Du wirst registriert und eingeloggt.');

    auth()->logout();
    Livewire::test(LoginRegister::class)
        ->set('emailOrName', 'neuling')
        ->set('loginPassword', 'Str0ng!Pass')
        ->call('login')
        ->assertDispatched('toast', type: 'loading', message: 'Du wirst einloggt.');

    Livewire::test(LoginRegister::class)->call('login')->assertNotDispatched('toast');
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

it('limits each IP to 10 new accounts per 15 minutes, in place of Auth0\'s bot-detection captcha', function () {
    foreach (range(1, 10) as $i) {
        auth()->logout();
        Livewire::test(LoginRegister::class, ['tab' => 'register'])
            ->set('username', "nutzer{$i}")
            ->set('email', "nutzer{$i}@example.test")
            ->set('registerPassword', 'Str0ng!Passw0rd')
            ->set('privacy', true)
            ->call('register')
            ->assertHasNoErrors();
    }

    auth()->logout();
    Livewire::test(LoginRegister::class, ['tab' => 'register'])
        ->set('username', 'nutzer11')
        ->set('email', 'nutzer11@example.test')
        ->set('registerPassword', 'Str0ng!Passw0rd')
        ->set('privacy', true)
        ->call('register')
        ->assertHasErrors(['username'])
        ->assertSee('Zu viele Versuche. Bitte warte kurz.');

    expect(User::where('name', 'nutzer11')->exists())->toBeFalse();

    $this->travel(15)->minutes();
    Livewire::test(LoginRegister::class, ['tab' => 'register'])
        ->set('username', 'nutzer11')
        ->set('email', 'nutzer11@example.test')
        ->set('registerPassword', 'Str0ng!Passw0rd')
        ->set('privacy', true)
        ->call('register')
        ->assertHasNoErrors();
});

it('does not spend the registration budget on a submission that fails validation', function () {
    foreach (range(1, 12) as $i) {
        Livewire::test(LoginRegister::class, ['tab' => 'register'])->call('register')->assertHasErrors(['username']);
    }

    Livewire::test(LoginRegister::class, ['tab' => 'register'])
        ->set('username', 'geduldig')
        ->set('email', 'geduldig@example.test')
        ->set('registerPassword', 'Str0ng!Passw0rd')
        ->set('privacy', true)
        ->call('register')
        ->assertHasNoErrors();
});
