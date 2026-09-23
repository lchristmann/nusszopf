<?php

use App\Livewire\Projects\ProjectWizard;
use App\Models\Project;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The historical four-step creation wizard (`pages/user/project/create.js`,
 * `useStepper.js`) — docs/rewrite/second-slice.md. Covers step order/labels,
 * forward-only per-step validation, `?step=N` handling, the absence of any
 * draft, the persisted shape, and the authorization boundary.
 */
function doc(string $text): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

function leipzig(): array
{
    return [
        'key' => 'stub-1',
        'postcode' => '04109',
        'city' => 'Leipzig',
        'countryCode' => 'de',
        'geo' => ['lat' => '51.3406321', 'lon' => '12.3747329'],
        'osm' => ['id' => '62649', 'type' => 'relation'],
    ];
}

function wizard(?User $user = null): Testable
{
    return Livewire::actingAs($user ?? User::factory()->create())->withQueryParams(['step' => 0])->test(ProjectWizard::class);
}

/** A fully valid step 1 (remote + flexible, the shortest valid path). */
function completeStepOne(Testable $wizard): Testable
{
    return $wizard
        ->set('title', 'Nachbarschaftsgarten')
        ->set('goal', 'Eine grüne Fläche für alle schaffen.')
        ->set('description', doc('Wir legen gemeinsam einen Garten an.'))
        ->set('location.remote', true)
        ->set('period.flexible', true);
}

// --- Access ---------------------------------------------------------------

it('sends a guest to the login screen', function () {
    $this->get(route('projects.create'))->assertRedirect(route('login'));
    $this->get(route('projects.create', ['step' => 2]))->assertRedirect(route('login'));
});

it('renders for an authenticated user, starting at step 0 with the historical defaults', function () {
    wizard()
        ->assertOk()
        ->assertSet('step', 0)
        ->assertSet('visibility', 'public')
        ->assertSet('contact', false)
        ->assertSet('location', ['remote' => false, 'searchTerm' => '', 'data' => []])
        ->assertSet('period', ['flexible' => false, 'from' => '', 'to' => ''])
        ->assertSet('description', RichText::empty())
        ->assertSet('team', RichText::empty())
        ->assertSee('Neues Projekt')
        ->assertSee('Beschreibung 1/2')
        ->assertSee('Projekttitel*')
        ->assertDontSee('Zurück');
});

// --- Step navigation and the URL -------------------------------------------

it('always starts at step 0: opening it without or with any other ?step= lands on ?step=0', function (mixed $requested) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.create', $requested === null ? [] : ['step' => $requested]))
        ->assertRedirect(route('projects.create', ['step' => 0]));
})->with([[null], [3], [1], ['abc'], [-1], [99]]);

it('renders ?step=0 as the first step', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('projects.create', ['step' => 0]))
        ->assertOk()
        ->assertSee('Beschreibung 1/2')
        ->assertSee('Projekttitel*');
});

it('shows the four steps in order with their labels and progress', function () {
    $wizard = completeStepOne(wizard());

    $labels = ['Beschreibung 1/2', 'Beschreibung 2/2', 'Gesuche', 'Einstellungen'];
    $wizard->assertSee($labels[0])->assertSeeHtml('width: 25%');

    $wizard->call('next')->assertSet('step', 1)->assertSee($labels[1])->assertSeeHtml('width: 50%');
    $wizard->call('next')->assertSet('step', 2)->assertSee($labels[2])->assertSeeHtml('width: 75%');
    $wizard->call('next')->assertSet('step', 3)->assertSee($labels[3])->assertSeeHtml('width: 100%');
});

it('labels the button Weiter until the last step, where it is Erstellen', function () {
    $wizard = completeStepOne(wizard());

    $wizard->assertSeeHtml('data-test="btn_create-or-next_navigation"')->assertSee('Weiter')->assertDontSee('Erstellen');
    $wizard->call('next')->call('next')->assertSee('Weiter');
    $wizard->call('next')->assertSee('Erstellen')->assertDontSee('Weiter');
});

