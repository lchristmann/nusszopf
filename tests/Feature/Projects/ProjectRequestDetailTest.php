<?php

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Requests on the project detail page (`pages/projects/[id].js`,
 * `RequestCard` variant `view`, `RequestDialog.js`) — docs/rewrite/third-slice.md,
 * and BUG-002: a request is never shown where its project is not.
 */
it('shows the historical empty state for a project without requests', function () {
    $project = Project::factory()->public()->create();

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Projektgesuche')
        ->assertSee('Alles zopfig! Derzeit gibt es keine Gesuche.')
        ->assertDontSee('data-test="card_request"', escape: false);
});

it('lists the requests newest first as cards in the category color, with their date', function () {
    $project = Project::factory()->public()->create();
    ProjectRequest::factory()->for($project)->category('rooms')->create(['title' => 'Älter', 'created_at' => Carbon::parse('2026-03-01')]);
    ProjectRequest::factory()->for($project)->category('companions')->create(['title' => 'Neuer', 'created_at' => Carbon::parse('2026-04-02')]);

    $response = $this->get(route('projects.show', $project))->assertOk();
    $html = $response->getContent();

    $response->assertSee('Erstellt am 2.4.2026')->assertSee('Erstellt am 1.3.2026')->assertDontSee('Alles zopfig! Derzeit gibt es keine Gesuche.');

    expect(strpos($html, 'Neuer'))->toBeLessThan(strpos($html, 'Älter'))
        ->and(substr_count($html, 'data-test="card_request"'))->toBe(2)
        ->and($html)->toContain('bg-red-200 border border-red-300')
        ->and($html)->toContain('bg-yellow-200 border border-yellow-400');
});

it('gives every request a dialog with its title, date, rich text, contact and close buttons', function () {
    $project = Project::factory()->public()->create(['contact' => 'owner@example.org']);
    ProjectRequest::factory()->for($project)->category('materials')->create([
        'title' => 'Holz gesucht',
        'created_at' => Carbon::parse('2026-05-06'),
        'description' => 'Fett und Link',
        'description_template' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Fett', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' und '],
            ['type' => 'text', 'text' => 'Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.org/holz']]]],
        ]]]],
    ]);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('data-test="request-dialog"', escape: false)
        ->assertSee('aria-label="Gesuche Informationen"', escape: false)
        ->assertSee('Holz gesucht')
        ->assertSee('Erstellt am 6.5.2026')
        ->assertSee('<span class="font-medium">Fett</span>', escape: false)
        ->assertSee('href="https://example.org/holz"', escape: false)
        ->assertSee('nz-link-stone-turquoise', escape: false)
        ->assertSee('bg-turquoise-200', escape: false)
        ->assertSee('bg-turquoise-300', escape: false)
        ->assertSee('Kontaktieren')
        ->assertSee('Schließen')
        ->assertSee('href="mailto:owner@example.org?subject=', escape: false);
});

it('escapes a request\'s title and text', function () {
    $project = Project::factory()->public()->create();
    ProjectRequest::factory()->for($project)->create(['title' => '<script>alert(1)</script>', 'description_template' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<img src=x onerror=alert(1)>']]]]]]);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertDontSee('<img src=x', escape: false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
});

// --- BUG-002: visibility inherits from the project ---------------------------------

it('404s a private project with requests for a guest and a stranger, leaking none of it', function () {
    $project = Project::factory()->private()->create();
    ProjectRequest::factory()->for($project)->create(['title' => 'Geheimes Gesuch']);

    $this->get(route('projects.show', $project))->assertNotFound()->assertDontSee('Geheimes Gesuch');
    $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertNotFound()->assertDontSee('Geheimes Gesuch');
});

it('shows a private project\'s requests to its owner', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->private()->create();
    ProjectRequest::factory()->for($project)->create(['title' => 'Mein Gesuch']);

    $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()->assertSee('Mein Gesuch');
});

it('does not show the requests of a project once it turns private', function () {
    $project = Project::factory()->public()->create();
    ProjectRequest::factory()->for($project)->create(['title' => 'Wieder versteckt']);

    $this->get(route('projects.show', $project))->assertOk()->assertSee('Wieder versteckt');

    $project->update(['visibility' => 'private']);

    $this->get(route('projects.show', $project))->assertNotFound()->assertDontSee('Wieder versteckt');
});

it('404s a project that does not exist without telling it apart from a private one', function () {
    $this->get(route('projects.show', '01a0c4da-2d7f-7033-a7bd-420315f21367'))->assertNotFound();
});

it('does not list another project\'s requests', function () {
    $project = Project::factory()->public()->create();
    ProjectRequest::factory()->create(['title' => 'Fremdes Gesuch']);

    $this->get(route('projects.show', $project))->assertOk()->assertDontSee('Fremdes Gesuch');
});
