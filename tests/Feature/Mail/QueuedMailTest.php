<?php

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\AccountDeleter;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * P-12, finding P12-03 (docs/release/parity/P-12-queue-scheduler.md): a queued mail that carries an account which is
 * deleted before the worker sends it used to fail at once with ModelNotFoundException and stay in `failed_jobs`
 * for good, as noise an operator had to read. Nothing is left to send it to, so the job is dropped.
 */
function runQueuedJobsOnce(): void
{
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--sleep' => 0]);
}

beforeEach(function () {
    config(['queue.default' => 'database']);
});

it('drops a welcome mail whose account was deleted before the worker ran, instead of failing it', function () {
    $user = User::factory()->create();

    Mail::send(new WelcomeMail($user));
    expect(DB::table('jobs')->count())->toBe(1);

    AccountDeleter::delete($user);
    runQueuedJobsOnce();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('still sends the mail to an account that exists', function () {
    $user = User::factory()->create();

    Mail::send(new WelcomeMail($user));
    runQueuedJobsOnce();

    $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($sent)->toHaveCount(1)
        ->and($sent->first()->getOriginalMessage()->getTo()[0]->getAddress())->toBe($user->email);
});

it('still keeps a mail for an existing account in failed_jobs when the mail server cannot be reached', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    app()->forgetInstance(MailManager::class);

    Mail::send(new WelcomeMail(User::factory()->create()));
    runQueuedJobsOnce();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});
