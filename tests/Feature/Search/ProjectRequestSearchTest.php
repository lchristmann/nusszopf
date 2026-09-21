<?php

use App\Livewire\Projects\ProjectEdit;
use App\Livewire\Search\Search;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Laravel\Scout\EngineManager;
use Livewire\Livewire;

/**
 * Requests in the search index (docs/search/README.md, "Third slice"): one
 * shared `items` index with a document per request — or, for a project without
 * requests, one for the project — carrying `group_id` and `req_type`, gated by
 * the project's visibility (search.function.js). The hits are the groups; the
 * search page's nesting is the search-completion slice's.
 */
it('indexes a request as the project\'s fields plus its own', function () {
    $owner = User::factory()->create(['name' => 'gartenfreund']);
    $project = Project::factory()->for($owner)->public()->withLocation('Leipzig')->create(['team' => 'Anna', 'motto' => 'Wächst.']);
    $request = ProjectRequest::factory()->for($project)->category('rooms')->create(['title' => 'Werkstatt', 'description' => 'Ein Raum mit Strom.']);

    $document = $request->fresh()->toSearchableArray();

    expect($document)->toMatchArray([
        ...Arr::except($project->fresh()->projectDocument(), ['updated_at']),
        'req_title' => 'Werkstatt',
        'req_description' => 'Ein Raum mit Strom.',
        'req_type' => 'rooms',
        'group_id' => $project->id,
    ])
        ->and($document['author'])->toBe('gartenfreund')
        ->and($document['location_text'])->toBe('Leipzig, Sachsen, Deutschland')
        ->and($document['updated_at'])->toBe($project->fresh()->updated_at->timestamp);
});

it('indexes a project without requests as a project document of type none', function () {
    $project = Project::factory()->public()->create();

    expect($project->toSearchableArray())->toMatchArray(['req_type' => 'none', 'group_id' => $project->id]);
});

it('shares one index between the two models', function () {
    expect(Project::SEARCH_INDEX)->toBe('items')
        ->and((new Project)->searchableAs())->toBe(config('scout.prefix').'items')
        ->and((new ProjectRequest)->searchableAs())->toBe((new Project)->searchableAs());
});

it('indexes a project only while it is public and has no requests, and its requests only while it is public', function () {
    $public = Project::factory()->public()->create();
    $private = Project::factory()->private()->create();

    expect($public->shouldBeSearchable())->toBeTrue()->and($private->shouldBeSearchable())->toBeFalse();

    $publicRequest = ProjectRequest::factory()->for($public)->create();
    $privateRequest = ProjectRequest::factory()->for($private)->create();

    expect($public->fresh()->shouldBeSearchable())->toBeFalse()
        ->and($publicRequest->fresh()->shouldBeSearchable())->toBeTrue()
        ->and($privateRequest->fresh()->shouldBeSearchable())->toBeFalse();

    $publicRequest->delete();

    expect($public->fresh()->shouldBeSearchable())->toBeTrue();
});

// --- Against the real Meilisearch ---------------------------------------------------

it('replaces the project\'s document with its requests\' documents, grouped by the project', function () {
    realMeilisearch();
    $project = Project::factory()->public()->create(['title' => 'Streuobstwiese '.w('Haubentaucher')]);
    expect(awaitIndex(fn () => indexHas(w('Haubentaucher'), $project->id)))->toBeTrue();

    $request = ProjectRequest::factory()->for($project)->category('materials')->create(['title' => 'Leitern', 'description' => 'Wir brauchen Baumschnittleitern '.w('Zwergtaucher').'.']);

    expect(awaitIndex(fn () => indexHas(w('Zwergtaucher'), $request->id)))->toBeTrue()
        ->and(awaitIndex(fn () => ! indexHas(w('Haubentaucher'), $project->id)))->toBeTrue();

    $hit = indexHits(w('Zwergtaucher'))->firstWhere('id', $request->id);
    // The request's document carries the project's fields too: the project's title finds the request.
    expect($hit)->toMatchArray(['group_id' => $project->id, 'req_type' => 'materials', 'req_title' => 'Leitern', 'title' => 'Streuobstwiese '.w('Haubentaucher')])
        ->and(awaitIndex(fn () => indexHas(w('Haubentaucher'), $request->id)))->toBeTrue();
})->group('meilisearch');

it('keeps a document per request and none for the project, and brings the project back with its last request gone', function () {
    realMeilisearch();
    $project = Project::factory()->public()->create(['title' => 'Bachpatenschaft '.w('Eisvogel')]);
    $first = ProjectRequest::factory()->for($project)->create(['title' => 'Kescher Erstes']);
    $second = ProjectRequest::factory()->for($project)->create(['title' => 'Stiefel Zweites']);

    expect(awaitIndex(fn () => indexHits(w('Eisvogel'))->pluck('id')->sort()->values()->all() === collect([$first->id, $second->id])->sort()->values()->all()))->toBeTrue();

    $first->delete();
    expect(awaitIndex(fn () => indexHits(w('Eisvogel'))->pluck('id')->all() === [$second->id]))->toBeTrue();

    $second->delete();
    expect(awaitIndex(fn () => indexHits(w('Eisvogel'))->pluck('id')->all() === [$project->id]))->toBeTrue()
        ->and(indexHits(w('Eisvogel'))->first())->toMatchArray(['req_type' => 'none', 'group_id' => $project->id]);
})->group('meilisearch');