it('shows the back button from step 1 on, and going back never validates', function () {
    $wizard = completeStepOne(wizard())->call('next')->assertSet('step', 1)->assertSee('Zurück');

    // Make step 1's own state invalid; going back must still work.
    $wizard->set('motto', str_repeat('a', 500))->call('back')->assertSet('step', 0)->assertHasNoErrors();

    // And back from step 0 stays on step 0.
    $wizard->call('back')->assertSet('step', 0);
});

it('follows a step restored by the browser history without validating it', function () {
    // Browser back/forward changes `?step=`, which Livewire applies as a property update.
    wizard()->set('step', 2)->assertSet('step', 2)->assertSee('Gesuche')
        ->set('step', 3)->assertSet('step', 3)->assertSee('Einstellungen')
        ->set('step', '1')->assertSet('step', 1)->assertSee('Beschreibung 2/2');
});

it('ignores an out-of-range or non-numeric step from the URL and keeps the current step', function (mixed $bad) {
    wizard()->set('step', 1)->set('step', $bad)->assertSet('step', 1);
})->with([[4], [-1], ['abc'], ['1.5'], [''], [99]]);

it('drops the errors when the step changes', function () {
    $wizard = wizard()->call('next')->assertHasErrors('title');

    $wizard->set('step', 1)->assertHasNoErrors();
});

// --- Step 1 validation (title, goal, description, location, period) ----------

it('blocks Weiter on step 1 with the historical messages when everything is empty', function () {
    wizard()
        ->call('next')
        ->assertSet('step', 0)
        ->assertHasErrors(['title', 'goal', 'description', 'location.searchTerm', 'period.from', 'period.to'])
        ->assertSee('Gib einen Titel ein')
        ->assertSee('Gib ein Ziel ein')
        ->assertSee('Gib eine Beschreibung ein')
        ->assertSee('Gib einen Ort ein')
        ->assertSee('Gib ein Startdatum ein')
        ->assertSee('Gib ein Enddatum ein');
});

it('enforces title 40 and goal 150 characters', function () {
    completeStepOne(wizard())
        ->set('title', str_repeat('a', 41))
        ->set('goal', str_repeat('a', 151))
        ->call('next')
        ->assertSet('step', 0)
        ->assertSee('Nicht mehr als 40 Zeichen')
        ->assertSee('Nicht mehr als 150 Zeichen');

    completeStepOne(wizard())
        ->set('title', str_repeat('a', 40))
        ->set('goal', str_repeat('a', 150))
        ->call('next')
        ->assertSet('step', 1);
});

it('rejects a whitespace-only title or goal (BUG-026)', function () {
    completeStepOne(wizard())->set('title', '   ')->set('goal', "\t ")->call('next')
        ->assertSet('step', 0)
        ->assertSee('Gib einen Titel ein')
        ->assertSee('Gib ein Ziel ein');
});

it('requires a description, treating only a lone empty paragraph as empty', function () {
    completeStepOne(wizard())->set('description', RichText::empty())->call('next')->assertSet('step', 0)->assertHasErrors('description');
    completeStepOne(wizard())->set('description', doc(' '))->call('next')->assertSet('step', 1)->assertHasNoErrors();
});

it('rejects a description whose serialized document exceeds 6000 characters', function () {
    completeStepOne(wizard())
        ->set('description', doc(str_repeat('a', 6000)))
        ->call('next')
        ->assertSet('step', 0)
        ->assertSee('Maximale Zeichenlänge erreicht');
});

it('accepts a location-independent project without any place', function () {
    completeStepOne(wizard())->set('location.remote', true)->call('next')->assertSet('step', 1);
});

