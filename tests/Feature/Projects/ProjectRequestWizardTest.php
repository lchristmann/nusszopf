<?php

use App\Livewire\Projects\ProjectWizard;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Step 3 ("Gesuche") of the creation wizard (`RequestsStep.js`,
 * `EditRequestDialog.js`) — docs/rewrite/third-slice.md: requests live in the
 * wizard's form state, are validated by the dialog and again at creation, and
 * are persisted together with the project — or not at all (BUG-027).
 */
function reqDoc(string $text): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

/** A wizard at step 2 with a valid step 1 and 2. */
function wizardAtRequests(?User $user = null): Testable
{
    return Livewire::actingAs($user ?? User::factory()->create())->withQueryParams(['step' => 0])->test(ProjectWizard::class)
        ->set('title', 'Nachbarschaftsgarten')
        ->set('goal', 'Eine grüne Fläche für alle schaffen.')
        ->set('description', reqDoc('Wir legen gemeinsam einen Garten an.'))
        ->set('location.remote', true)
        ->set('period.flexible', true)
        ->call('next')
        ->call('next')
        ->assertSet('step', 2);
}

function addRequest(Testable $wizard, string $title = 'Hochbeete', string $category = 'materials', string $text = 'Wir suchen Holz.'): Testable
{
    return $wizard->call('openRequestDialog')
        ->set('requestTitle', $title)
        ->set('requestCategory', $category)
        ->set('requestDescription', reqDoc($text))
        ->call('saveRequest');
}

it('shows the historical step 3 with an enabled create button and the info card while there are no requests', function () {
    wizardAtRequests()
        ->assertSee('Gesuche')
        ->assertSee('Projektgesuche')
        ->assertSee('Gesuche in dem Projekt zeigen anderen Nusszopfer:innen, was für die Projektumsetzung noch alles benötigt wird.')
        ->assertSee('Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.')
        ->assertDontSee('Erstellte Gesuche')
        ->assertSeeHtml('data-test="btn_create_requests-step"')
        ->assertDontSeeHtml('data-test="edit-request-dialog"')
        ->assertSet('requests', []);
});

it('opens an empty dialog and closes it again without a request', function () {
    wizardAtRequests()
        ->call('openRequestDialog')
        ->assertSet('requestDialogOpen', true)
        ->assertSet('requestKey', null)
        ->assertSet('requestTitle', '')
        ->assertSet('requestCategory', '')
        ->assertSet('requestDescription', RichText::empty())
        ->assertSeeHtml('data-test="edit-request-dialog"')
        ->assertSee('Titel*')
        ->assertSee('Kategorie*')
        ->assertSee('Beschreibung*')
        ->assertSee('Wer oder was wird gesucht?')
        ->assertSee('Mitstreiter:innen')
        ->assertSee('Erstellen')
        ->assertSee('Abbrechen')
        ->call('closeRequestDialog')
        ->assertSet('requestDialogOpen', false)
        ->assertDontSeeHtml('data-test="edit-request-dialog"')
        ->assertSet('requests', []);
});

it('creates a request in the list and shows it as a card', function () {
    $wizard = addRequest(wizardAtRequests());

    $wizard->assertSet('requestDialogOpen', false)
        ->assertSee('Erstellte Gesuche')
        ->assertDontSee('Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.')
        ->assertSee('Hochbeete')
        ->assertSee('Erstellt am '.now()->format('j.n.Y'))
        ->assertSeeHtml('data-test="menu_edit-request-card"');

    expect($wizard->get('requests'))->toHaveCount(1)
        ->and($wizard->get('requests.0'))->toMatchArray(['title' => 'Hochbeete', 'category' => 'materials', 'description' => reqDoc('Wir suchen Holz.')]);
});

it('edits a request in place, keeping its date and position', function () {
    $wizard = addRequest(addRequest(wizardAtRequests(), 'Erstes'), 'Zweites', 'rooms');
    $createdAt = $wizard->get('requests.0.created_at');

    $wizard->call('editRequest', '0')
        ->assertSet('requestKey', '0')
        ->assertSet('requestTitle', 'Erstes')
        ->assertSet('requestCategory', 'materials')
        ->assertSee('Speichern')
        ->set('requestTitle', 'Erstes geändert')
        ->call('saveRequest')
        ->assertSet('requestDialogOpen', false);

    expect(collect($wizard->get('requests'))->pluck('title')->all())->toBe(['Erstes geändert', 'Zweites'])
        ->and($wizard->get('requests.0.created_at'))->toBe($createdAt);
});

