<?php

namespace App\Mail;

use Illuminate\Mail\SendQueuedMailable;

/**
 * Every queued mail is a job that carries the models it was built from (`SerializesModels`) and loads them again
 * when the worker picks it up. A mail for an account that was deleted in between (a welcome or verification mail,
 * the account deleted before the worker sent it) cannot be built any more, and there is nobody left to send it to.
 * Laravel would fail such a job at once, without a retry, and leave it in `failed_jobs` for good, where it made an
 * operator read a failure that needs no action (P-12, finding P12-03). The job is dropped instead.
 *
 * Only a vanished model is dropped. A mail that fails for any other reason (the mail server down) still goes
 * through the retries and ends in `failed_jobs`. Laravel takes this flag from the job, not from the mailable, hence
 * this job class; `AppServiceProvider` binds it in place of `SendQueuedMailable`.
 */
class SendQueuedMailUnlessModelGone extends SendQueuedMailable
{
    public bool $deleteWhenMissingModels = true;
}