it('requires a place for a fixed location and that it be picked from the suggestions', function () {
    completeStepOne(wizard())
        ->set('location.remote', false)
        ->set('location.searchTerm', '')
        ->call('next')
        ->assertSet('step', 0)
        ->assertSee('Gib einen Ort ein');

    // Typed text without choosing a suggestion.
    Http::fake(['*' => Http::response([], 200)]);
    completeStepOne(wizard())
        ->set('location.remote', false)
        ->set('location.searchTerm', 'Leipz')
        ->call('next')
        ->assertSet('step', 0)
        ->assertSee('Wähle einen Ort aus der Liste aus');
});

it('validates a fixed period: required, dd.mm.yyyy, and ordering', function () {
    $fixed = fn () => completeStepOne(wizard())->set('period.flexible', false);

    $fixed()->call('next')->assertSee('Gib ein Startdatum ein')->assertSee('Gib ein Enddatum ein');

    $fixed()->set('period.from', 'morgen')->set('period.to', '31.2.2027')->call('next')
        ->assertSet('step', 0)
        ->assertSee('Nicht im Format dd.mm.yyyy');

    $fixed()->set('period.from', '1.3.2027')->set('period.to', '28.2.2027')->call('next')
        ->assertSet('step', 0)
        ->assertSee('Enddatum vor Startdatum');

    $fixed()->set('period.from', '1.3.2027')->set('period.to', '1.3.2027')->call('next')->assertSet('step', 1);
    $fixed()->set('period.from', '01.03.27')->set('period.to', '31.05.27')->call('next')->assertSet('step', 1);
});

it('does not apply period rules while the period is flexible, not even the ordering rule (BUG-022)', function () {
    completeStepOne(wizard())
        ->set('period.flexible', false)
        ->set('period.from', '1.3.2027')
        ->set('period.to', '1.1.2027')
        ->set('period.flexible', true)
        ->call('next')
        ->assertSet('step', 1)
        ->assertHasNoErrors();
});

// --- Blur validation (Formik `touched`) --------------------------------------

it('shows a field error only once that field was blurred', function () {
    wizard()
        ->assertDontSee('Gib einen Titel ein')
        ->call('blurred', 'title')
        ->assertSee('Gib einen Titel ein')
        ->assertDontSee('Gib ein Ziel ein')
        ->set('title', 'Fertig')
        ->call('blurred', 'title')
        ->assertDontSee('Gib einen Titel ein');
});

it('only validates fields that are on the current step', function () {
    wizard()->call('blurred', 'motto')->assertHasNoErrors();
});

it('re-checks an entered end date when the start date is edited', function () {
    completeStepOne(wizard())
        ->set('period.flexible', false)
        ->set('period.from', '1.3.2027')
        ->set('period.to', '1.2.2027')
        ->call('blurred', 'period.from')
        ->assertHasErrors('period.to');
});

// --- Step 2 (team, motto) -----------------------------------------------------

it('lets step 2 be passed with an empty team and motto', function () {
    completeStepOne(wizard())->call('next')->assertSet('step', 1)->call('next')->assertSet('step', 2)->assertHasNoErrors();
});

it('enforces motto 200 characters and team 6000 characters on step 2', function () {
    completeStepOne(wizard())->call('next')
        ->set('motto', str_repeat('a', 201))
        ->call('next')
        ->assertSet('step', 1)
        ->assertSee('Nicht mehr als 200 Zeichen');

    completeStepOne(wizard())->call('next')
        ->set('team', doc(str_repeat('a', 6000)))
        ->call('next')
        ->assertSet('step', 1)
        ->assertSee('Maximale Zeichenlänge erreicht');
});

// --- Step 3 (Gesuche — intentional scaffolding) ------------------------------------

it('shows the Gesuche step with its historical copy and lets it be passed with zero requests', function () {
    completeStepOne(wizard())->call('next')->call('next')
        ->assertSet('step', 2)
        ->assertSee('Projektgesuche')
        ->assertSee('Gesuche in dem Projekt zeigen anderen Nusszopfer:innen, was für die Projektumsetzung noch alles benötigt wird.')
        ->assertSee('Gesuch erstellen')
        ->assertSee('Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.')
        ->call('next')
        ->assertSet('step', 3);
});

