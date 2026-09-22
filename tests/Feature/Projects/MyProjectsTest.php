<?php

use App\Livewire\Projects\MyProjects;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

/**
 * `pages/user/projects.js` — the authenticated user's own project grid
 * (`EditProjectCard`'s per-card actions, `WelcomeCard`'s empty state,
 * `ProjectsSkeleton`'s loading state), deferred by the second slice to this
 * one (docs/rewrite/master-roadmap.md, slice 5, inventory 17).
 */
it('sends a guest to the login screen', function () {
    $this->get(route('projects.mine'))->assertRedirect(route('login'));
});

it('shows the skeleton before the follow-up load, and the grid after it', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['title' => 'Nachbarschaftsgarten']);

    Livewire::actingAs($owner)->test(MyProjects::class)
        ->assertSeeHtml('data-test="skeleton_projects"')
        ->assertDontSee('Nachbarschaftsgarten')
        ->call('load')
        ->assertDontSeeHtml('data-test="skeleton_projects"')
        ->assertSee('Nachbarschaftsgarten');
});

it('shows only the caller\'s own projects, any visibility, never another user\'s', function () {
    $owner = User::factory()->create();
    $public = Project::factory()->for($owner)->public()->create(['title' => 'Mein öffentliches Projekt']);
    $private = Project::factory()->for($owner)->private()->create(['title' => 'Mein privates Projekt']);
    $stranger = Project::factory()->public()->create(['title' => 'Fremdes Projekt']);

    Livewire::actingAs($owner)->test(MyProjects::class)->call('load')
        ->assertSee('Mein öffentliches Projekt')
        ->assertSee('Mein privates Projekt')
        ->assertDontSee('Fremdes Projekt');
});

it('shows the welcome card, not the grid, when the caller has no projects', function () {
    Livewire::actingAs(User::factory()->create())->test(MyProjects::class)->call('load')
        ->assertSeeHtml('data-test="welcome-card"')
        ->assertSee('Willkommen in Deinem Nusszopfbereich!')
        ->assertSee('Hier kannst Du Projekte mit Gesuchen erstellen und verwalten. Am besten legst Du gleich los und backst dir die Welt, wie sie dir gefällt!')
        ->assertSee('Viel Spaß im nussigsten Netzwerk aller Zeiten!')
        ->assertDontSeeHtml('data-test="route_edit-project_projects-page"');
});

it('shows a preview of each project\'s requests on its card', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $project->requests()->create(['title' => 'Werkzeug gesucht', 'category' => 'materials', 'description' => 'x', 'description_template' => ['type' => 'doc', 'content' => []]]);

    Livewire::actingAs($owner)->test(MyProjects::class)->call('load')->assertSee('Werkzeug gesucht');
});

// --- Visibility toggle -------------------------------------------------------------------

it('toggles a public project to private and back, with the shared update toast', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create();

    // `handleVisibility` calls the same `updateProject` service function the
    // settings view's own save does — the same toast, not a distinct one.
    Livewire::actingAs($owner)->test(MyProjects::class)
        ->call('toggleVisibility', $project->id)
        ->assertDispatched('toast', type: 'success', message: 'Projekt wurde aktualisiert.');
    expect($project->fresh()->visibility)->toBe('private');
});

it('throttles rapid visibility toggles to one per second, dropping (not queuing) the second call', function () {
    // A deliberate, documented deviation from lodash.throttle's actual default
    // ({ leading: true, trailing: true }, which would queue and re-fire the
    // second call ~1s later) — docs/rewrite/fifth-slice.md, decision 3.
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create();

    $component = Livewire::actingAs($owner)->test(MyProjects::class);
    $component->call('toggleVisibility', $project->id);
    $component->call('toggleVisibility', $project->id);

    // Only the first call within the window took effect.
    expect($project->fresh()->visibility)->toBe('private');
});

it('re-syncs search when the grid toggles visibility', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create();
    $queued = [];

    Project::saved(function (Project $saved) use (&$queued): void {
        $queued[] = $saved->id;
    });

    Livewire::actingAs($owner)->test(MyProjects::class)->call('toggleVisibility', $project->id);

    expect($queued)->toContain($project->id);
});

it('denies toggling another user\'s project', function () {
    $project = Project::factory()->public()->create();
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)->test(MyProjects::class)->call('toggleVisibility', $project->id)->assertNotFound();

    expect($project->fresh()->visibility)->toBe('public');
});

// --- Deletion (BUG-013 preserved: the browser's own confirm()) -----------------------------------

it('lets the owner delete a project from the grid', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    Livewire::actingAs($owner)->test(MyProjects::class)
        ->call('deleteProject', $project->id)
        ->assertDispatched('toast', type: 'success', message: 'Das Projekt wurde gelöscht.');

    expect(Project::find($project->id))->toBeNull();
});

it('denies deleting another user\'s project from the grid', function () {
    $project = Project::factory()->create();
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)->test(MyProjects::class)->call('deleteProject', $project->id)->assertNotFound();

    expect(Project::find($project->id))->not->toBeNull();
});
