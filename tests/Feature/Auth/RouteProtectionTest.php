<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * BUG-003 fix (docs/rewrite/bugs.md, docs/rewrite/intentional-changes.md):
 * server-side `auth` middleware, not the historical client-side
 * flash-then-redirect — a guest must never receive any protected content,
 * not even momentarily.
 */
it('redirects a guest away from the project creation form without rendering it', function () {
    $response = $this->get(route('projects.create'));

    $response->assertRedirect(route('login'));
    $response->assertDontSee('Projekt erstellen');
});

it('redirects a guest away from the my-projects dashboard', function () {
    $response = $this->get(route('projects.mine'));

    $response->assertRedirect(route('login'));
});

it('redirects an authenticated user away from the login screen', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect();
});

it('logs the user out and invalidates the session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
