<?php

use App\Livewire\Auth\LoginRegister;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

/**
 * Google login (docs/authentication/README.md §3; register B-6): the only
 * functional social connection historically (BUG-012, Apple never wired
 * up). Hidden entirely when GOOGLE_CLIENT_ID/SECRET are unset.
 */
it('hides the button and 404s both routes when Google is not configured', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get(route('login'))->assertDontSee('btn_login-google', false);

    $this->get(route('google.redirect'))->assertNotFound();
    $this->get(route('google.callback'))->assertNotFound();
});

it('shows the Google button on the login tab only, once configured', function () {
    config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);

    Livewire::test(LoginRegister::class)->assertSee('btn_login-google', false);
    Livewire::test(LoginRegister::class)->set('tab', 'register')->assertDontSee('btn_login-google', false);
});

it('creates a new, pre-verified account on first Google login and sends the welcome mail', function () {
    Mail::fake();
    config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'g-1',
        'email' => 'nussknacker@example.com',
        'nickname' => 'nussknacker',
        'avatar' => 'https://example.com/avatar.jpg',
        'email_verified' => true,
    ]));

    $this->get(route('google.callback'))->assertRedirect(route('projects.mine'));

    $user = User::where('email', 'nussknacker@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('g-1')
        ->and($user->name)->toBe('nussknacker')
        ->and($user->picture)->toBe('https://example.com/avatar.jpg')
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Mail::assertQueued(WelcomeMail::class);
});

it('refuses to create an account from an unverified Google e-mail', function () {
    config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'g-2',
        'email' => 'unverified@example.com',
        'email_verified' => false,
    ]));

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    expect(User::where('email', 'unverified@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('links an existing account by e-mail only when Google asserts it is verified', function () {
    config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);
    $user = User::factory()->create(['email' => 'nussknacker@example.com']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'g-3',
        'email' => 'nussknacker@example.com',
        'email_verified' => false,
    ]));

    $this->get(route('google.callback'))->assertRedirect(route('login'));
    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'g-3',
        'email' => 'nussknacker@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('google.callback'))->assertRedirect(route('projects.mine'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('g-3');
});

it('fills an empty picture from Google but never overwrites a manual upload (BUG-004)', function () {
    config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);
    $withPicture = User::factory()->create(['email' => 'has-picture@example.com', 'picture' => 'https://mine.example/avatar.png']);
    $withoutPicture = User::factory()->create(['email' => 'no-picture@example.com', 'picture' => null]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'g-4',
        'email' => 'has-picture@example.com',
        'avatar' => 'https://google.example/avatar.jpg',
        'email_verified' => true,
    ]));
    $this->get(route('google.callback'));
    expect($withPicture->fresh()->picture)->toBe('https://mine.example/avatar.png');

    // `google.callback` sits behind `guest` middleware — the previous
    // request left the client authenticated, so it must log out before a
    // second, independent login attempt can reach the controller at all.
    $this->withoutMiddleware(PreventRequestForgery::class)->post(route('logout'));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'g-5',
        'email' => 'no-picture@example.com',
        'avatar' => 'https://google.example/avatar.jpg',
        'email_verified' => true,
    ]));
    $this->get(route('google.callback'));
    expect($withoutPicture->fresh()->picture)->toBe('https://google.example/avatar.jpg');
});

it('reuses the same Nusszopf account on a second Google login without re-matching by e-mail', function () {
    config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);

    Socialite::fake('google', SocialiteUser::fake(['id' => 'g-6', 'email' => 'nussknacker@example.com', 'email_verified' => true]));
    $this->get(route('google.callback'));
    $first = User::where('google_id', 'g-6')->first();

    $this->withoutMiddleware(PreventRequestForgery::class)->post(route('logout'));

    Socialite::fake('google', SocialiteUser::fake(['id' => 'g-6', 'email' => 'nussknacker@example.com', 'email_verified' => true]));
    $this->get(route('google.callback'));

    expect(User::count())->toBe(1);
    $this->assertAuthenticatedAs($first);
});
