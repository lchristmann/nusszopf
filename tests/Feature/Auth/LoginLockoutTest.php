<?php

use App\Livewire\Auth\LoginRegister;
use App\Mail\BlockedAccountMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * Register B-7/B-12 (docs/rewrite/decisions-register.md): a documented,
 * reasonable default replacing Auth0's own IP-block Attack Protection
 * (unrecoverable threshold) — sends the "blocked" notice only to the
 * account actually being targeted, and only once per lock.
 */
beforeEach(function () {
    RateLimiter::clear('login:127.0.0.1');
});

it('locks the specific account after 5 failed attempts and e-mails only its owner, once', function () {
    Mail::fake();
    $user = User::factory()->create(['password' => 'Str0ng!Pass']);

    for ($i = 0; $i < 5; $i++) {
        // Each attempt trips the IP limiter's own 60s decay too, so it is
        // cleared between attempts to isolate the per-account lock under test.
        RateLimiter::clear('login:127.0.0.1');

        Livewire::test(LoginRegister::class)
            ->set('emailOrName', $user->email)
            ->set('loginPassword', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['loginPassword']);
    }

    RateLimiter::clear('login:127.0.0.1');

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', $user->email)
        ->set('loginPassword', 'Str0ng!Pass')
        ->call('login')
        ->assertHasErrors(['loginPassword']);

    $this->assertGuest();
    Mail::assertQueuedCount(1);
    Mail::assertQueued(BlockedAccountMail::class, fn (BlockedAccountMail $mail) => $mail->user->is($user));
});

it('never locks or e-mails for attempts against an unknown account', function () {
    Mail::fake();

    for ($i = 0; $i < 6; $i++) {
        RateLimiter::clear('login:127.0.0.1');

        Livewire::test(LoginRegister::class)
            ->set('emailOrName', 'nobody@example.com')
            ->set('loginPassword', 'whatever123')
            ->call('login')
            ->assertHasErrors(['loginPassword']);
    }

    Mail::assertNothingQueued();
});

it('throttles by IP after 5 attempts regardless of which account was targeted', function () {
    $user = User::factory()->create(['password' => 'Str0ng!Pass']);

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(LoginRegister::class)
            ->set('emailOrName', $user->email)
            ->set('loginPassword', 'wrong-password')
            ->call('login');
    }

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', $user->email)
        ->set('loginPassword', 'Str0ng!Pass')
        ->call('login')
        ->assertHasErrors(['emailOrName']);

    $this->assertGuest();
});

it('unblocks exactly the IP/account pair the link was signed for', function () {
    Mail::fake();
    $user = User::factory()->create(['password' => 'Str0ng!Pass']);

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::clear('login:127.0.0.1');

        Livewire::test(LoginRegister::class)
            ->set('emailOrName', $user->email)
            ->set('loginPassword', 'wrong-password')
            ->call('login');
    }

    $mail = Mail::queued(BlockedAccountMail::class)->first();

    $this->get($mail->unblockUrl)->assertRedirect(route('login'));

    RateLimiter::clear('login:127.0.0.1');

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', $user->email)
        ->set('loginPassword', 'Str0ng!Pass')
        ->call('login')
        ->assertRedirect(route('projects.mine'));

    $this->assertAuthenticatedAs($user);
});
