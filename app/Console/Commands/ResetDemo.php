<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rebuilds the public demo account and its fictional sample data (docs/handbuch/demo.md). Refuses to run unless
 * demo mode is on, so it can never wipe or create an account on an ordinary installation by mistake.
 */
#[Signature('demo:reset')]
#[Description('Rebuild the shared demo account and its fictional sample projects (only when NUSSZOPF_DEMO is on)')]
class ResetDemo extends Command
{
    public function handle(): int
    {
        if (! Demo::enabled()) {
            $this->error('Demo mode is off (NUSSZOPF_DEMO=false); nothing was changed.');

            return self::FAILURE;
        }

        $user = Demo::reset();

        $this->info("Demo account rebuilt with {$user->projects()->count()} sample projects.");

        return self::SUCCESS;
    }
}
