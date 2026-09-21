<?php

namespace App\Jobs;

use App\Health\HealthChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Dispatched every minute by the scheduler; when a queue worker runs it, the health check
 * sees that jobs are being processed, not merely that a worker container exists. Never retried:
 * the next minute's job is the retry.
 */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put(HealthChecker::QUEUE_HEARTBEAT, now()->timestamp, now()->addDay());
    }
}
