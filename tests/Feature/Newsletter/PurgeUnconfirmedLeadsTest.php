<?php

use App\Models\Lead;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Decision A-1's retention rule: unconfirmed leads go 14 days after their
 * latest request; confirmed ones are never purged.
 */
it('deletes only unconfirmed leads whose latest request is older than 14 days', function () {
    $stale = Lead::factory()->create(['requested_at' => now()->subDays(14)->subMinute()]);
    $fresh = Lead::factory()->create(['requested_at' => now()->subDays(13)]);
    $confirmed = Lead::factory()->create(['requested_at' => now()->subYear(), 'confirmed_at' => now()->subYear()]);

    $this->artisan('newsletter:purge-unconfirmed')
        ->expectsOutput('Deleted 1 unconfirmed newsletter subscription(s).')
        ->assertSuccessful();

    expect(Lead::find($stale->id))->toBeNull()
        ->and(Lead::find($fresh->id))->not->toBeNull()
        ->and(Lead::find($confirmed->id))->not->toBeNull();
});

it('is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'newsletter:purge-unconfirmed'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 3 * * *');
});