// --- Step 4 (visibility, contact) and creation --------------------------------------

it('offers the two visibility options and the two contact options, with the owner e-mail truncated', function () {
    $user = User::factory()->create(['email' => 'eine.sehr.lange.adresse@beispiel-domain.example']);

    completeStepOne(wizard($user))->call('next')->call('next')->call('next')
        ->assertSet('step', 3)
        ->assertSee('Sichtbarkeit')
        // BUG-025: "Peronen" corrected.
        ->assertSee('nur für bestimmte Personen sichtbar sein?')
        ->assertDontSee('Peronen')
        ->assertSee('Öffentlich')
        ->assertSee('Privat')
        ->assertSee('Kontaktmöglichkeit')
        ->assertSee('Persönlich')
        ->assertSee('Über Nusszopf')
        ->assertSee('eine.sehr.lange.adress...')
        ->assertDontSee('eine.sehr.lange.adresse@beispiel-domain.example');
});

it('creates the project with every wizard field on the final step, then redirects to My Projects', function () {
    $user = User::factory()->create();
    Http::fake();

    $wizard = wizard($user)
        ->set('title', 'Nachbarschaftsgarten')
        ->set('goal', 'Eine grüne Fläche für alle schaffen.')
        ->set('description', doc('Wir legen gemeinsam einen Garten an.'))
        ->set('location.remote', false)
        ->set('location.searchTerm', 'Leipzig, Sachsen, Deutschland')
        ->set('location.data', leipzig())
        ->set('period.flexible', false)
        ->set('period.from', '1.3.2027')
        ->set('period.to', '31.5.2027')
        ->call('next')
        ->set('team', doc('Anna und Ben'))
        ->set('motto', 'Gemeinsam wächst mehr.')
        ->call('next')->call('next')
        ->set('visibility', 'private')
        ->set('contact', true)
        ->call('next')
        ->assertRedirect(route('projects.mine'));

    $project = Project::sole();

    expect($project->user_id)->toBe($user->id)
        ->and($project->title)->toBe('Nachbarschaftsgarten')
        ->and($project->goal)->toBe('Eine grüne Fläche für alle schaffen.')
        ->and($project->description)->toBe('Wir legen gemeinsam einen Garten an.')
        ->and($project->description_template)->toEqual(doc('Wir legen gemeinsam einen Garten an.'))
        ->and($project->team)->toBe('Anna und Ben')
        ->and($project->team_template)->toEqual(doc('Anna und Ben'))
        ->and($project->motto)->toBe('Gemeinsam wächst mehr.')
        ->and($project->visibility)->toBe('private')
        ->and($project->contact)->toBe($user->email)
        ->and($project->location)->toEqual(['remote' => false, 'searchTerm' => 'Leipzig, Sachsen, Deutschland', 'data' => leipzig()])
        ->and($project->period['flexible'])->toBeFalse()
        ->and($project->period['from'])->toMatch('/^2027-03-01T00:00:00[+-]\d{2}:\d{2}$/')
        ->and($project->period['to'])->toMatch('/^2027-05-31T00:00:00[+-]\d{2}:\d{2}$/');

    expect(session('toast'))->toBe(['type' => 'success', 'message' => 'Projekt wurde erstellt.']);
});

// --- Contact e-mail verification (decision A-3, docs/rewrite/decisions-register.md) --------------

it('refuses to create a project with "Persönlich" as the contact for an unverified owner', function () {
    $user = User::factory()->unverified()->create();

    completeStepOne(wizard($user))->call('next')->call('next')->call('next')
        ->set('contact', true)
        ->call('next')
        ->assertHasErrors(['contact']);

    expect(Project::count())->toBe(0);
});

