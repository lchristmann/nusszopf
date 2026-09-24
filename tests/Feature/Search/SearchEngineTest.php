<?php

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Services\Search\ProjectSearch;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;

/**
 * The search page's query side against the real Meilisearch (group `meilisearch`,
 * docs/testing/README.md): category filter semantics, paging, highlighting, and the
 * checked-in index settings.
 */
beforeEach(fn () => applyIndexSettings());

/**
 * @param  list<string>  $categories
 * @return array<string, list<string>> project title => titles of the requests nested in its card
 */
function searched(string $word, array $categories = [], int $pages = 1): array
{
    $results = app(ProjectSearch::class)->search($word, $categories, $pages);

    return collect($results->hits)->mapWithKeys(fn ($hit) => [$hit->project->title => collect($hit->requests)->pluck('titleHtml')->all()])->all();
}

it('applies the checked-in index settings: req_type filterable, recency last, no 1000-hit cap', function () {
    $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
    $settings = $client->index(Project::searchIndexName())->getSettings();

    expect($settings['filterableAttributes'])->toContain('req_type')
        ->and(last($settings['rankingRules']))->toBe('updated_at:desc')
        ->and($settings['pagination']['maxTotalHits'])->toBe(config('scout.meilisearch.index-settings')[Project::class]['pagination']['maxTotalHits']);
});

it('filters by the checked request categories, per combination', function () {
    $word = w('Filterwort');
    $none = Project::factory()->public()->create(['title' => "Ohne Gesuche {$word}"]);
    $companions = Project::factory()->public()->create(['title' => "Mit Menschen {$word}"]);
    $mixed = Project::factory()->public()->create(['title' => "Gemischt {$word}"]);
    $materials = Project::factory()->public()->create(['title' => "Mit Material {$word}"]);
    ProjectRequest::factory()->for($companions)->category('companions')->create(['title' => 'Menschen']);
    ProjectRequest::factory()->for($mixed)->category('rooms')->create(['title' => 'Raum']);
    ProjectRequest::factory()->for($mixed)->category('others')->create(['title' => 'Sonstiges']);
    ProjectRequest::factory()->for($materials)->category('materials')->create(['title' => 'Holz']);

    $everything = [
        "Ohne Gesuche {$word}" => [],
        "Mit Menschen {$word}" => ['Menschen'],
        "Gemischt {$word}" => ['Raum', 'Sonstiges'],
        "Mit Material {$word}" => ['Holz'],
    ];
    $sorted = fn (array $groups) => collect($groups)->map(fn ($titles) => collect($titles)->sort()->values()->all())->sortKeys()->all();

    // Until every project document has been replaced by its requests' documents.
    expect(awaitIndex(fn () => $sorted(searched($word)) === $sorted($everything)))->toBeTrue();

    // Nothing checked, or everything checked, filters nothing.
    expect($sorted(searched($word)))->toBe($sorted($everything))
        ->and($sorted(searched($word, ProjectSearch::CATEGORIES)))->toBe($sorted($everything))
        // "Keine Gesuche": the projects without requests.
        ->and(searched($word, ['none']))->toBe(["Ohne Gesuche {$word}" => []])
        // One category: the projects with such a request, and only that request in the card.
        ->and(searched($word, ['rooms']))->toBe(["Gemischt {$word}" => ['Raum']])
        // Several: an OR, each card showing the requests that matched.
        ->and($sorted(searched($word, ['rooms', 'others'])))->toBe($sorted(["Gemischt {$word}" => ['Raum', 'Sonstiges']]))
        ->and($sorted(searched($word, ['companions', 'none'])))->toBe($sorted(["Ohne Gesuche {$word}" => [], "Mit Menschen {$word}" => ['Menschen']]))
        ->and(searched($word, ['financials']))->toBe([]);
})->group('meilisearch');

it('finds a project through its request\'s text and nests only the matching request', function () {
    $word = w('Nestwort');
    $project = Project::factory()->public()->create(['title' => 'Nestprojekt']);
    ProjectRequest::factory()->for($project)->category('rooms')->create(['title' => "Ein Raum {$word}"]);
    ProjectRequest::factory()->for($project)->category('others')->create(['title' => 'Etwas anderes']);

    // Only the request that matches is a hit; the project's other request is not among the documents found.
    expect(awaitIndex(fn () => searched($word) === ['Nestprojekt' => ["Ein Raum <em>{$word}</em>"]]))->toBeTrue();
})->group('meilisearch');

it('highlights the matches and escapes everything else', function () {
    $word = w('Leuchtwort');
    Project::factory()->public()->create(['title' => "Garten <b>fett</b> {$word}", 'goal' => "Ziel {$word} & mehr"]);

    $hit = null;
    expect(awaitIndex(function () use ($word, &$hit) {
        $hit = app(ProjectSearch::class)->search($word)->hits[0] ?? null;

        return $hit !== null;
    }))->toBeTrue();

    expect($hit->titleHtml)->toBe("Garten &lt;b&gt;fett&lt;/b&gt; <em>{$word}</em>")
        ->and($hit->goalHtml)->toBe("Ziel <em>{$word}</em> &amp; mehr");
})->group('meilisearch');

it('pages through the results by the historical page size, and stops exactly at the end', function () {
    $word = w('Seitenwort');
    $total = ProjectSearch::pageSize() * 2 + 30;
    ProjectRequest::withoutSyncingToSearch(fn () => Project::withoutSyncingToSearch(
        fn () => Project::factory()->count($total)->public()->create(['title' => "Seiten {$word}"])
    ));
    Project::where('title', "Seiten {$word}")->with('user')->get()->searchable();

    $page = fn (int $pages) => app(ProjectSearch::class)->search($word, [], $pages);
    expect(awaitIndex(fn () => count($page(3)->hits) === $total))->toBeTrue();

    expect(count($page(1)->hits))->toBe(50)
        ->and($page(1)->hasMore)->toBeTrue()
        ->and(count($page(2)->hits))->toBe(100)
        ->and($page(2)->hasMore)->toBeTrue()
        ->and(count($page(3)->hits))->toBe($total)
        ->and($page(3)->hasMore)->toBeFalse()
        // No project twice, however far one pages.
        ->and(collect($page(3)->hits)->pluck('project.id')->unique())->toHaveCount($total);
})->group('meilisearch');

it('shows a stale document of a private project nowhere', function () {
    $word = w('Altlastwort');
    $public = Project::factory()->public()->create(['title' => "Oeffentlich {$word}"]);
    $private = Project::factory()->private()->create(['title' => "Privat {$word}"]);

    // Written straight to the engine, as a missed sync would leave it.
    app(EngineManager::class)->engine()->update(collect([$private]));
    expect(awaitIndex(fn () => indexHas($word, $private->id)))->toBeTrue();

    expect(awaitIndex(fn () => array_keys(searched($word)) === ["Oeffentlich {$word}"]))->toBeTrue();
})->group('meilisearch');
