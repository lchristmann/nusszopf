<?php

use App\Mail\NewsletterSubscribeMail;
use App\Mail\NewsletterUnsubscribeMail;

/**
 * `sendgrid/newsletter/{subscribe,unsubscribe}.mjml` (docs/email/README.md
 * items 6–7), verbatim except BUG-006's "Bestätigte" → "Bestätige".
 */
it('renders the subscribe confirmation mail', function () {
    $mail = new NewsletterSubscribeMail('nuss@example.com', 'https://nz.test/newsletter/subscribe/abc.def');

    $mail->assertHasTo('nuss@example.com');
    $mail->assertHasSubject('Nussiger Newsletter – Anmeldebestätigung');
    $mail->assertSeeInHtml('Der News&shy;letter ist zum Grei&shy;fen nah!', false);
    $mail->assertSeeInHtml('Bestätige deine E-Mail-Adresse durch einen Klick auf den Button und schon bist Du zum Newsletter angemeldet!');
    $mail->assertDontSeeInHtml('Bestätigte');
    $mail->assertSeeInHtml('E-Mail-Adresse bestätigen');
    $mail->assertSeeInHtml('href="https://nz.test/newsletter/subscribe/abc.def"', false);
});

it('renders the unsubscribe confirmation mail', function () {
    $mail = new NewsletterUnsubscribeMail('nuss@example.com', 'https://nz.test/newsletter/unsubscribe/abc.def');

    $mail->assertHasTo('nuss@example.com');
    $mail->assertHasSubject('Nussiger Newsletter – Abmeldebestätigung');
    $mail->assertSeeInHtml('Der Nusszopf liebt dich sowieso!');
    $mail->assertSeeInHtml('Bestätige deine Abmeldung von dem Newsletter, indem Du auf den Button klickst. Wenn Du möchtest, kannst Du dich natürlich jederzeit wieder anmelden.');
    $mail->assertDontSeeInHtml('Bestätigte');
    $mail->assertSeeInHtml('Abmelden bestätigen');
    $mail->assertSeeInHtml('href="https://nz.test/newsletter/unsubscribe/abc.def"', false);
});
