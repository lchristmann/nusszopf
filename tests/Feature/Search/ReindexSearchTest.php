<?php

use App\Models\Project;
use App\Models\ProjectRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

/**
 * The recovery path (docs/deployment/operations.md): the index is derived from the
 * database, so `search:reindex` from an empty — or wrong — index must end up where
 * live syncing would have.
 */
beforeEach(fn () => applyIndexSettings());

function seedProjects(string $word): void
{
    $plain = Project::factory()->public()->create(['title' => "Ohne Gesuche {$word}"]);
    $withRequests = Project::factory()->public()->create(['title' => "Mit Gesuchen {$word}"]);
    ProjectRequest::factory()->for($withRequests)->category('rooms')->create(['title' => 'Raum', 'description' => 'Mit Strom']);
    ProjectRequest::factory()->for($withRequests)->category('others')->create(['title' => 'Sonstiges']);
    $private = Project::factory()->private()->create(['title' => "Privat {$word}"]);
    ProjectRequest::factory()->for($private)->create();
}

/** @return list<string> the ids that ought to be in the index */
function expectedDocumentIds(): array
{
    return Project::query()->where('visibility', 'public')->get()
        ->flatMap(fn (Project $project) => $project->requests()->exists() ? $project->requests()->pluck('id') : [$project->id])
        ->sort()->values()->all();
}

function awaitDocumentCount(int $count): bool
{
    return awaitIndex(fn () => count(indexSnapshot()) === $count);
}

it('rebuilds an empty index to exactly what live syncing produced', function () {
    // Live synced, against a clean index.
    Project::removeAllFromSearch();
    expect(awaitDocumentCount(0))->toBeTrue();
    seedProjects(w('Wiederherstellwort'));
    $expected = expectedDocumentIds();
    expect(awaitDocumentCount(count($expected)))->toBeTrue();
    $live = indexSnapshot();
    expect(array_keys($live))->toBe($expected);

    // Lost: the index is empty (a wiped Meilisearch volume).
    Project::removeAllFromSearch();
    expect(awaitDocumentCount(0))->toBeTrue();

    expect(Artisan::call('search:reindex'))->toBe(0);

    expect(awaitDocumentCount(count($expected)))->toBeTrue()
        ->and(indexSnapshot())->toBe($live);
})->group('meilisearch');

/**
 * BUG-047: hits the ranking rules rank equal — always the requests of one project, which share its `updated_at` —
 * came in Meilisearch's internal order, the order the documents were written in, so a rebuild reordered them.
 */
it('answers in the same order after a rebuild: equally ranked hits by id, whatever order they were written in', function () {
    Project::removeAllFromSearch();
    expect(awaitDocumentCount(0))->toBeTrue();
    $word = w('Reihenwort');
    $project = Project::factory()->public()->create(['title' => "Reihenfolge {$word}"]);
    $requests = collect(range(1, 4))->map(fn (int $i) => ProjectRequest::factory()->for($project)->category('rooms')->create(['title' => "Gesuch {$i}"]));
    $byId = $requests->pluck('id')->sort()->values()->all();

    // Written newest first, one task each: Meilisearch's internal order is now the reverse of the ids.
    $requests->each->unsearchable();
    expect(awaitDocumentCount(0))->toBeTrue();
    $requests->sortByDesc('id')->each->searchable();
    expect(awaitDocumentCount(4))->toBeTrue();

    $order = fn () => indexHits($word)->pluck('id')->all();
    expect($order())->toBe($byId);

    expect(Artisan::call('search:reindex'))->toBe(0);
    expect(awaitDocumentCount(4))->toBeTrue()
        ->and($order())->toBe($byId);
})->group('meilisearch');

it('is idempotent: running it again changes nothing', function () {
    Project::removeAllFromSearch();
    seedProjects(w('Idempotenzwort'));

    Artisan::call('search:reindex');
    expect(awaitDocumentCount(count(expectedDocumentIds())))->toBeTrue();
    $first = indexSnapshot();

    Artisan::call('search:reindex');
    expect(awaitDocumentCount(count($first)))->toBeTrue()
        ->and(indexSnapshot())->toBe($first);
})->group('meilisearch');

