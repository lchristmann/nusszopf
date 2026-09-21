<?php

use App\Models\Project;
use App\Services\Search\ProjectSearch;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;

/*
 * Helpers of the tests that talk to the real Meilisearch (group `meilisearch`), loaded by tests/Pest.php.
 */

/**
 * The raw index hits for a query.
 *
 * @return Collection<int, array<string, mixed>>
 */
function indexHits(string $query): Collection
{
    return collect(Project::search($query)->raw()['hits'] ?? []);
}

function indexHas(string $query, string $id): bool
{
    return indexHits($query)->contains(fn (array $hit) => $hit['id'] === $id);
}

function awaitIndex(Closure $condition): bool
{
    for ($attempt = 0; $attempt < 40; $attempt++) {
        if ($condition()) {
            return true;
        }
        usleep(100_000);
    }

    return false;
}

/**
 * A search word made unique to this run: documents of earlier runs stay in the
 * real index (their rows are rolled back, the index is not).
 */
function w(string $word): string
{
    static $tag = null;
    $tag ??= strtolower(Str::random(8));

    return $word.$tag;
}

function realMeilisearch(): void
{
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);
}

/**
 * Applies the checked-in index settings (`config/scout.php`) to the test index and waits until they are in effect:
 * the filter needs `req_type` to be filterable, which a bare test index is not.
 */
function applyIndexSettings(): void
{
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);
    Artisan::call('scout:sync-index-settings');

    $ready = awaitIndex(function () {
        try {
            app(ProjectSearch::class)->fetch('', ['rooms'], 1);

            return true;
        } catch (Throwable) {
            return false;
        }
    });

    expect($ready)->toBeTrue();
}

/**
 * Every document of the test index, keyed by id — what reindexing must reproduce.
 *
 * @return array<string, array<string, mixed>>
 */
function indexSnapshot(): array
{
    $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
    $documents = $client->index(Project::searchIndexName())->getDocuments((new DocumentsQuery)->setLimit(10000))->getResults();

    return collect($documents)->keyBy('id')->map(fn (array $document) => Arr::except($document, ['updated_at']))->sortKeys()->all();
}
