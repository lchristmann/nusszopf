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
| (docs/rewrite/master-roadmap.md, phase O-2) — so nothing periodic is reproduced. The
| entries are the two heartbeats that let /health and `nusszopf:health` tell an operator
| whether the scheduler and the queue worker are alive, and the newsletter retention rule
| of decision A-1 (slice 9). Add a task here only together with the slice that needs it,
| and say why.
|
*/

Schedule::call(fn () => Cache::put(HealthChecker::SCHEDULER_HEARTBEAT, now()->timestamp, now()->addDay()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::job(new QueueHeartbeat)->name('queue-heartbeat')->everyMinute();

// Decision A-1: unconfirmed newsletter subscriptions are deleted 14 days after their latest request.
Schedule::command('newsletter:purge-unconfirmed')->name('newsletter-purge-unconfirmed')->dailyAt('03:30');

// The public demo's fictional data is rebuilt hourly, so nothing a visitor changes outlives the hour. Registered only
// in demo mode (docs/deployment/demo.md); an ordinary installation has no such task.
if (config('nusszopf.demo')) {
    Schedule::command('demo:reset')->name('demo-reset')->hourly();
}
