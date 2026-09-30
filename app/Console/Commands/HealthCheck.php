<?php

namespace App\Console\Commands;

use App\Health\HealthChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The operator's view of an installation: the version that runs and each dependency's state,
 * with a non-zero exit when something is wrong (docs/handbuch/betrieb.md, "Gesundheit prüfen").
 */
#[Signature('nusszopf:health {--only= : Report only this check (database, redis, search, scheduler, queue, failed_jobs); used by the container health checks}')]
#[Description('Show the running version and check the database, Redis, search, scheduler, queue worker and failed jobs')]
class HealthCheck extends Command
{
    public function handle(HealthChecker $health): int
    {
        $checks = $health->run();

        if ($only = $this->option('only')) {
            $checks = array_intersect_key($checks, [$only => true]);

            if ($checks === []) {
                $this->error("No such check: {$only}");

                return self::FAILURE;
            }
        }

        $this->line('Nusszopf '.config('nusszopf.version'));
        $this->table(['Check', 'Status', 'Detail'], collect($checks)->map(fn (array $check, string $name) => [
            $name, $check['ok'] ? 'ok' : 'FAILED', $check['detail'],
        ])->values()->all());

        return $health->healthy($checks) ? self::SUCCESS : self::FAILURE;
    }
}