it('removes documents that no longer belong to anything', function () {
    Project::removeAllFromSearch();
    $project = Project::factory()->public()->create(['title' => 'Bleibt '.w('Ballastwort')]);
    $gone = Project::factory()->public()->create(['title' => 'Verschwunden '.w('Ballastwort')]);
    $private = Project::factory()->public()->create(['title' => 'Wird privat '.w('Ballastwort')]);
    expect(awaitDocumentCount(3))->toBeTrue();

    // The rows change behind the index's back, as a missed sync would leave it.
    DB::table('projects')->where('id', $gone->id)->delete();
    DB::table('projects')->where('id', $private->id)->update(['visibility' => 'private']);

    Artisan::call('search:reindex');

    expect(awaitDocumentCount(1))->toBeTrue()
        ->and(array_keys(indexSnapshot()))->toBe([$project->id]);
})->group('meilisearch');

it('applies the index settings even to an index that does not exist yet', function () {
    $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
    $client->deleteIndex(Project::searchIndexName());
    expect(awaitIndex(function () use ($client) {
        try {
            $client->getIndex(Project::searchIndexName());

            return false;
        } catch (ApiException) {
            return true;
        }
    }))->toBeTrue();

    expect(Artisan::call('search:reindex'))->toBe(0);

    // The index is created by a task of its own, so it may not be there yet when the command returns.
    expect(awaitIndex(function () use ($client) {
        try {
            return in_array('req_type', $client->index(Project::searchIndexName())->getFilterableAttributes(), true);
        } catch (ApiException) {
            return false;
        }
    }))->toBeTrue();
})->group('meilisearch');

it('fails, and says so, when Meilisearch does not take the index settings', function () {
    // scout:sync-index-settings reports the rejection and still exits successfully; the import after it works.
    config(['scout.meilisearch.index-settings.'.Project::class.'.rankingRules' => ['nicht-gueltig'], 'search.settings_wait_attempts' => 2]);

    $this->artisan('search:reindex')->expectsOutputToContain('The index settings were not applied')->assertFailed();
})->group('meilisearch');

it('fails visibly, and says so, when the engine cannot be reached', function () {
    config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
    app()->forgetInstance(EngineManager::class);
    app()->forgetInstance(Client::class);

    $this->artisan('search:reindex')->expectsOutputToContain('the search index is incomplete')->assertFailed();
})->group('meilisearch');

/**
 * P-12 (docs/release/parity/P-12-queue-scheduler.md): with the queue in use the queue worker writes the documents, so
 * the queue is part of a rebuild.
 */
it('leaves the working index alone when the queue cannot take the import (P12-04)', function () {
    Project::removeAllFromSearch();
    seedProjects(w('Warteschlangenwort'));
    $expected = expectedDocumentIds();
    expect(awaitDocumentCount(count($expected)))->toBeTrue();
    $before = indexSnapshot();

    // Redis is down: before the fix the documents were dropped first and the import failed after.
    config(['scout.queue' => true, 'queue.default' => 'redis']);
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

    $this->artisan('search:reindex')
        ->expectsOutputToContain('The queue cannot be reached (Connection refused); nothing was changed')
        ->assertFailed();

    expect(indexSnapshot())->toBe($before);
})->group('meilisearch');

it('says so when no queue worker writes the documents (P12-05)', function () {
    config(['scout.queue' => true, 'queue.default' => 'database', 'search.queue_wait_attempts' => 1]);
    seedProjects(w('Arbeiterwort'));
    DB::table('jobs')->delete();

    $this->artisan('search:reindex')
        ->expectsOutputToContain('were still waiting after 0 s. Search shows nothing until they have run: check that the queue worker is running (docker compose ps queue-worker)')
        ->assertSuccessful();

    expect(DB::table('jobs')->count())->toBeGreaterThan(0);
})->group('meilisearch');

it('reports success once the queue has written every document', function () {
    config(['scout.queue' => true, 'queue.default' => 'sync']);
    seedProjects(w('Fertigwort'));

    $this->artisan('search:reindex')
        ->expectsOutputToContain('the queue worker has written every document')
        ->assertSuccessful();

    expect(awaitDocumentCount(count(expectedDocumentIds())))->toBeTrue();
})->group('meilisearch');
