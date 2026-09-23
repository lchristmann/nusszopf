<?php

namespace App\Console\Commands;

use App\Support\Newsletter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Decision A-1's retention rule: a newsletter subscription nobody confirmed
 * is deleted 14 days after its latest request — twice the lifetime of the
 * confirmation link, so a lead with a still-valid link is never purged.
 * Scheduled daily (routes/console.php); safe to run by hand at any time.
 */
#[Signature('newsletter:purge-unconfirmed')]
#[Description('Delete newsletter subscriptions that were not confirmed within 14 days')]
class PurgeUnconfirmedLeads extends Command
{
    public function handle(): int
    {
        $deleted = Newsletter::purgeUnconfirmed();

        $this->info("Deleted {$deleted} unconfirmed newsletter subscription(s).");

        return self::SUCCESS;
    }
}
