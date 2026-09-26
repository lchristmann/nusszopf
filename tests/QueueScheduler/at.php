<?php

// Queue/scheduler drill (scripts/queue-scheduler-test.sh), step 7b: `schedule:run` as the scheduler container runs it
// every minute, with the clock set to the given time of day (UTC) on today's date. What the scheduler then starts, the
// purge command among it, is the production image's own. Prints what the scheduler ran and what the purge said.
//
//   docker compose exec -T php-fpm php /tmp/at.php 03:30:00

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Carbon::setTestNow(Carbon::parse(gmdate('Y-m-d').' '.($argv[1] ?? '03:30:00'), 'UTC'));

$due = collect($app->make(Schedule::class)->events())->filter(fn (Event $event) => $event->isDue($app))->map->description->implode(', ');
echo 'at ', Carbon::now()->format('H:i'), ' UTC the scheduler starts: ', $due, "\n";

$kernel->call('schedule:run');
echo $kernel->output();
