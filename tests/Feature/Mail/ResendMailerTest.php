<?php

use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ResendTransport;

/**
 * P-13 (docs/release/parity/P-13-email-delivery.md, finding P13-01): `config/mail.php` and `config/services.php`
 * had always offered `MAIL_MAILER=resend`, but the package the transport needs was not installed, so an instance
 * configured that way could not send a single mail. Delivery through the real API is verified by
 * `scripts/mail-delivery-test.sh`; this keeps the option from breaking again without one.
 */
it('requires the Resend client as a production dependency', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    // `require`, not `require-dev`: the production images install with --no-dev.
    expect($composer['require'])->toHaveKey('resend/resend-php');
});

it('builds the resend mailer from RESEND_API_KEY', function () {
    config(['services.resend.key' => 're_test_not_a_real_key']);

    $transport = app(MailManager::class)->mailer('resend')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(ResendTransport::class);
});
