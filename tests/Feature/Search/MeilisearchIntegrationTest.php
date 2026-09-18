<?php

use App\Models\Project;
use Illuminate\Support\Facades\Config;

/**
 * The fast Feature suite fakes Scout everywhere else; this file is the
 * deliberately small exception that exercises the real Meilisearch
 * integration end to end (docs/testing/README.md, "Search and mail
 * testing") — real index name, real HTTP calls, real query results.
 */
it('indexes a public project into real Meilisearch and finds it by search', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $project = Project::factory()->public()->create([
        'title' => 'Sehr eindeutiger Suchtitel Buntspecht',
    ]);

    $project->searchable();

    // Meilisearch indexes asynchronously — a document written via
    // ->searchable() may not be queryable for a few milliseconds.
    $found = collect();
    for ($attempt = 0; $attempt < 20 && ! $found->pluck('id')->contains($project->id); $attempt++) {
        usleep(100_000);
        $found = Project::search('Buntspecht')->get();
    }

    expect($found->pluck('id'))->toContain($project->id);

    $project->unsearchable();
})->group('meilisearch');

it('never lets a private project be findable in real Meilisearch', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $project = Project::factory()->private()->create([
        'title' => 'Ganz einzigartiger Privattitel Wiesenknopf',
    ]);

    // Scout's model observer respects shouldBeSearchable() automatically —
    // a direct ->searchable() call on a private project is a no-op.
    $project->searchable();

    $found = Project::search('Wiesenknopf')->get();

    expect($found->pluck('id'))->not->toContain($project->id);
})->group('meilisearch');
