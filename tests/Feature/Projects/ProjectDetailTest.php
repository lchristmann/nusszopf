<?php

use App\Models\Project;
use App\Models\User;

/**
 * docs/rewrite/first-slice.md acceptance criteria #3, #4, #6: the owner can
 * always view their own project regardless of visibility; a public project
 * is visible to anonymous visitors; a private project 404s for anyone else
 * — a hard 404, never a 403 that would reveal the project's existence
 * (docs/rewrite/open-questions.md, resolved).
 */
it('lets an anonymous visitor view a public project', function () {
    $project = Project::factory()->public()->create(['title' => 'Öffentliches Projekt']);

    $response = $this->get(route('projects.show', $project));

    $response->assertOk();
    $response->assertSee('Öffentliches Projekt');
});

it('lets the owner view their own private project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->private()->for($owner)->create(['title' => 'Mein privates Projekt']);

    $response = $this->actingAs($owner)->get(route('projects.show', $project));

    $response->assertOk();
    $response->assertSee('Mein privates Projekt');
});

it('404s a private project for an anonymous visitor', function () {
    $project = Project::factory()->private()->create();

    $response = $this->get(route('projects.show', $project));

    $response->assertNotFound();
});

it('404s a private project for a different authenticated user, not a 403', function () {
    $project = Project::factory()->private()->create();
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->get(route('projects.show', $project));

    $response->assertNotFound();
});
