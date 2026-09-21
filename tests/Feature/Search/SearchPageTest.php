<?php

use App\Models\Project;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

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

    // A per-run word: the search index outlives the database rollback, and an
    // unconfigured index (the tests' own, `testing_items`) has no recency ranking.
    $word = 'Suchwort'.strtolower(Str::random(8));
    $public = Project::factory()->public()->create(['title' => "Ganz einzigartiges Suchseiten-Projekt {$word}"]);
    Project::factory()->private()->create(['title' => "Verstecktes Projekt Zwiebelfisch {$word}"]);

    $response = null;
    for ($attempt = 0; $attempt < 20; $attempt++) {
        usleep(100_000);
        $response = $this->get(route('search', ['q' => $word]));
        if (str_contains($response->getContent(), 'Ganz einzigartiges Suchseiten-Projekt')) {
            break;
        }
    }

    $response->assertOk();
    $response->assertSee('Ganz einzigartiges Suchseiten-Projekt');
    $response->assertDontSee('Verstecktes Projekt Zwiebelfisch');
})->group('meilisearch');
