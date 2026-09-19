<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * The fast Feature suite fakes Scout everywhere else; this file is the
 * deliberately small exception that exercises the real Meilisearch
 * integration end to end (docs/testing/README.md, "Search and mail
 * testing") — real index name, real HTTP calls, real query results.
 *
 * Indexing happens through the *automatic* create/update/delete model
 * observer Scout's Searchable trait registers, not a manual ->searchable()
 * call — shouldBeSearchable() only gates that automatic sync (Scout's
 * documented behavior; an explicit ->searchable() call is an imperative
 * override and intentionally bypasses the gate), and the automatic path is
 * the only one the application itself ever exercises.
 */
it('indexes a public project into real Meilisearch and finds it by search', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $project = Project::factory()->public()->create([
        'title' => 'Sehr eindeutiger Suchtitel Buntspecht',
    ]);

    // Meilisearch indexes asynchronously — a document written via the
    // model-created observer may not be queryable for a few milliseconds.
    $found = collect();
    for ($attempt = 0; $attempt < 20 && ! $found->pluck('id')->contains($project->id); $attempt++) {
        usleep(100_000);
        $found = Project::search('Buntspecht')->get();
    }

    expect($found->pluck('id'))->toContain($project->id);
})->group('meilisearch');

it('never lets a private project be findable in real Meilisearch', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $project = Project::factory()->private()->create([
        'title' => 'Ganz einzigartiger Privattitel Wiesenknopf',
    ]);

    // Give the (non-existent) sync a moment, then assert it never arrived.
    usleep(300_000);

    $found = Project::search('Wiesenknopf')->get();

    expect($found->pluck('id'))->not->toContain($project->id);
})->group('meilisearch');

it('removes a project from the index the moment it is switched from public to private', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $project = Project::factory()->public()->create([
        'title' => 'Sichtbarkeitswechsel Testprojekt Saftladen',
    ]);

    $found = collect();
    for ($attempt = 0; $attempt < 20 && ! $found->pluck('id')->contains($project->id); $attempt++) {
        usleep(100_000);
        $found = Project::search('Saftladen')->get();
    }
    expect($found->pluck('id'))->toContain($project->id);

    $project->update(['visibility' => 'private']);

    for ($attempt = 0; $attempt < 20; $attempt++) {
        usleep(100_000);
        $found = Project::search('Saftladen')->get();
        if (! $found->pluck('id')->contains($project->id)) {
            break;
        }
    }

    expect($found->pluck('id'))->not->toContain($project->id);
})->group('meilisearch');

it('excludes a private project from search results even if it were force-indexed, via the query-time defense-in-depth scope', function () {
    // The indexing-time gate (shouldBeSearchable()) is the primary
    // enforcement, but docs/search/README.md explicitly calls for a
    // second, query-time backstop (Search::render()'s ->query(fn ($q) =>
    // $q->visible())) precisely because Scout/Livewire call sites are
    // easier to invoke without the gate than the historical bespoke
    // webhook was. Simulate a leaked/stale index document by calling
    // ->searchable() directly, which deliberately bypasses
    // shouldBeSearchable() (Scout's documented behavior for explicit
    // calls) — the query-time scope must still keep it out of results.
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $owner = User::factory()->create();
    $private = Project::factory()->private()->for($owner)->create([
        'title' => 'Heimlich indiziertes Testprojekt Loeffelstiel',
    ]);

    $private->searchable();

    $found = collect();
    for ($attempt = 0; $attempt < 20; $attempt++) {
        usleep(100_000);
        $found = Project::search('Loeffelstiel')->get();
        if ($found->pluck('id')->contains($private->id)) {
            break;
        }
    }
    // Sanity check: the document really did land in the raw index (proving
    // this test would catch a regression, not just an indexing delay).
    expect($found->pluck('id'))->toContain($private->id);

    $visibleResults = Project::search('Loeffelstiel')->query(fn ($query) => $query->visible())->get();

    expect($visibleResults->pluck('id'))->not->toContain($private->id);

    $private->unsearchable();
})->group('meilisearch');
