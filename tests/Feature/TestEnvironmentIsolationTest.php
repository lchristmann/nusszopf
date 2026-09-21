<?php

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Regression protection for the test infrastructure: inside the Compose
 * containers the development `.env` is the process environment and once
 * silently beat phpunit.xml (the suite re-migrated the development database
 * and flooded its queue worker). See tests/bootstrap.php and docs/testing/README.md.
 */
it('runs against the testing database, never the development one', function () {
    expect(DB::connection()->getDatabaseName())->toBe('nusszopf_testing');
});

it('uses the sync queue and no search engine by default, so the development queue worker sees nothing', function () {
    expect(config('queue.default'))->toBe('sync')
        ->and(config('scout.driver'))->toBeNull()
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array');
});

it('keeps its search documents in an index of its own', function () {
    expect(config('scout.prefix'))->toBe('testing_')
        ->and(Project::searchIndexName())->toBe('testing_items');
});