it('closes the dialog without changing anything when a request is saved unchanged', function () {
    $wizard = addRequest(wizardAtRequests());
    $before = $wizard->get('requests');

    $wizard->call('editRequest', '0')->call('saveRequest')->assertSet('requestDialogOpen', false);

    expect($wizard->get('requests'))->toBe($before);
});

it('deletes a request from the list without asking and returns to the info card', function () {
    $wizard = addRequest(addRequest(wizardAtRequests(), 'Erstes'), 'Zweites');

    $wizard->call('deleteRequest', '0');
    expect(collect($wizard->get('requests'))->pluck('title')->all())->toBe(['Zweites']);

    $wizard->call('deleteRequest', '0')
        ->assertSet('requests', [])
        ->assertSee('Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.');
});

it('ignores an edit or delete of a request that is not in the list', function () {
    $wizard = addRequest(wizardAtRequests());

    $wizard->call('editRequest', '7')->assertSet('requestDialogOpen', false);
    $wizard->call('deleteRequest', '7');

    expect($wizard->get('requests'))->toHaveCount(1);
});

// --- The dialog's validation -----------------------------------------------------

it('rejects an empty dialog with the historical messages and keeps it open', function () {
    wizardAtRequests()
        ->call('openRequestDialog')
        ->call('saveRequest')
        ->assertHasErrors(['requestTitle', 'requestCategory', 'requestDescription'])
        ->assertSee('Gib einen Titel ein')
        ->assertSee('Wähle eine Kategorie aus')
        ->assertSee('Gib eine Beschreibung ein')
        ->assertSet('requestDialogOpen', true)
        ->assertSet('requests', []);
});

it('validates a title of more than 40 characters, and a whitespace-only one is empty', function () {
    wizardAtRequests()->call('openRequestDialog')
        ->set('requestTitle', str_repeat('a', 41))
        ->call('blurredRequest', 'requestTitle')
        ->assertSee('Maximal 40 Zeichen')
        ->set('requestTitle', str_repeat('ä', 40))
        ->call('blurredRequest', 'requestTitle')
        ->assertHasNoErrors('requestTitle')
        ->set('requestTitle', '   ')
        ->call('blurredRequest', 'requestTitle')
        ->assertSee('Gib einen Titel ein');
});

it('only accepts the five real categories', function (string $category) {
    wizardAtRequests()->call('openRequestDialog')
        ->set('requestCategory', $category)
        ->call('blurredRequest', 'requestCategory')
        ->assertHasErrors('requestCategory');
})->with(['none', '', 'other', 'Companions']);

it('rejects a description of more than 6000 characters once serialized, and an empty one', function () {
    wizardAtRequests()->call('openRequestDialog')
        ->set('requestDescription', reqDoc(str_repeat('x', 6000)))
        ->call('blurredRequest', 'requestDescription')
        ->assertSee('Maximale Zeichenlänge erreicht')
        ->set('requestDescription', reqDoc(''))
        ->call('blurredRequest', 'requestDescription')
        ->assertSee('Gib eine Beschreibung ein')
        ->set('requestDescription', reqDoc('Kurz.'))
        ->call('blurredRequest', 'requestDescription')
        ->assertHasNoErrors('requestDescription');
});

it('shows a field error only after that field lost focus or a submit failed', function () {
    wizardAtRequests()->call('openRequestDialog')
        ->assertDontSee('Gib einen Titel ein')
        ->call('blurredRequest', 'requestTitle')
        ->assertSee('Gib einen Titel ein')
        ->assertDontSee('Wähle eine Kategorie aus');
});

// --- Step mechanics stay as they were ---------------------------------------------

it('lets the step pass with zero requests and never validates the list', function () {
    wizardAtRequests()->call('next')->assertSet('step', 3)->assertHasNoErrors();
});

it('keeps the requests when going back and forth, and discards them with the wizard', function () {
    $wizard = addRequest(wizardAtRequests());

    $wizard->call('next')->assertSet('step', 3)->call('back')->assertSet('step', 2)->assertSee('Hochbeete');

    Livewire::actingAs(User::factory()->create())->withQueryParams(['step' => 0])->test(ProjectWizard::class)->assertSet('requests', []);
});

