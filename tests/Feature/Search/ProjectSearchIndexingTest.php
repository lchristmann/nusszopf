<?php

use App\Models\Project;
use Laravel\Scout\Searchable;

/**
 * Reproduces the historical indexing-time visibility gate
 * (docs/search/README.md, "Authorization filtering at search time") as the
 * Nusszopf 2 Scout integration's core invariant: a private project must
 * never reach the search index, and a public→private transition must
 * remove it, not merely stop future syncs.
 */
it('marks a public project as searchable', function () {
    $project = Project::factory()->public()->make();

    expect($project->shouldBeSearchable())->toBeTrue();
});

it('marks a private project as not searchable', function () {
    $project = Project::factory()->private()->make();

    expect($project->shouldBeSearchable())->toBeFalse();
});

it('uses the Searchable trait so Scout observes create/update/delete automatically', function () {
    expect(class_uses_recursive(Project::class))->toHaveKey(Searchable::class);
});
