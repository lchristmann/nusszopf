<?php

/**
 * P-13 (docs/release/parity/P-13-email-delivery.md): builds every mail type like the application does, hands each to
 * the in-memory `array` mailer instead of a relay, and prints what would go out: the envelope headers, the parts,
 * every link and the result of the escaping checks. Sends nothing. Run inside the php-fpm container by
 * scripts/mail-delivery-test.sh --inspect, or by hand:
 *
 *   docker compose exec -T php-fpm php < scripts/mail-delivery-inspect.php
 */

use App\Mail\BlockedAccountMail;
use App\Mail\ChangePasswordMail;
use App\Mail\ContactMail;
use App\Mail\NewsletterSubscribeMail;
use App\Mail\NewsletterUnsubscribeMail;
use App\Mail\VerifyEmailMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$recipient = 'recipient@example.test';
$user = new User(['name' => '<b>P13 "Nuss" & Zopf</b>', 'email' => $recipient]);
$user->id = 1;

$hostile = [
    'title' => '<script>alert("p13-title")</script> & "Quoted"',
    'request' => '<img src=x onerror=alert("p13-request")>',
    'message' => "<b>fett?</b> & \"q\"\n</p><a href=\"https://evil.example/p13\">Klick</a> {{ 7*7 }} {!! 8*8 !!}",
];

$mails = [
    'welcome' => new WelcomeMail($user),
    'verify-email' => new VerifyEmailMail($user, URL::temporarySignedRoute('verification.verify', now()->addDays(7), ['id' => 1, 'hash' => sha1($recipient)])),
    'password-reset' => new ChangePasswordMail($recipient, route('password.reset', ['token' => 'TOKEN', 'email' => $recipient])),
    'blocked-account' => new BlockedAccountMail($user, '203.0.113.7', URL::temporarySignedRoute('login.unblock', now()->addDay(), ['ip' => '203.0.113.7', 'user' => 1])),
    'contact' => new ContactMail($recipient, 'visitor@example.org', $hostile['title'], $hostile['request'], $hostile['message']),
    'newsletter-subscribe' => new NewsletterSubscribeMail($recipient, route('newsletter.subscribe.confirm', 'TOKEN')),
    'newsletter-unsubscribe' => new NewsletterUnsubscribeMail($recipient, route('newsletter.unsubscribe.confirm', 'TOKEN')),
];

$mailer = Mail::mailer('array');
$out = [];
foreach ($mails as $type => $mailable) {
    $mailer->sendNow($mailable);
    $sent = collect($mailer->getSymfonyTransport()->messages())->last();
    $email = $sent->getOriginalMessage();
    $html = (string) $email->getHtmlBody();
    $text = $email->getTextBody();
    preg_match_all('/<a\s[^>]*href="([^"]*)"/i', $html, $links);
    $out[$type] = [
        'from' => array_map(fn ($a) => $a->toString(), $email->getFrom()),
        'to' => array_map(fn ($a) => $a->getAddress(), $email->getTo()),
        'reply_to' => array_map(fn ($a) => $a->getAddress(), $email->getReplyTo()),
        'subject' => $email->getSubject(),
        'html_bytes' => strlen($html),
        'text_part' => $text === null ? 'none' : strlen($text).' bytes',
        'links' => array_values(array_unique($links[1])),
        'raw_script_tag' => str_contains($html, '<script'),
        'raw_img_onerror' => str_contains($html, '<img src=x'),
        'raw_evil_anchor' => str_contains($html, 'href="https://evil.example'),
        'raw_bold_tag_from_input' => str_contains($html, '<b>fett?</b>') || str_contains($html, '<b>P13'),
        // Shown as typed, not evaluated: the template syntax in the message arrives as text.
        'template_syntax_shown_literally' => str_contains($html, '{{ 7*7 }}') && str_contains($html, '{!! 8*8 !!}'),
        'hostile_text_escaped' => str_contains($html, '&lt;script&gt;') && str_contains($html, '&lt;img src=x'),
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
