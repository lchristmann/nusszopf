<?php

/**
 * P-13 (docs/release/parity/P-13-email-delivery.md): sends one mail of every type the application sends, through
 * the application's own entry points (the ones the screens call), to the address in P13_RECIPIENT. Run inside the
 * php-fpm container by scripts/mail-delivery-test.sh; the mails are queued and the queue worker delivers them.
 *
 * The contact mail carries hostile, user-controlled text so that the received message shows whether it is escaped.
 */

use App\Livewire\Auth\LoginRegister;
use App\Mail\ContactMail;
use App\Mail\WelcomeMail;
use App\Models\Lead;
use App\Models\User;
use App\Support\Newsletter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$recipient = getenv('P13_RECIPIENT');
if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "P13_RECIPIENT must be set to the address that receives the test mails.\n");
    exit(2);
}

// The account the mails are about. Its name is hostile too, in case a template ever prints it.
$user = User::query()->firstOrCreate(
    ['email' => $recipient],
    ['name' => '<b>P13 "Nuss" & Zopf</b>', 'password' => bin2hex(random_bytes(16))],
);

// 1. Welcome (registration).
Mail::send(new WelcomeMail($user));
// 2. E-mail verification (registration, Profile, e-mail change).
$user->sendEmailVerificationNotification();
// 3. Password reset, through the broker the "forgot password" form uses.
Password::sendResetLink(['email' => $recipient]);
// 4. Account lockout notice: the private method the login form calls after repeated failures.
$method = new ReflectionMethod(LoginRegister::class, 'sendBlockedAccountNotice');
$method->invoke(new LoginRegister, $user, '203.0.113.7');
// 5. Contact mail to a project owner (the recipient), with hostile visitor-controlled content.
Mail::send(new ContactMail(
    ownerEmail: $recipient,
    visitorEmail: 'visitor@example.org',
    projectTitle: '<script>alert("p13-title")</script> & "Quoted" \'Single\'',
    requestTitle: '<img src=x onerror=alert("p13-request")>',
    message: "Zeile 1: <b>fett?</b> & \"Anführungszeichen\"\nZeile 2: </p><a href=\"https://evil.example/p13\">Klick</a>\nZeile 3: Umlaute äöüß 🌰 {{ 7*7 }} {!! 8*8 !!}",
));
// 6. Newsletter double opt-in, and 7. unsubscribe confirmation (needs the lead the first one creates).
Newsletter::subscribe($recipient, 'P13 Test', Lead::SOURCE_FORM);
Newsletter::requestUnsubscribe($recipient);

echo "queued: welcome, verify, password-reset, blocked-account, contact, newsletter-subscribe, newsletter-unsubscribe\n";
