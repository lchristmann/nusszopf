<?php

use App\Health\HealthChecker;
use App\Models\Project;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

/**
 * P-11, finding P11-02 (docs/release/parity/P-11-search-recovery.md): after Meilisearch lost its data, the health
 * check said "search ok" because the engine answered, while the index was missing, or recreated by live indexing
 * without its settings.
 */
beforeEach(fn () => applyIndexSettings());
afterEach(fn () => applyIndexSettings());

function searchCheck(): array
{
    return (new HealthChecker)->run()['search'];
}

function meiliClient(): Client
{
    return new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
}

it('passes while the index exists with its configured settings', function () {
    expect(searchCheck())->toBe(['ok' => true, 'detail' => 'available, index configured']);
})->group('meilisearch');

it('fails, naming the recovery, when the index does not exist', function () {
    meiliClient()->deleteIndex(Project::searchIndexName());
    expect(awaitIndex(function () {
        try {
            meiliClient()->getIndex(Project::searchIndexName());

            return false;
        } catch (ApiException) {
            return true;
        }
    }))->toBeTrue();

    expect(searchCheck()['ok'])->toBeFalse()
        ->and(searchCheck()['detail'])->toContain('does not exist')->toContain('search:reindex');
    $this->getJson('/health')->assertStatus(503);
})->group('meilisearch');

it('fails when the index has lost its settings, as one recreated by live indexing has', function () {
    meiliClient()->index(Project::searchIndexName())->resetSettings();
    expect(awaitIndex(fn () => meiliClient()->index(Project::searchIndexName())->getFilterableAttributes() === []))->toBeTrue();

    expect(searchCheck()['ok'])->toBeFalse()
        ->and(searchCheck()['detail'])->toContain('filterableAttributes')->toContain('rankingRules')->toContain('search:reindex');
})->group('meilisearch');
