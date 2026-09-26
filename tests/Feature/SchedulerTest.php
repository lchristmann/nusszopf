<?php

use App\Models\Lead;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/**
 * P-12 (docs/release/parity/P-12-queue-scheduler.md): what the scheduler runs, and when. The scheduler container runs
 * `schedule:work`, which does nothing but ask this list every minute. Nothing here may be added, dropped or moved
 * without a decision: the historical product had no scheduled work at all (docs/rewrite/master-roadmap.md, O-2).
 */
function scheduled(): array
{
    return collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event) => [$event->description => $event])
        ->all();
}

it('runs exactly the two heartbeats and the newsletter purge', function () {
    $events = scheduled();

    expect(array_keys($events))->toBe(['scheduler-heartbeat', 'queue-heartbeat', 'newsletter-purge-unconfirmed'])
        ->and($events['scheduler-heartbeat']->expression)->toBe('* * * * *')
        ->and($events['queue-heartbeat']->expression)->toBe('* * * * *')
        ->and($events['newsletter-purge-unconfirmed']->expression)->toBe('30 3 * * *');
});

it('keeps time in UTC, the only time zone the application has', function () {
    expect(config('app.timezone'))->toBe('UTC');

    foreach (scheduled() as $event) {
        expect($event->timezone)->toBe('UTC');
    }
});

it('is due for the purge once a day at 03:30, and for the heartbeats every minute', function () {
    $due = function (string $at): array {
        Carbon::setTestNow(Carbon::parse($at, 'UTC'));

        return collect(scheduled())->filter(fn (Event $event) => $event->isDue(app()))->keys()->all();
    };

    expect($due('2026-09-27 03:30:00'))->toBe(['scheduler-heartbeat', 'queue-heartbeat', 'newsletter-purge-unconfirmed'])
        ->and($due('2026-09-27 03:30:59'))->toContain('newsletter-purge-unconfirmed')
        ->and($due('2026-09-27 03:31:00'))->toBe(['scheduler-heartbeat', 'queue-heartbeat'])
        ->and($due('2026-09-27 03:29:00'))->toBe(['scheduler-heartbeat', 'queue-heartbeat'])
        ->and($due('2026-09-28 03:30:00'))->toContain('newsletter-purge-unconfirmed')
        ->and($due('2026-09-27 15:30:00'))->not->toContain('newsletter-purge-unconfirmed');

    Carbon::setTestNow();
});

it('lets the next run purge what expired while the scheduler was down', function () {
    // The run of 03:30 was missed for a week (a stopped container, a host that was off): the purge has no window of
    // its own, it deletes by age, so the first run after the outage catches up with everything, and a second run
    // right after it (two scheduler containers, a restart within the minute) finds nothing left to do.
    $missed = Lead::factory()->count(3)->create(['requested_at' => now()->subDays(21)]);
    $fresh = Lead::factory()->create(['requested_at' => now()->subDays(2)]);

    $this->artisan('newsletter:purge-unconfirmed')->expectsOutput('Deleted 3 unconfirmed newsletter subscription(s).')->assertSuccessful();
    $this->artisan('newsletter:purge-unconfirmed')->expectsOutput('Deleted 0 unconfirmed newsletter subscription(s).')->assertSuccessful();

    expect(Lead::whereIn('id', $missed->pluck('id'))->count())->toBe(0)
        ->and(Lead::find($fresh->id))->not->toBeNull();
});
