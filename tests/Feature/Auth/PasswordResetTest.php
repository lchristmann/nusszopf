<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Mail\ChangePasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * "Passwort vergessen" / "Neues Passwort erstellen"
 * (docs/authentication/README.md §4). Enumeration-safety and the reset
 * mechanics are new to this slice; the request/set-new-password screens
 * themselves have no historical 1:1 route (docs/design/screen-specs.md).
 */
it('always toasts "E-Mail verschickt!" whether or not the address exists', function () {
    Mail::fake();
    User::factory()->create(['email' => 'nussknacker@example.com']);

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nussknacker@example.com')
        ->call('send')
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt!');

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('send')
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt!');

    Mail::assertQueued(ChangePasswordMail::class, 1);
});

it('rejects an invalid e-mail format on the forgot-password form', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', 'not-an-email')
        ->call('send')
        ->assertHasErrors(['email']);
});

it('sends the reset link mail to the account, carrying a usable reset URL', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'nussknacker@example.com']);

    Password::sendResetLink(['email' => $user->email]);

    Mail::assertQueued(ChangePasswordMail::class, function (ChangePasswordMail $mail) use ($user) {
        return $mail->recipientEmail === $user->email
            && str_contains($mail->url, '/password/reset/')
            && str_contains($mail->url, 'email='.urlencode($user->email));
    });

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
});

it('resets the password with the historical strength policy and invalidates the token', function () {
    $user = User::factory()->create(['email' => 'nussknacker@example.com', 'password' => 'OldStr0ng!Pass']);
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'short')
        ->call('save')
        ->assertHasErrors(['password']);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'NewStr0ng!Pass')
        ->call('save')
        ->assertRedirect(route('login'));

    expect(Hash::check('NewStr0ng!Pass', $user->fresh()->password))->toBeTrue();

    // The token was consumed; using it again fails.
    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'AnotherStr0ng!Pass')
        ->call('save')
        ->assertDispatched('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

    expect(Hash::check('NewStr0ng!Pass', $user->fresh()->password))->toBeTrue();
});

it('rejects a tampered or unknown reset token', function () {
    $user = User::factory()->create(['email' => 'nussknacker@example.com']);

    Livewire::test(ResetPassword::class, ['token' => Str::random(40)])
        ->set('email', $user->email)
        ->set('password', 'NewStr0ng!Pass')
        ->call('save')
        ->assertDispatched('toast', type: 'error', message: 'Sorry, da lief etwas schief.');
});

it('reads the token from the route and the e-mail from the query string', function () {
    $user = User::factory()->create(['email' => 'nussknacker@example.com']);
    $token = Password::createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertSeeLivewire(ResetPassword::class);
});

it('limits each IP to 10 reset requests per 15 minutes, with the throttle copy and no further mail (SEC-03)', function () {
    Mail::fake();
    $users = User::factory()->count(11)->create();

    foreach ($users->take(10) as $user) {
        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('send')->assertHasNoErrors();
    }

    Livewire::test(ForgotPassword::class)
        ->set('email', $users->last()->email)
        ->call('send')
        ->assertHasErrors(['email'])
        ->assertSee('Zu viele Versuche. Bitte warte kurz.')
        ->assertNotDispatched('toast', type: 'success', message: 'E-Mail verschickt!');

    Mail::assertQueued(ChangePasswordMail::class, 10);

    $this->travel(16)->minutes();

    Livewire::test(ForgotPassword::class)->set('email', $users->last()->email)->call('send')->assertHasNoErrors();
    Mail::assertQueued(ChangePasswordMail::class, 11);
});

it('does not spend the reset budget on an invalid address', function () {
    foreach (range(1, 12) as $ignored) {
        Livewire::test(ForgotPassword::class)->set('email', 'not-an-email')->call('send');
    }

    Livewire::test(ForgotPassword::class)->set('email', 'nobody@example.com')->call('send')->assertHasNoErrors();
});

it('ends every other signed-in session of the account once its password is reset (SEC-10)', function () {
    $user = User::factory()->create(['email' => 'nussknacker@example.com', 'password' => 'OldStr0ng!Pass']);

    // A session signed in elsewhere (a stolen or forgotten device): signed in through the session itself,
    // as a real login leaves it, not through the test helper that bypasses the session.
    $this->withSession([Auth::guard('web')->getName() => $user->id])->get(route('profile'))->assertOk();

    Livewire::test(ResetPassword::class, ['token' => Password::createToken($user)])
        ->set('email', $user->email)
        ->set('password', 'NewStr0ng!Pass')
        ->call('save');

    Auth::forgetGuards();

    $this->get(route('profile'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps a session signed in while the password is unchanged', function () {
    $user = User::factory()->create();

    $this->withSession([Auth::guard('web')->getName() => $user->id])->get(route('profile'))->assertOk();
    Auth::forgetGuards();

    $this->get(route('profile'))->assertOk();
    $this->assertAuthenticatedAs($user);
});
