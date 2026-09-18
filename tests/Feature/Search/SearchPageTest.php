<?php

use App\Models\Project;
use Illuminate\Support\Facades\Config;

/**
 * docs/rewrite/first-slice.md acceptance criterion #7: a published project
 * appears in /search; a private project never does.
 */
it('renders the search page for an anonymous visitor', function () {
    $this->get(route('search'))->assertOk();
});

it('shows a published project on the search results page and never a private one', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $public = Project::factory()->public()->create(['title' => 'Ganz einzigartiges Suchseiten-Projekt']);
    Project::factory()->private()->create(['title' => 'Verstecktes Projekt Zwiebelfisch']);

    $response = null;
    for ($attempt = 0; $attempt < 20; $attempt++) {
        usleep(100_000);
        $response = $this->get(route('search'));
        if (str_contains($response->getContent(), 'Ganz einzigartiges Suchseiten-Projekt')) {
            break;
        }
    }

    $response->assertOk();
    $response->assertSee('Ganz einzigartiges Suchseiten-Projekt');
    $response->assertDontSee('Verstecktes Projekt Zwiebelfisch');
})->group('meilisearch');
