<?php

use App\Models\Project;
use App\Models\User;

/**
 * `Project::scopeVisible()` is the single enforcement point required to
 * back every read path — direct show, listing, and the search-indexing
 * gate — per docs/rewrite/first-slice.md's acceptance criteria #8.
 */
it('includes public projects for a guest visitor', function () {
    $public = Project::factory()->public()->create();
    Project::factory()->private()->create();

    $visible = Project::visible()->pluck('id');

    expect($visible)->toContain($public->id)
        ->and($visible)->toHaveCount(1);
});

it('includes an owners own private projects when authenticated', function () {
    $owner = User::factory()->create();
    $ownPrivate = Project::factory()->private()->for($owner)->create();
    $othersPrivate = Project::factory()->private()->create();
    $public = Project::factory()->public()->create();

    $this->actingAs($owner);

    $visible = Project::visible()->pluck('id');

    expect($visible)->toContain($ownPrivate->id)
        ->and($visible)->toContain($public->id)
        ->and($visible)->not->toContain($othersPrivate->id);
});

it('never returns another users private project to an authenticated visitor', function () {
    $stranger = User::factory()->create();
    $private = Project::factory()->private()->create();

    $this->actingAs($stranger);

    expect(Project::visible()->pluck('id'))->not->toContain($private->id);
});
