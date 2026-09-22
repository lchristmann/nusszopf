<?php

use App\Models\Project;
use App\Models\ProjectAnalytics;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * BUG-001's fix (docs/rewrite/bugs.md, docs/rewrite/intentional-changes.md):
 * the historical `views` counter was insertable/updatable by any caller, for
 * any project, via an open Hasura permission — a client-writable field with
 * no ownership check at all. Nusszopf 2 counts server-side only, from
 * `ProjectDetail::mount()`, with the same "once per browser, never the
 * owner" dedupe the historical `localStorage` mechanism used (register B-4),
 * a cookie instead since there is no client script issuing the increment.
 */
it('creates the analytics row and starts it at 1 on a guest\'s first visit', function () {
    $project = Project::factory()->public()->create();

    expect($project->analytics)->toBeNull();

    $this->get(route('projects.show', $project));

    expect($project->fresh()->analytics->views)->toBe(1);
});

it('increments an existing counter on a further visitor\'s first visit', function () {
    $project = Project::factory()->public()->create();
    $project->analytics()->create(['views' => 41]);

    $this->get(route('projects.show', $project));

    expect($project->fresh()->analytics->views)->toBe(42);
});

it('does not count a second visit from the same browser (cookie dedupe)', function () {
    $project = Project::factory()->public()->create();

    $this->get(route('projects.show', $project));
    $this->withCookie('nz_viewed_projects', json_encode([$project->id]))->get(route('projects.show', $project));

    expect($project->fresh()->analytics->views)->toBe(1);
});

it('counts a visit to a second project from a browser that already viewed a different one', function () {
    $seen = Project::factory()->public()->create();
    $project = Project::factory()->public()->create();

    $this->withCookie('nz_viewed_projects', json_encode([$seen->id]))->get(route('projects.show', $project));

    expect($project->fresh()->analytics->views)->toBe(1);
});

it('never counts the owner\'s own views', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create();

    $this->actingAs($owner)->get(route('projects.show', $project));
    $this->actingAs($owner)->get(route('projects.show', $project));

    expect($project->fresh()->analytics)->toBeNull();
});

it('counts an authenticated stranger\'s visit, not just a guest\'s', function () {
    $project = Project::factory()->public()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('projects.show', $project));

    expect($project->fresh()->analytics->views)->toBe(1);
});

it('exposes no route that lets a caller write the counter directly', function () {
    // The historical exploit: any caller could set any project's `views` to
    // any value via a direct GraphQL mutation. Nusszopf 2 has no equivalent
    // HTTP surface at all — no `ProjectAnalyticsPolicy`, no route, no
    // wire:model — `views` is only ever touched from `ProjectDetail::recordView()`.
    $touchesAnalytics = collect(Route::getRoutes())->filter(
        fn ($route) => str_contains(strtolower($route->getActionName()), 'analytics')
    );

    expect($touchesAnalytics)->toBeEmpty();
});

it('deletes the analytics row when its project is deleted', function () {
    $project = Project::factory()->public()->create();
    $project->analytics()->create(['views' => 5]);

    $project->delete();

    expect(ProjectAnalytics::query()->where('project_id', $project->id)->exists())->toBeFalse();
});
