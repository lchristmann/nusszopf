<?php

use App\Livewire\Auth\LoginRegister;
use App\Mail\VerifyEmailMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * Decision A-3 (docs/rewrite/decisions-register.md): a new, non-blocking
 * gate — historical Nusszopf never verified an e-mail address, so there is
 * no behavior here to reproduce, only new behavior to test.
 */
it('sends the welcome mail and a verification mail on registration, without gating login', function () {
    Mail::fake();

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
    expect($user->hasVerifiedEmail())->toBeFalse();

    Mail::assertQueued(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->user->is($user));
    Mail::assertQueued(VerifyEmailMail::class, fn (VerifyEmailMail $mail) => $mail->user->is($user));
});

it('marks the address verified from the signed link, without signing the visitor in', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addDays(7), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->get($url)->assertRedirect(route('login'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertGuest();
});

it('rejects a verification link whose hash does not match the account e-mail', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addDays(7), ['id' => $user->id, 'hash' => sha1('someone-else@example.com')]);

    $this->get($url)->assertNotFound();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired or tampered verification link', function () {
    $user = User::factory()->unverified()->create();

    $expired = URL::temporarySignedRoute('verification.verify', now()->subDay(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($expired)->assertForbidden();

    $tampered = URL::temporarySignedRoute('verification.verify', now()->addDays(7), ['id' => $user->id, 'hash' => sha1($user->email)]).'&tampered=1';
    $this->get($tampered)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('lets a signed-in user resend the verification mail', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('verification.send'))
        ->assertRedirect();

    Mail::assertQueued(VerifyEmailMail::class, fn (VerifyEmailMail $mail) => $mail->user->is($user));
});