it('defaults to a public project reachable through Nusszopf when the last step is left alone', function () {
    $user = User::factory()->create();

    completeStepOne(wizard($user))->call('next')->call('next')->call('next')->call('next')->assertRedirect(route('projects.mine'));

    $project = Project::sole();

    expect($project->visibility)->toBe('public')
        ->and($project->contact)->toBe(Project::NUSSZOPF_CONTACT)
        ->and($project->location)->toEqual(['remote' => true, 'searchTerm' => '', 'data' => []])
        ->and($project->period)->toEqual(['flexible' => true, 'from' => '', 'to' => ''])
        ->and($project->motto)->toBe('')
        ->and($project->team)->toBe('');
});

it('clears a stale place and dates when the remote/flexible switches are on', function () {
    completeStepOne(wizard())
        ->set('location.searchTerm', 'Leipzig, Sachsen, Deutschland')
        ->set('location.data', leipzig())
        ->set('location.remote', true)
        ->set('period.from', '1.3.2027')
        ->set('period.to', '31.5.2027')
        ->set('period.flexible', true)
        ->call('next')->call('next')->call('next')->call('next');

    $project = Project::sole();

    expect($project->location)->toEqual(['remote' => true, 'searchTerm' => '', 'data' => []])
        ->and($project->period)->toEqual(['flexible' => true, 'from' => '', 'to' => '']);
});

it('creates nothing until the last step submits (no draft)', function () {
    completeStepOne(wizard())->call('next')->call('next')->call('next');

    expect(Project::count())->toBe(0);
});

it('discards all progress when the wizard is opened again', function () {
    $user = User::factory()->create();

    completeStepOne(wizard($user))->call('next');

    wizard($user)->assertSet('step', 0)->assertSet('title', '')->assertSet('goal', '');
});

it('re-validates steps 1 and 2 at the point of persistence and reports it with a toast', function () {
    $wizard = completeStepOne(wizard())->call('next')->call('next')->call('next');

    // Corrupt earlier state behind the stepper's back, then submit the last step.
    $wizard->set('title', '')->call('next')
        ->assertNoRedirect()
        ->assertDispatched('toast', type: 'error', message: 'Bitte überprüfe deine Eingaben oder versuche es später erneut.');

    expect(Project::count())->toBe(0);
});

it('always creates the project for the caller — there is no way to name another owner', function () {
    $user = User::factory()->create();
    User::factory()->create();

    completeStepOne(wizard($user))->call('next')->call('next')->call('next')->call('next');

    expect(Project::sole()->user_id)->toBe($user->id);
});

// --- Sanitization at persistence -------------------------------------------------------------

it('stores only what the six-tool toolbar can produce, dropping tampered nodes', function () {
    $tampered = ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'content' => [['type' => 'text', 'text' => 'weg']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'bleibt', 'marks' => [['type' => 'bold'], ['type' => 'code']]]]],
    ]];

    completeStepOne(wizard())->set('description', $tampered)->call('next')->call('next')->call('next')->call('next');

    expect(Project::sole()->description_template)->toEqual(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'bleibt', 'marks' => [['type' => 'bold']]]]],
    ]])->and(Project::sole()->description)->toBe('bleibt');
});

it('rejects a tampered place selection that is not an addressable OpenStreetMap object', function () {
    $tampered = leipzig();
    $tampered['osm'] = ['id' => '1/../../evil', 'type' => 'javascript'];

    // The OSM reference is dropped; the city selection itself remains usable.
    completeStepOne(wizard())
        ->set('location.remote', false)
        ->set('location.searchTerm', 'Leipzig')
        ->set('location.data', $tampered)
        ->call('next')->call('next')->call('next')->call('next');

    expect(Project::sole()->location['data'])->not->toHaveKey('osm');
});

it('rejects a place selection without a city', function () {
    completeStepOne(wizard())
        ->set('location.remote', false)
        ->set('location.searchTerm', 'Leipzig')
        ->set('location.data', ['key' => 'x'])
        ->call('next')
        ->assertSet('step', 0)
        ->assertSee('Wähle einen Ort aus der Liste aus');
});