it('re-indexes a request when it is edited, and all of them when the project is', function () {
    realMeilisearch();
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create(['title' => 'Imkerei '.w('Steinkauz')]);
    $request = ProjectRequest::factory()->for($project)->create(['title' => 'Bienenkasten', 'description' => 'Alt '.w('Kiebitz').'.']);
    expect(awaitIndex(fn () => indexHas(w('Kiebitz'), $request->id)))->toBeTrue();

    $request->update(['title' => 'Bienenstock', 'description' => 'Neu '.w('Rotmilan').'.']);
    expect(awaitIndex(fn () => indexHas(w('Rotmilan'), $request->id)))->toBeTrue()
        ->and(awaitIndex(fn () => ! indexHas(w('Kiebitz'), $request->id)))->toBeTrue();

    Livewire::actingAs($owner)->test(ProjectEdit::class, ['project' => $project])->set('title', 'Imkerei '.w('Turmfalke'))->call('saveProject')->assertHasNoErrors();
    expect(awaitIndex(fn () => indexHas(w('Turmfalke'), $request->id)))->toBeTrue()
        ->and(awaitIndex(fn () => ! indexHas(w('Steinkauz'), $request->id)))->toBeTrue();
})->group('meilisearch');

it('removes a project\'s requests from the index when it turns private, and restores them when it turns public', function () {
    realMeilisearch();
    $project = Project::factory()->public()->create(['title' => 'Wandergruppe '.w('Wendehals')]);
    $request = ProjectRequest::factory()->for($project)->create();
    expect(awaitIndex(fn () => indexHas(w('Wendehals'), $request->id)))->toBeTrue();

    $project->update(['visibility' => 'private']);
    expect(awaitIndex(fn () => indexHits(w('Wendehals'))->isEmpty()))->toBeTrue();

    $project->update(['visibility' => 'public']);
    expect(awaitIndex(fn () => indexHas(w('Wendehals'), $request->id)))->toBeTrue()
        ->and(indexHas(w('Wendehals'), $project->id))->toBeFalse();
})->group('meilisearch');

it('never indexes the requests of a private project (BUG-002)', function () {
    realMeilisearch();
    $project = Project::factory()->private()->create(['title' => 'Verborgen '.w('Schleiereule')]);
    ProjectRequest::factory()->for($project)->create(['title' => 'Versteckt Uhu', 'description' => 'Geheim '.w('Wachtelkoenig').'.']);

    usleep(400_000);

    expect(indexHits(w('Wachtelkoenig')))->toBeEmpty()->and(indexHits(w('Schleiereule')))->toBeEmpty();
})->group('meilisearch');

it('removes a project\'s request documents when the project is deleted', function () {
    realMeilisearch();
    $project = Project::factory()->public()->create(['title' => 'Aufgegeben '.w('Kranich')]);
    $request = ProjectRequest::factory()->for($project)->create();
    expect(awaitIndex(fn () => indexHas(w('Kranich'), $request->id)))->toBeTrue();

    $project->delete();

    expect(awaitIndex(fn () => indexHits(w('Kranich'))->isEmpty()))->toBeTrue();
})->group('meilisearch');

it('finds a project on the search page through the text of its request, once, in its own card', function () {
    realMeilisearch();
    $project = Project::factory()->public()->create(['title' => 'Nachbarschaftsfest']);
    ProjectRequest::factory()->count(2)->for($project)->create(['description' => 'Wir suchen Bierbaenke '.w('Sperber').'.']);
    Project::factory()->private()->create(['title' => 'Privat '.w('Sperber')]);

    $html = '';
    for ($attempt = 0; $attempt < 40 && ! str_contains($html, 'Nachbarschaftsfest'); $attempt++) {
        usleep(100_000);
        $html = Livewire::test(Search::class)->set('query', w('Sperber'))->call('load')->html();
    }

    expect($html)->toContain('Nachbarschaftsfest')
        ->and(substr_count($html, 'data-test="route_hitcard"'))->toBe(1)
        // Both requests matched; both are nested in the one card.
        ->and(substr_count($html, 'data-test="card_request-hit"'))->toBe(2)
        ->and($html)->not->toContain('Privat '.w('Sperber'));
})->group('meilisearch');

it('does not show a project on the search page for a stale request document of a project that is private', function () {
    realMeilisearch();
    $project = Project::factory()->private()->create(['title' => 'Kurzzeitig Oeffentlich '.w('Bussard')]);
    $request = ProjectRequest::factory()->for($project)->create();

    // A document left behind in the index by a missed sync, written straight to the engine.
    app(EngineManager::class)->engine()->update(collect([$request->load('project')]));
    expect(awaitIndex(fn () => indexHas(w('Bussard'), $request->id)))->toBeTrue();

    Livewire::test(Search::class)->set('query', w('Bussard'))->call('load')->assertOk()->assertDontSee('Kurzzeitig Oeffentlich '.w('Bussard'));
})->group('meilisearch');
