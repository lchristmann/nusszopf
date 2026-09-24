<?php

use App\Mail\ChangePasswordMail;
use App\Mail\NewsletterSubscribeMail;
use App\Mail\NewsletterUnsubscribeMail;
use App\Mail\VerifyEmailMail;
use App\Models\Lead;
use App\Models\User;
use App\Support\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * P-4, SEC-01 (docs/release/parity/P-04-security.md): links in mails were built from the Host header of the
 * request that triggered them. A "forgot password" request sent with `Host: evil.example` mailed the account
 * owner a genuine reset token under the attacker's host. Every absolute URL is now rooted at APP_URL.
 */
function forgeHost(string $host): void
{
    $request = Request::create("http://{$host}/password/forgot");
    app()->instance('request', $request);
    URL::setRequest($request);
}

function appUrl(): string
{
    return rtrim((string) config('app.url'), '/');
}

it('roots the password-reset link at APP_URL whatever Host the request carried', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'victim@example.com']);
    forgeHost('evil.example');

    Password::sendResetLink(['email' => $user->email]);

    Mail::assertQueued(ChangePasswordMail::class, fn (ChangePasswordMail $mail) => str_starts_with($mail->url, appUrl().'/password/reset/')
        && ! str_contains($mail->url, 'evil.example'));
});

it('roots the verification link at APP_URL whatever Host the request carried', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create();
    forgeHost('evil.example');

    $user->sendEmailVerificationNotification();

    Mail::assertQueued(VerifyEmailMail::class, fn (VerifyEmailMail $mail) => str_starts_with($mail->url, appUrl().'/email/verify/')
        && ! str_contains($mail->url, 'evil.example'));
});

it('roots the newsletter confirmation and unsubscribe links at APP_URL whatever Host the request carried', function () {
    Mail::fake();
    forgeHost('evil.example');

    Newsletter::subscribe('reader@example.com', 'Leser', Lead::SOURCE_FORM);
    Newsletter::requestUnsubscribe('reader@example.com');

    Mail::assertQueued(NewsletterSubscribeMail::class, fn (NewsletterSubscribeMail $mail) => str_starts_with($mail->url, appUrl().'/newsletter/subscribe/'));
    Mail::assertQueued(NewsletterUnsubscribeMail::class, fn (NewsletterUnsubscribeMail $mail) => str_starts_with($mail->url, appUrl().'/newsletter/unsubscribe/'));
});

it('roots signed links, such as the unblock link, at APP_URL', function () {
    forgeHost('evil.example');

    $url = URL::temporarySignedRoute('login.unblock', now()->addDay(), ['ip' => '203.0.113.5', 'user' => 'x']);

    expect($url)->toStartWith(appUrl().'/auth/unblock');
});

it('renders asset and Livewire URLs from APP_URL on a page requested under a foreign Host', function () {
    $html = $this->get('http://evil.example/login')->assertOk()->getContent();

    expect($html)->not->toContain('evil.example')
        ->and($html)->toContain('data-update-uri="'.appUrl().'/');
});
