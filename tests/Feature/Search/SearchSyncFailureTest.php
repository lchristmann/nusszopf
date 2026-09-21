<?php

use App\Models\Project;
use App\Models\ProjectRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Meilisearch\Client;

/**
 * BUG-009 (docs/rewrite/intentional-changes.md, "Queue-backed sync jobs"): a sync that
 * cannot reach the search engine is not silently dropped — it ends up in `failed_jobs`,
 * where it is visible and can be retried. Covered here for the requests' documents too.
 */
it('leaves a failed request sync in failed_jobs instead of losing it', function () {
    config([
        'queue.default' => 'database',
        'scout.driver' => 'meilisearch',
        'scout.queue' => true,
        'scout.meilisearch.host' => 'http://127.0.0.1:1',
    ]);
    app()->forgetInstance(EngineManager::class);
    app()->forgetInstance(Client::class);

    $project = Project::factory()->public()->create();
    ProjectRequest::factory()->for($project)->create();

    expect(DB::table('jobs')->count())->toBeGreaterThan(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--sleep' => 0]);

    $failed = DB::table('failed_jobs')->pluck('payload')->map(fn (string $payload) => json_decode($payload, true)['displayName']);
    expect($failed)->not->toBeEmpty()
        ->and($failed->unique()->diff([MakeSearchable::class, RemoveFromSearch::class]))->toBeEmpty()
        ->and(DB::table('jobs')->count())->toBe(0);
});
