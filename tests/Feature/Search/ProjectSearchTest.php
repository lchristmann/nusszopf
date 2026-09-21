<?php

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Services\Search\ProjectSearch;
use App\Support\SearchHighlight;

/**
 * The grouping and checking of a Meilisearch response (`present`) and the filter
 * expression, without a search engine: the response is written out by hand.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function projectDocument(Project $project, array $extra = []): array
{
    return [
        'id' => $project->id,
        'group_id' => $project->id,
        'req_type' => 'none',
        'title' => $project->title,
        'goal' => $project->goal,
        '_formatted' => ['title' => $project->title, 'goal' => $project->goal],
        ...$extra,
    ];
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function requestDocument(ProjectRequest $request, array $extra = []): array
{
    return [
        'id' => $request->id,
        'group_id' => $request->project_id,
        'req_type' => $request->category,
        'req_title' => $request->title,
        '_formatted' => ['req_title' => $request->title, 'req_description' => $request->description],
        ...$extra,
    ];
}

// --- The category filter (`_mapFilterQuery`) ------------------------------------------

it('filters by nothing when no option or every option is checked', function () {
    expect(ProjectSearch::filterExpression([]))->toBeNull()
        ->and(ProjectSearch::filterExpression(ProjectSearch::CATEGORIES))->toBeNull();
});

it('filters by an OR over the checked options', function (array $checked, string $expected) {
    expect(ProjectSearch::filterExpression($checked))->toBe($expected);
})->with([
    'one category' => [['rooms'], 'req_type = rooms'],
    'no requests only' => [['none'], 'req_type = none'],
    'two categories, in the filter\'s order' => [['others', 'companions'], 'req_type = companions OR req_type = others'],
    'a category and none' => [['none', 'materials'], 'req_type = materials OR req_type = none'],
    'all but one' => [['companions', 'rooms', 'materials', 'financials', 'others'], 'req_type = companions OR req_type = rooms OR req_type = materials OR req_type = financials OR req_type = others'],
]);

it('ignores values that are not filter options, so nothing reaches the engine\'s filter syntax', function () {
    expect(ProjectSearch::filterExpression(['rooms" OR title = "x']))->toBeNull()
        ->and(ProjectSearch::filterExpression(['rooms', 'nope']))->toBe('req_type = rooms');
});

it('has the historical page size', function () {
    // `const OFFSET = 50` in search.service.js
    expect(ProjectSearch::PAGE_SIZE)->toBe(50);
});

// --- Grouping ---------------------------------------------------------------------------

it('groups the documents into their projects, in the order each first appears, with the matching requests nested', function () {
    $first = Project::factory()->public()->create(['title' => 'Erstes']);
    $second = Project::factory()->public()->create(['title' => 'Zweites']);
    $third = Project::factory()->public()->create(['title' => 'Drittes']);
    $a = ProjectRequest::factory()->for($first)->category('rooms')->create(['title' => 'Raum A']);
    $b = ProjectRequest::factory()->for($second)->category('others')->create(['title' => 'Sonst B']);
    $c = ProjectRequest::factory()->for($first)->category('materials')->create(['title' => 'Stoff C']);

    $results = app(ProjectSearch::class)->present(['hits' => [
        requestDocument($a),
        requestDocument($b),
        projectDocument($third),
        requestDocument($c),
    ], 'estimatedTotalHits' => 4]);

    expect(collect($results->hits)->map(fn ($hit) => $hit->project->title)->all())->toBe(['Erstes', 'Zweites', 'Drittes'])
        ->and(collect($results->hits[0]->requests)->pluck('titleHtml')->all())->toBe(['Raum A', 'Stoff C'])
        ->and(collect($results->hits[0]->requests)->pluck('category')->all())->toBe(['rooms', 'materials'])
        ->and($results->hits[1]->requests)->toHaveCount(1)
        ->and($results->hits[2]->requests)->toBe([]);
});

it('lists a project once however many of its documents match', function () {
    $project = Project::factory()->public()->create();
    $requests = ProjectRequest::factory()->count(3)->for($project)->create();

    $results = app(ProjectSearch::class)->present(['hits' => $requests->map(fn ($r) => requestDocument($r))->all(), 'estimatedTotalHits' => 3]);

    expect($results->hits)->toHaveCount(1)->and($results->hits[0]->requests)->toHaveCount(3);
});

// --- Only what may be seen is shown -------------------------------------------------------

it('drops the documents of a project that is private or gone (stale index documents)', function () {
    $public = Project::factory()->public()->create(['title' => 'Sichtbar']);
    $private = Project::factory()->private()->create(['title' => 'Nicht mehr sichtbar']);
    $deleted = Project::factory()->public()->create(['title' => 'Geloescht']);
    $deletedDocument = projectDocument($deleted);
    $privateRequest = ProjectRequest::factory()->for($private)->create();
    $deleted->delete();

    $results = app(ProjectSearch::class)->present(['hits' => [
        projectDocument($private),
        requestDocument($privateRequest),
        $deletedDocument,
        projectDocument($public),
    ], 'estimatedTotalHits' => 4]);

    expect(collect($results->hits)->map(fn ($hit) => $hit->project->title)->all())->toBe(['Sichtbar']);
});

it('does not show a private project to its own owner through a stale document', function () {
    $owner = User::factory()->create();
    $private = Project::factory()->for($owner)->private()->create();

    $this->actingAs($owner);
    $results = app(ProjectSearch::class)->present(['hits' => [projectDocument($private)], 'estimatedTotalHits' => 1]);

    // The index holds only what anybody may see, so search shows only that — for the owner too.
    expect($results->hits)->toBe([]);
});

it('drops a request document whose request is gone, or that now belongs to another project', function () {
    $project = Project::factory()->public()->create();
    $other = Project::factory()->public()->create();
    $kept = ProjectRequest::factory()->for($project)->create(['title' => 'Bleibt']);
    $removed = ProjectRequest::factory()->for($project)->create(['title' => 'Weg']);
    $removedDocument = requestDocument($removed);
    $removed->delete();
    $foreign = ProjectRequest::factory()->for($other)->create(['title' => 'Fremd']);

    $results = app(ProjectSearch::class)->present(['hits' => [
        requestDocument($kept),
        $removedDocument,
        requestDocument($foreign, ['group_id' => $project->id]),
    ], 'estimatedTotalHits' => 3]);

    expect(collect($results->hits[0]->requests)->pluck('titleHtml')->all())->toBe(['Bleibt']);
});

// --- "Mehr laden" boundary ---------------------------------------------------------------

it('offers more exactly while the index holds more documents than were fetched', function (int $total, int $fetched, bool $more) {
    $project = Project::factory()->public()->create();
    $documents = array_fill(0, $fetched, projectDocument($project));

    expect(app(ProjectSearch::class)->present(['hits' => $documents, 'estimatedTotalHits' => $total])->hasMore)->toBe($more);
})->with([
    'nothing found' => [0, 0, false],
    'fewer than a page' => [12, 12, false],
    'exactly a page, nothing more' => [50, 50, false],
    'a page and one' => [51, 50, true],
    'two pages fetched of three' => [130, 100, true],
    'all fetched after the last click' => [130, 130, false],
]);

// --- Text --------------------------------------------------------------------------------

it('escapes the stored text and highlights only what the engine marked', function () {
    $project = Project::factory()->public()->create(['title' => '<img src=x onerror=alert(1)>']);
    $open = SearchHighlight::OPEN;
    $close = SearchHighlight::CLOSE;

    $hit = app(ProjectSearch::class)->present(['hits' => [
        projectDocument($project, ['_formatted' => ['title' => "<img src=x> {$open}Baum{$close}", 'goal' => 'Ziel']]),
    ], 'estimatedTotalHits' => 1])->hits[0];

    expect($hit->titleHtml)->toBe('&lt;img src=x&gt; <em>Baum</em>')
        ->and($hit->titleHtml)->not->toContain('<img');
});

it('summarises the best document\'s description, place, team, motto and author, without the empty ones, cut at 90 characters', function () {
    $project = Project::factory()->public()->create();

    $hit = app(ProjectSearch::class)->present(['hits' => [
        projectDocument($project, ['_formatted' => [
            'title' => 'T', 'goal' => 'G',
            'description' => 'Beschreibung', 'location_text' => '', 'team' => 'Team', 'motto' => null, 'author' => 'anna',
        ]]),
    ], 'estimatedTotalHits' => 1])->hits[0];

    expect($hit->infoHtml)->toBe('Beschreibung | Team | anna');

    $long = app(ProjectSearch::class)->present(['hits' => [
        projectDocument($project, ['_formatted' => ['description' => str_repeat('x', 200)]]),
    ], 'estimatedTotalHits' => 1])->hits[0];

    expect(mb_strlen($long->infoHtml))->toBe(90)->and($long->infoHtml)->toEndWith('...');
});

it('shows a request\'s description only when there is one, cut at 90 characters', function () {
    $project = Project::factory()->public()->create();
    $with = ProjectRequest::factory()->for($project)->create(['description' => str_repeat('y', 150)]);
    $without = ProjectRequest::factory()->for($project)->create();

    $hit = app(ProjectSearch::class)->present(['hits' => [
        requestDocument($with),
        requestDocument($without, ['_formatted' => ['req_title' => 'Ohne']]),
    ], 'estimatedTotalHits' => 2])->hits[0];

    expect(mb_strlen($hit->requests[0]->descriptionHtml))->toBe(90)
        ->and($hit->requests[1]->descriptionHtml)->toBe('');
});
