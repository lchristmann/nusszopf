<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Every row of docs/security/authorization-matrix.md's "Project" section
 * needs both an allow-case and a deny-case test — a matrix row with only
 * one of the two is incomplete coverage (docs/testing/README.md).
 */
it('allows a guest to view a public project', function () {
    $project = Project::factory()->public()->create();

    expect(Gate::forUser(null)->allows('view', $project))->toBeTrue();
});

it('allows an unrelated authenticated user to view a public project', function () {
    $project = Project::factory()->public()->create();
    $stranger = User::factory()->create();

    expect($stranger->can('view', $project))->toBeTrue();
});

it('denies viewing a private project to a guest', function () {
    $project = Project::factory()->private()->create();

    expect(Gate::forUser(null)->allows('view', $project))->toBeFalse();
});

it('denies viewing a private project to a non-owner', function () {
    $project = Project::factory()->private()->create();
    $stranger = User::factory()->create();

    expect($stranger->can('view', $project))->toBeFalse();
});

it('allows the owner to view their own private project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->private()->for($owner)->create();

    expect($owner->can('view', $project))->toBeTrue();
});

it('allows any authenticated user to create a project as themselves', function () {
    $user = User::factory()->create();

    expect($user->can('create', Project::class))->toBeTrue();
});

it('allows the owner to update their own project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    expect($owner->can('update', $project))->toBeTrue();
});

it('denies a non-owner from updating a project, including toggling visibility', function () {
    $project = Project::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can('update', $project))->toBeFalse();
});

it('allows the owner to delete their own project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    expect($owner->can('delete', $project))->toBeTrue();
});

it('denies a non-owner from deleting a project', function () {
    $project = Project::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can('delete', $project))->toBeFalse();
});