// --- Location autocomplete (LocationIQ) ------------------------------------------------------

function locationIqResponse(): array
{
    return [[
        'place_id' => 'abc-1',
        'osm_id' => '62649',
        'osm_type' => 'relation',
        'lat' => '51.3406321',
        'lon' => '12.3747329',
        'display_place' => 'Leipzig',
        'display_address' => 'Sachsen, Deutschland',
        'address' => ['name' => 'Leipzig', 'postcode' => '04109', 'country_code' => 'de'],
    ]];
}

beforeEach(function () {
    config(['services.locationiq.key' => 'test-key', 'services.locationiq.url' => 'https://locationiq.test/v1/autocomplete.php']);
});

it('queries LocationIQ with the historical parameters and maps the suggestions', function () {
    Http::fake(['locationiq.test/*' => Http::response(locationIqResponse())]);

    wizard()->set('location.searchTerm', 'Leip')
        ->assertSet('locationOptions', [[
            'key' => 'abc-1',
            'value' => 'Leipzig, Sachsen, Deutschland',
            'postcode' => '04109',
            'city' => 'Leipzig',
            'countryCode' => 'de',
            'geo' => ['lat' => '51.3406321', 'lon' => '12.3747329'],
            'osm' => ['id' => '62649', 'type' => 'relation'],
        ]]);

    Http::assertSent(fn ($request) => $request['q'] === 'Leip'
        && $request['countrycodes'] === 'de'
        && $request['limit'] === 5
        && $request['tag'] === 'place:city,place:town,place:village'
        && $request['accept-language'] === 'de'
        && $request['normalizecity'] === 1
        && $request['dedupe'] === 1
        && $request['key'] === 'test-key');
});

it('selects a suggestion into the search term and place data, then closes the list', function () {
    Http::fake(['locationiq.test/*' => Http::response(locationIqResponse())]);

    wizard()->set('location.searchTerm', 'Leip')
        ->call('selectLocation', 'abc-1')
        ->assertSet('location.searchTerm', 'Leipzig, Sachsen, Deutschland')
        ->assertSet('location.data', ['key' => 'abc-1'] + leipzig())
        ->assertSet('locationOptions', []);
});

it('discards a selected place as soon as the search term is edited', function () {
    Http::fake(['locationiq.test/*' => Http::response(locationIqResponse())]);

    wizard()->set('location.searchTerm', 'Leip')->call('selectLocation', 'abc-1')
        ->set('location.searchTerm', 'Leipz')
        ->assertSet('location.data', []);
});

it('clears the search term, place and suggestions', function () {
    Http::fake(['locationiq.test/*' => Http::response(locationIqResponse())]);

    wizard()->set('location.searchTerm', 'Leip')->call('clearLocation')
        ->assertSet('location.searchTerm', '')
        ->assertSet('location.data', [])
        ->assertSet('locationOptions', []);
});

it('empties the suggestions for an empty search term without calling the provider', function () {
    Http::fake();

    wizard()->set('location.searchTerm', '   ')->assertSet('locationOptions', []);

    Http::assertNothingSent();
});

it('keeps the previous suggestions when the provider fails, as historically', function () {
    Http::fake(['locationiq.test/*' => Http::sequence()->push(locationIqResponse())->push('boom', 500)]);

    wizard()->set('location.searchTerm', 'Leip')
        ->set('location.searchTerm', 'Leipz')
        ->assertCount('locationOptions', 1);
});

it('cannot pick a suggestion that was never offered', function () {
    Http::fake(['locationiq.test/*' => Http::response(locationIqResponse())]);

    wizard()->set('location.searchTerm', 'Leip')
        ->call('selectLocation', 'not-offered')
        ->assertSet('location.data', []);
});

it('returns no suggestions when no LocationIQ key is configured', function () {
    config(['services.locationiq.key' => '']);
    Http::fake();

    wizard()->set('location.searchTerm', 'Leip')->assertSet('locationOptions', []);

    Http::assertNothingSent();
});