it('closes the dialog when the step changes', function () {
    wizardAtRequests()->call('openRequestDialog')->call('back')->assertSet('requestDialogOpen', false);
});

// --- Persistence ------------------------------------------------------------------

it('creates the project together with its requests, in order, on the last step', function () {
    $user = User::factory()->create();
    $wizard = addRequest(addRequest(wizardAtRequests($user), 'Hochbeete', 'materials', 'Wir suchen Holz.'), 'Mitstreiter', 'companions', 'Wer hilft?');

    $wizard->call('next')->call('next')->assertRedirect(route('projects.mine'));

    $project = Project::sole();
    expect($project->user_id)->toBe($user->id)
        ->and($project->requests()->orderBy('id')->pluck('title')->all())->toBe(['Hochbeete', 'Mitstreiter']);

    $request = $project->requests()->where('title', 'Hochbeete')->sole();
    expect($request->category)->toBe('materials')
        ->and($request->description)->toBe('Wir suchen Holz.')
        ->and($request->description_template)->toEqual(reqDoc('Wir suchen Holz.'));
});

it('creates a project without requests exactly as before', function () {
    wizardAtRequests()->call('next')->call('next')->assertRedirect(route('projects.mine'));

    expect(Project::count())->toBe(1)->and(ProjectRequest::count())->toBe(0);
});

it('creates a private project with its requests, indexing neither', function () {
    $wizard = addRequest(wizardAtRequests());

    $wizard->call('next')->set('visibility', 'private')->call('next');

    $project = Project::sole();
    expect($project->visibility)->toBe('private')
        ->and($project->requests()->sole()->shouldBeSearchable())->toBeFalse();
});

it('stores a request description with the plain-text projection and a normalized document', function () {
    $wizard = addRequest(wizardAtRequests(), 'T', 'others', 'Text');
    $wizard->set('requests.0.description', ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Fett  und', 'marks' => [['type' => 'bold'], ['type' => 'script']]]]], ['type' => 'iframe']]]);

    $wizard->call('next')->call('next');

    $request = ProjectRequest::sole();
    expect($request->description)->toBe('Fett und')
        ->and($request->description_template['content'])->toHaveCount(1)
        ->and($request->description_template['content'][0]['content'][0]['marks'])->toBe([['type' => 'bold']]);
});

it('creates nothing when a request in the form was tampered into an invalid one (BUG-027)', function () {
    $wizard = addRequest(wizardAtRequests());
    $wizard->set('requests.0.category', 'none')->call('next')->call('next');

    expect(Project::count())->toBe(0)->and(ProjectRequest::count())->toBe(0);
    $wizard->assertNoRedirect();
});

it('creates neither the project nor any request when a write fails midway (BUG-027)', function () {
    $wizard = addRequest(addRequest(wizardAtRequests(), 'Erstes'), 'Zweites');
    $wizard->call('next');

    // The second request's insert fails at the database layer (undone with the test's transaction).
    DB::statement("ALTER TABLE project_requests ADD CONSTRAINT refuse_zweites CHECK (title <> 'Zweites')");

    $wizard->call('next');

    expect(Project::count())->toBe(0)->and(ProjectRequest::count())->toBe(0);
    $wizard->assertNoRedirect()->assertDispatched('toast', type: 'error', message: 'Sorry, das Projekt konnte nicht erstellt werden.');
});

it('authorizes creating requests only through the project the wizard creates for the user', function () {
    $user = User::factory()->create();
    $other = Project::factory()->create();

    expect(Gate::forUser($user)->allows('create', [ProjectRequest::class, $other]))->toBeFalse();
});

it('reduces a tampered request list to well-formed entries, so the step still renders', function () {
    $wizard = addRequest(wizardAtRequests());

    $wizard->set('requests', ['nonsense', ['title' => ['x'], 'created_at' => 'gestern-ish', 'category' => 5], ['title' => 'Ok', 'category' => 'rooms']])
        ->assertOk();

    expect($wizard->get('requests'))->toHaveCount(2)
        ->and($wizard->get('requests.0.title'))->toBe('')
        ->and($wizard->get('requests.1.title'))->toBe('Ok')
        ->and($wizard->get('requests.1.description'))->toBe(RichText::empty());

    $wizard->call('next')->call('next');
    expect(Project::count())->toBe(0);
});
