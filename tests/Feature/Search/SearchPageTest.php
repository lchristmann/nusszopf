<?php

use App\Livewire\Search\Search;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Services\Search\ProjectHit;
use App\Services\Search\ProjectSearch;
use App\Services\Search\RequestHit;
use App\Services\Search\SearchResults;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * docs/rewrite/first-slice.md acceptance criterion #7 and the search-completion
 * slice: the page, its skeleton, the submit, the filter, "Mehr laden".
 *
 * The engine is replaced by a fake here; the real one is exercised in
 * SearchEngineTest and the browser suite.
 */
function fakeSearch(?Closure $handler = null): void
{
    $fake = Mockery::mock(ProjectSearch::class);
    $fake->shouldReceive('search')->andReturnUsing($handler ?? fn () => new SearchResults([], false));
    app()->instance(ProjectSearch::class, $fake);
}

function hitFor(Project $project): ProjectHit
{
    return new ProjectHit($project, e($project->title), e($project->goal), '', []);
}

it('renders the search page for an anonymous visitor', function () {
    $this->get(route('search'))->assertOk()->assertSee('Ideen und Projekte aus dem Nusswerk');
});

it('opens on the skeleton and queries nothing until the page has loaded', function () {
    fakeSearch(fn () => throw new RuntimeException('must not be asked before load'));

    Livewire::test(Search::class)
        ->assertSeeHtml('data-test="skeleton_hits"')
        ->assertDontSeeHtml('data-test="no-hits"');
});

it('shows the hits once loaded, replacing the skeleton', function () {
    $project = Project::factory()->public()->create(['title' => 'Gemeinschaftsgarten']);
    fakeSearch(fn () => new SearchResults([hitFor($project)], false));

    Livewire::test(Search::class)
        ->call('load')
        ->assertDontSeeHtml('data-test="skeleton_hits"')
        ->assertSeeHtml('data-test="route_hitcard"')
        ->assertSee('Gemeinschaftsgarten')
        ->assertSeeHtml('href="'.route('projects.show', $project).'"');
});

it('shows the no-hits state with the way to start a project', function () {
    fakeSearch();

    Livewire::test(Search::class)
        ->call('load')
        ->assertSeeHtml('data-test="no-hits"')
        ->assertSee('Verzopft, wir konnten leider nichts zu deiner Suche finden!')
        ->assertSee('Projekt starten')
        ->assertSeeHtml('href="'.route('projects.create').'"');
});

it('queries with the term, the applied filter and the page, and applies both on submit', function () {
    $asked = [];
    fakeSearch(function (string $query, array $categories, int $pages) use (&$asked) {
        $asked[] = [$query, $categories, $pages];

        return new SearchResults([], false);
    });

    Livewire::test(Search::class)
        ->call('load')
        ->set('query', 'Garten')
        ->call('search', ['rooms', 'none', 'unknown'])
        ->assertSet('filter', ['rooms', 'none']);

    expect(end($asked))->toBe(['Garten', ['rooms', 'none'], 1]);
});

it('starts over at the first page on a new search', function () {
    fakeSearch();

    Livewire::test(Search::class)
        ->call('load')
        ->call('loadMore')
        ->assertSet('pages', 2)
        ->call('search', [])
        ->assertSet('pages', 1);
});

it('is deep-linkable by term and filter', function () {
    fakeSearch();

    Livewire::withQueryParams(['q' => 'Wiese', 'f' => ['materials']])
        ->test(Search::class)
        ->assertSet('query', 'Wiese')
        ->assertSet('filter', ['materials']);
});

it('limits the term to 30 characters like the input does', function () {
    $asked = null;
    fakeSearch(function (string $query) use (&$asked) {
        $asked = $query;

        return new SearchResults([], false);
    });

    Livewire::test(Search::class)->call('load')->set('query', str_repeat('a', 45))->call('search', []);

    expect($asked)->toBe(str_repeat('a', 30));
});

it('offers "Mehr laden" only while there is more, and loads the next page on click', function () {
    $project = Project::factory()->public()->create();
    $pagesAsked = [];
    fakeSearch(function (string $query, array $categories, int $pages) use (&$pagesAsked, $project) {
        $pagesAsked[] = $pages;

        return new SearchResults([hitFor($project)], $pages < 3);
    });

    Livewire::test(Search::class)
        ->call('load')
        ->assertSeeHtml('data-test="btn_load-more"')
        ->call('loadMore')
        ->assertSeeHtml('data-test="btn_load-more"')
        ->call('loadMore')
        ->assertSet('pages', 3)
        ->assertDontSeeHtml('data-test="btn_load-more"');

    expect($pagesAsked)->toContain(1, 2, 3);
});

it('keeps the hits and shows an error toast when loading more fails', function () {
    $project = Project::factory()->public()->create(['title' => 'Bleibt stehen']);
    fakeSearch(fn (string $query, array $categories, int $pages) => $pages === 1
        ? new SearchResults([hitFor($project)], true)
        : new SearchResults([], false, failed: true));

    Livewire::test(Search::class)
        ->call('load')
        ->call('loadMore')
        ->assertSet('pages', 1)
        ->assertDispatched('toast', type: 'error', message: 'Sorry! Das hat gerade nicht geklappt.')
        ->assertSee('Bleibt stehen')
        ->assertSeeHtml('data-test="btn_load-more"');
});

it('renders the filter popover with the six historical options and the scroll-to-top button', function () {
    fakeSearch();

    $test = Livewire::test(Search::class)->call('load')
        ->assertSee('Gesuche filtern')
        ->assertSeeHtml('aria-label="Nach oben scrollen"');

    foreach (['companions' => 'Mitstreiter:innen', 'rooms' => 'Räume', 'materials' => 'Materialien', 'financials' => 'Finanzielles', 'others' => 'Sonstiges', 'none' => 'Keine Gesuche'] as $key => $label) {
        $test->assertSeeHtml('data-test="checkbox_'.$key.'_filter-popover"')->assertSee($label);
    }
});

it('nests the matching requests of a hit in the card, in their category color', function () {
    $project = Project::factory()->public()->create();
    $request = ProjectRequest::factory()->for($project)->category('rooms')->create();
    $hit = new ProjectHit($project, 'T', 'G', '', [new RequestHit($request->id, 'rooms', 'Ein <em>Raum</em>', 'Mit <em>Strom</em>')]);
    fakeSearch(fn () => new SearchResults([$hit], false));

    Livewire::test(Search::class)
        ->call('load')
        ->assertSeeHtml('data-test="card_request-hit"')
        ->assertSeeHtml('bg-yellow-200')
        ->assertSeeHtml('Ein <em>Raum</em>')
        ->assertSeeHtml('Mit <em>Strom</em>');
});

it('shows a published project on the search results page and never a private one', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    // A per-run word: the search index outlives the database rollback.
    $word = 'Suchwort'.strtolower(Str::random(8));
    Project::factory()->public()->create(['title' => "Ganz einzigartiges Suchseiten-Projekt {$word}"]);
    Project::factory()->private()->create(['title' => "Verstecktes Projekt Zwiebelfisch {$word}"]);

    $found = false;
    for ($attempt = 0; $attempt < 40 && ! $found; $attempt++) {
        usleep(100_000);
        $test = Livewire::test(Search::class)->set('query', $word)->call('load');
        $found = str_contains($test->html(), 'Ganz einzigartiges Suchseiten-Projekt');
    }

    expect($found)->toBeTrue();
    $test->assertSee('Ganz einzigartiges Suchseiten-Projekt')->assertDontSee('Verstecktes Projekt Zwiebelfisch');
})->group('meilisearch');
