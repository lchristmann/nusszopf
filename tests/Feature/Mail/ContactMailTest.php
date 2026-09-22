<?php

use App\Mail\ContactMail;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * `sendgrid/contact.mjml` (docs/email/README.md item 5). Trigger/recipient/
 * subject/Reply-To end to end, and the rate limit, are covered in
 * `tests/Feature/Projects/ContactFormTest.php`; this file covers the
 * Mailable/rendered view in isolation, including BUG-010's escaping half.
 */
it('names only the project when opened without a request', function () {
    $mail = new ContactMail('owner@example.test', 'visitor@example.test', 'Gartenprojekt', null, 'Hallo!');

    $mail->assertSeeInHtml('Gartenprojekt');
    $mail->assertDontSeeInHtml('Gartenprojekt / ');
});

it('names the project and the request together when both are known', function () {
    $mail = new ContactMail('owner@example.test', 'visitor@example.test', 'Gartenprojekt', 'Werkzeug gesucht', 'Hallo!');

    $mail->assertSeeInHtml('Gartenprojekt / Werkzeug gesucht');
});

it('escapes HTML in the visitor\'s message, the visitor\'s address and the project title', function () {
    $mail = new ContactMail(
        'owner@example.test',
        '<script>alert(1)</script>@example.test',
        '<img src=x onerror=alert(1)>',
        null,
        '<script>alert(2)</script>',
    );

    $html = $mail->render();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->not->toContain('<script>alert(2)</script>')
        ->toContain('&lt;script&gt;alert(2)&lt;/script&gt;');
});

it('sets the historical subject and the visitor\'s address as Reply-To', function () {
    $mail = new ContactMail('owner@example.test', 'visitor@example.test', 'Gartenprojekt', null, 'Hallo!');
    $envelope = $mail->envelope();

    expect($envelope->subject)->toBe('Nusszopf – Kontaktanfrage')
        ->and(collect($envelope->replyTo)->map->address->all())->toBe(['visitor@example.test'])
        ->and(collect($envelope->to)->map->address->all())->toBe(['owner@example.test']);
});

/**
 * BUG-009's fix (docs/rewrite/intentional-changes.md, "Queue-backed sync jobs"), extended to mail:
 * the historical Node handler had no queue of its own and relied only on SendGrid's own delivery
 * retries. `ContactMail implements ShouldQueue` — a send that cannot reach the mail server is not
 * silently dropped, it lands in `failed_jobs`, mirroring `tests/Feature/Search/SearchSyncFailureTest.php`.
 */
it('leaves a failed contact send in failed_jobs instead of losing it', function () {
    config([
        'queue.default' => 'database',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
    ]);
    app()->forgetInstance(MailManager::class);

    Mail::send(new ContactMail('owner@example.test', 'visitor@example.test', 'Gartenprojekt', null, 'Hallo!'));

    expect(DB::table('jobs')->count())->toBeGreaterThan(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--sleep' => 0]);

    // A Mailable that implements ShouldQueue is queued as itself, not wrapped in
    // Illuminate\Mail\SendQueuedMailable (that wrapper is only used for a Mailable queued via the
    // fluent `Mail::to(...)->queue()` without implementing the interface itself).
    $failed = DB::table('failed_jobs')->pluck('payload')->map(fn (string $payload) => json_decode($payload, true)['displayName']);
    expect($failed)->not->toBeEmpty()
        ->and($failed->unique()->all())->toBe([ContactMail::class])
        ->and(DB::table('jobs')->count())->toBe(0);
});
