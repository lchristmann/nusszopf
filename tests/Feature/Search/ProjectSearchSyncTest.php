<?php

use App\Livewire\Projects\ProjectEdit;
use App\Livewire\Projects\ProjectWizard;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

/**
 * Search synchronization for the fields the Slice 2 screens edit
 * (docs/search/README.md): the indexed document carries the historical
 * `_parseProjectToDocument` field set, and every edit goes through the model
 * save that Scout observes — so a changed searchable field, or a changed
 * visibility, reaches the index without a separate code path.
 */
function syncDoc(string $text): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

it('indexes the historical document fields', function () {
    $owner = User::factory()->create(['name' => 'gartenfreund']);
    $project = Project::factory()->for($owner)->public()->withLocation('Leipzig')->withPeriod('1.3.2027', '31.5.2027')->create([
        'team' => 'Anna und Ben',
        'motto' => 'Gemeinsam wächst mehr.',
    ]);

    $document = $project->toSearchableArray();

    expect($document)->toMatchArray([
        'title' => $project->title,
        'goal' => $project->goal,
        'description' => $project->description,
        'team' => 'Anna und Ben',
        'motto' => 'Gemeinsam wächst mehr.',
        'author' => 'gartenfreund',
        'location_text' => 'Leipzig, Sachsen, Deutschland',
        'location_remote' => false,
        'period_flexible' => false,
    ])
        ->and($document['location_geo'])->toMatchArray(['lat' => '51.3406321', 'lon' => '12.3747329'])
        ->and($document['period_from'])->toBe(CarbonImmutableTimestamp('2027-03-01'))
        ->and($document['period_to'])->toBe(CarbonImmutableTimestamp('2027-05-31'));
});

function CarbonImmutableTimestamp(string $date): int
{
    return CarbonImmutable::parse($date, config('app.timezone'))->getTimestamp();
}

it('indexes no location text or dates for a remote, flexible project', function () {
    $project = Project::factory()->public()->create();

    expect($project->toSearchableArray())->toMatchArray([
        'location_text' => '',
        'location_remote' => true,
        'period_flexible' => true,
        'period_from' => null,
        'period_to' => null,
    ]);
});

it('indexes a first-slice project that has no structured fields', function () {
    $project = Project::factory()->public()->create(['location' => null, 'period' => null, 'team' => null, 'motto' => null]);

    expect($project->toSearchableArray())->toMatchArray(['location_remote' => true, 'period_flexible' => true, 'team' => null]);
});

it('creates a public project through the wizard as an indexable model, a private one as not indexable', function () {
    $user = User::factory()->create();

    foreach (['public', 'private'] as $visibility) {
        Livewire::actingAs($user)->withQueryParams(['step' => 0])->test(ProjectWizard::class)
            ->set('title', "T {$visibility}")->set('goal', 'Ziel')->set('description', syncDoc('Beschreibung'))
            ->set('location.remote', true)->set('period.flexible', true)
            ->call('next')->call('next')->call('next')
            ->set('visibility', $visibility)
            ->call('next');
    }

    expect(Project::where('title', 'T public')->sole()->shouldBeSearchable())->toBeTrue()
        ->and(Project::where('title', 'T private')->sole()->shouldBeSearchable())->toBeFalse();
});

// --- Against the real Meilisearch (deliberately small, like MeilisearchIntegrationTest) ---

/**
 * @return Collection<int, string>
 */
function searchIds(string $query): Collection
{
    return Project::search($query)->get()->pluck('id');
}

function waitFor(Closure $condition): bool
{
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($condition()) {
            return true;
        }
        usleep(100_000);
    }

    return false;
}

it('re-indexes a public project when the edit screen changes a searchable field', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create(['title' => 'Alter Titel Zaunkoenig']);
    expect(waitFor(fn () => searchIds('Zaunkoenig')->contains($project->id)))->toBeTrue();

    Livewire::actingAs($owner)->test(ProjectEdit::class, ['project' => $project])
        ->set('title', 'Neuer Titel Steinschmaetzer')
        ->set('team', syncDoc('Gruppe Rotkehlchen'))
        ->set('motto', 'Motto Blaumeise')
        ->call('saveProject')
        ->assertHasNoErrors();

    expect(waitFor(fn () => searchIds('Steinschmaetzer')->contains($project->id)))->toBeTrue()
        ->and(waitFor(fn () => searchIds('Rotkehlchen')->contains($project->id)))->toBeTrue()
        ->and(waitFor(fn () => searchIds('Blaumeise')->contains($project->id)))->toBeTrue()
        ->and(waitFor(fn () => ! searchIds('Zaunkoenig')->contains($project->id)))->toBeTrue();
})->group('meilisearch');

it('indexes a private project the moment the settings view publishes it, and removes it again when hidden', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->private()->create(['title' => 'Verstecktes Projekt Gartenrotschwanz']);
    usleep(300_000);
    expect(searchIds('Gartenrotschwanz'))->not->toContain($project->id);

    $edit = Livewire::actingAs($owner)->test(ProjectEdit::class, ['project' => $project])->call('selectView', 'Einstellungen');

    $edit->set('visibility', 'public')->call('saveSettings');
    expect(waitFor(fn () => searchIds('Gartenrotschwanz')->contains($project->id)))->toBeTrue();

    $edit->set('visibility', 'private')->call('saveSettings');
    expect(waitFor(fn () => ! searchIds('Gartenrotschwanz')->contains($project->id)))->toBeTrue();
})->group('meilisearch');

it('removes a public project from the index when it is deleted from the settings view', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create(['title' => 'Loeschkandidat Wiedehopf']);
    expect(waitFor(fn () => searchIds('Wiedehopf')->contains($project->id)))->toBeTrue();

    Livewire::actingAs($owner)->test(ProjectEdit::class, ['project' => $project])->call('selectView', 'Einstellungen')->call('deleteProject');

    expect(waitFor(fn () => ! searchIds('Wiedehopf')->contains($project->id)))->toBeTrue();
})->group('meilisearch');

it('finds a project by its location text, team and motto', function () {
    Config::set('scout.driver', 'meilisearch');
    Config::set('scout.queue', false);

    $project = Project::factory()->public()->withLocation('Kleinkleckersdorf')->create(['team' => 'Kollektiv Ziegenmelker', 'motto' => 'Wahlspruch Trauerschnaepper']);

    expect(waitFor(fn () => searchIds('Kleinkleckersdorf')->contains($project->id)))->toBeTrue()
        ->and(waitFor(fn () => searchIds('Ziegenmelker')->contains($project->id)))->toBeTrue()
        ->and(waitFor(fn () => searchIds('Trauerschnaepper')->contains($project->id)))->toBeTrue();
})->group('meilisearch');
