<?php

use App\Health\HealthChecker;
use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| The historical product had no periodic work at all — its Hasura cron triggers were empty
| (docs/rewrite/master-roadmap.md, phase O-2) — so nothing periodic is reproduced. The only
| entries are the two heartbeats that let /health and `nusszopf:health` tell an operator
| whether the scheduler and the queue worker are alive. Add a task here only together with
| the slice that needs it, and say why.
|
*/

Schedule::call(fn () => Cache::put(HealthChecker::SCHEDULER_HEARTBEAT, now()->timestamp, now()->addDay()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::job(new QueueHeartbeat)->name('queue-heartbeat')->everyMinute();
