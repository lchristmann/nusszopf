<?php

use App\Livewire\Projects\ProjectForm;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

/**
 * docs/rewrite/first-slice.md acceptance criteria #2, #3, #5: a logged-in
 * user creates exactly one project through the single-form path (title,
 * goal, description, visibility, defaulting to private), can view their
 * own private project, and can toggle visibility to public through the
 * same update path used for editing content.
 */
it('creates a private-by-default project for the authenticated user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProjectForm::class)
        ->set('title', 'Nachbarschaftsgarten')
        ->set('goal', 'Eine grüne Fläche für alle schaffen.')
        ->set('description', 'Eine ausführliche Beschreibung des Projekts.')
        ->call('save')
        ->assertRedirect(route('projects.mine'));

    $project = Project::first();

    expect($project->user_id)->toBe($user->id)
        ->and($project->title)->toBe('Nachbarschaftsgarten')
        ->and($project->visibility)->toBe('private');
});

it('cannot create a project as another user — user_id is always the caller', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProjectForm::class)
        ->set('title', 'Titel')
        ->set('goal', 'Ziel')
        ->set('description', 'Beschreibung')
        ->call('save');

    expect(Project::first()->user_id)->toBe($user->id)
        ->and(Project::first()->user_id)->not->toBe($other->id);
});

it('rejects an empty title, goal, or description', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProjectForm::class)
        ->set('title', '')
        ->set('goal', '')
        ->set('description', '')
        ->call('save')
        ->assertHasErrors(['title', 'goal', 'description']);
});

it('rejects a title over 40 characters and a goal over 150 characters', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProjectForm::class)
        ->set('title', str_repeat('a', 41))
        ->set('goal', str_repeat('a', 151))
        ->set('description', 'Beschreibung')
        ->call('save')
        ->assertHasErrors(['title', 'goal']);
});

it('lets the owner toggle visibility to public through the edit form', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->private()->for($owner)->create();

    Livewire::actingAs($owner)
        ->test(ProjectForm::class, ['project' => $project])
        ->set('title', $project->title)
        ->set('goal', $project->goal)
        ->set('description', $project->description)
        ->set('visibility', 'public')
        ->call('save')
        ->assertRedirect(route('projects.mine'));

    expect($project->fresh()->visibility)->toBe('public');
});

it('404s a non-owner editing another users project, not a 403', function () {
    // BUG-021 (docs/rewrite/bugs.md): must never distinguish "not yours"
    // from "doesn't exist" via a different status code than ProjectDetail
    // already uses for the same principle.
    $project = Project::factory()->create();
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test(ProjectForm::class, ['project' => $project])
        ->assertNotFound();
});

it('denies a guest from creating a project at all', function () {
    $response = $this->get(route('projects.create'));

    $response->assertRedirect(route('login'));
});
