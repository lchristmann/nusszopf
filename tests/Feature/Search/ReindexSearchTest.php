<?php

use App\Models\Project;
use App\Models\ProjectRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;

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
    awaitIndex(fn () => collect($client->getIndexes()->getResults())->doesntContain(fn ($index) => $index->getUid() === Project::searchIndexName()));

    expect(Artisan::call('search:reindex'))->toBe(0);

    expect(awaitIndex(fn () => in_array('req_type', $client->index(Project::searchIndexName())->getFilterableAttributes(), true)))->toBeTrue();
})->group('meilisearch');

it('fails visibly, and says so, when the engine cannot be reached', function () {
    config(['scout.meilisearch.host' => 'http://127.0.0.1:1']);
    app()->forgetInstance(EngineManager::class);
    app()->forgetInstance(Client::class);

    $this->artisan('search:reindex')->expectsOutputToContain('the search index is incomplete')->assertFailed();
})->group('meilisearch');
