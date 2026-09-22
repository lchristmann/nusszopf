<?php

use App\Livewire\Projects\ProjectEdit;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectDate;
use App\Support\RichText;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The historical edit screen (`pages/user/project/[id]/edit.js`,
 * `EditProjectViews/*`) — docs/rewrite/second-slice.md: a view selector
 * (Beschreibung / Gesuche / Einstellungen), an owner-only entry, whole-form
 * validation on save, save-only-when-changed, view switches that discard edits,
 * and deletion from the settings view.
 */
function editDoc(string $text): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

function editing(Project $project, ?User $as = null): Testable
{
    return Livewire::actingAs($as ?? $project->user)->test(ProjectEdit::class, ['project' => $project]);
}

function fullProject(User $owner, array $overrides = []): Project
{
    return Project::factory()->for($owner)->public()->withLocation()->withPeriod()->create([
        'title' => 'Nachbarschaftsgarten',
        'goal' => 'Eine grüne Fläche für alle schaffen.',
        'description' => 'Wir legen gemeinsam einen Garten an.',
        'description_template' => editDoc('Wir legen gemeinsam einen Garten an.'),
        'team' => 'Anna und Ben',
        'team_template' => editDoc('Anna und Ben'),
        'motto' => 'Gemeinsam wächst mehr.',
        ...$overrides,
    ]);
}

// --- Access (BUG-021) -------------------------------------------------------------

it('sends a guest to the login screen', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.edit', $project))->assertRedirect(route('login'));
});

it('renders the edit screen for the owner, public or private', function (string $visibility) {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['visibility' => $visibility, 'title' => 'Mein Projekt']);

    $this->actingAs($owner)->get(route('projects.edit', $project))->assertOk()->assertSee('Mein Projekt');
})->with(['public', 'private']);

it('404s a non-owner, public or private, never a 403', function (string $visibility) {
    $project = Project::factory()->create(['visibility' => $visibility]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('projects.edit', $project))->assertNotFound();
    editing($project, $stranger)->assertNotFound();
})->with(['public', 'private']);

it('404s an unknown project', function () {
    $this->actingAs(User::factory()->create())->get(route('projects.edit', 'nicht-vorhanden'))->assertNotFound();
});

// --- Loading -----------------------------------------------------------------------

it('loads every stored value into the form, with dates shown as d.m.yyyy', function () {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $project = fullProject($owner, ['contact' => 'owner@example.test']);

    editing($project)
        ->assertSet('title', 'Nachbarschaftsgarten')
        ->assertSet('goal', 'Eine grüne Fläche für alle schaffen.')
        ->assertSet('description', editDoc('Wir legen gemeinsam einen Garten an.'))
        ->assertSet('team', editDoc('Anna und Ben'))
        ->assertSet('motto', 'Gemeinsam wächst mehr.')
        ->assertSet('location.remote', false)
        ->assertSet('location.searchTerm', 'Leipzig, Sachsen, Deutschland')
        ->assertSet('location.data.city', 'Leipzig')
        ->assertSet('period', ['flexible' => false, 'from' => '1.3.2027', 'to' => '31.5.2027'])
        ->assertSet('visibility', 'public')
        ->assertSet('contact', true)
        ->assertSet('view', 'Beschreibung');
});

it('reads a Nusszopf contact as contact = false', function () {
    editing(fullProject(User::factory()->create()))->assertSet('contact', false);
});

it('loads a project created by the first slice, which has no structured fields yet', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create([
        'description' => "Zeile eins\nZeile zwei",
        'description_template' => null,
        'location' => null,
        'period' => null,
        'team' => null,
        'team_template' => null,
        'motto' => null,
    ]);

    editing($project)
        ->assertSet('description', RichText::fromPlainText("Zeile eins\nZeile zwei"))
        ->assertSet('team', RichText::empty())
        ->assertSet('motto', '')
        ->assertSet('location.remote', true)
        ->assertSet('period.flexible', true)
        ->assertOk();
});

it('shows the view selector with the three historical views and the project title', function () {
    editing(fullProject(User::factory()->create()))
        ->assertSee('Nachbarschaftsgarten')
        ->assertSeeHtml('data-test="select_view_edit-project-page"')
        ->assertSee('Beschreibung')
        ->assertSee('Gesuche')
        ->assertSee('Einstellungen');
});

// --- Beschreibung: save ---------------------------------------------------------------

it('saves every description field, sanitizing and re-deriving the plain text', function () {
    $owner = User::factory()->create();
    $project = fullProject($owner);

    editing($project)
        ->set('title', 'Neuer Titel')
        ->set('goal', 'Neues Ziel')
        ->set('description', editDoc('Neue Beschreibung'))
        ->set('team', editDoc('Neues Team'))
        ->set('motto', 'Neues Motto')
        ->set('location.remote', true)
        ->set('period.flexible', false)
        ->set('period.from', '2.4.2027')
        ->set('period.to', '3.4.2027')
        ->call('saveProject')
        ->assertDispatched('toast', type: 'success', message: 'Projekt wurde aktualisiert.')
        ->assertDispatched('form-saved');

    $fresh = $project->fresh();

    expect($fresh->title)->toBe('Neuer Titel')
        ->and($fresh->goal)->toBe('Neues Ziel')
        ->and($fresh->description)->toBe('Neue Beschreibung')
        ->and($fresh->description_template)->toEqual(editDoc('Neue Beschreibung'))
        ->and($fresh->team)->toBe('Neues Team')
        ->and($fresh->motto)->toBe('Neues Motto')
        ->and($fresh->location)->toEqual(['remote' => true, 'searchTerm' => '', 'data' => []])
        ->and(ProjectDate::toDisplay($fresh->period['from']))->toBe('2.4.2027')
        ->and(ProjectDate::toDisplay($fresh->period['to']))->toBe('3.4.2027')
        // Settings are not part of this view's save.
        ->and($fresh->visibility)->toBe('public')
        ->and($fresh->user_id)->toBe($owner->id);
});

it('does nothing when saving an unchanged form', function () {
    $project = fullProject(User::factory()->create());
    $before = $project->fresh();

    $this->travel(1)->hours();

    editing($project)->call('saveProject')->assertNotDispatched('toast');

    expect($project->fresh()->updated_at->equalTo($before->updated_at))->toBeTrue();
});

it('validates the whole description view at once with the wizard\'s messages', function () {
    $project = fullProject(User::factory()->create());

    editing($project)
        ->set('title', '')
        ->set('goal', str_repeat('a', 151))
        ->set('description', RichText::empty())
        ->set('motto', str_repeat('a', 201))
        ->set('team', editDoc(str_repeat('a', 6000)))
        ->set('location.remote', false)
        ->set('location.searchTerm', '')
        ->set('period.from', 'x')
        ->set('period.to', '')
        ->call('saveProject')
        ->assertHasErrors(['title', 'goal', 'description', 'motto', 'team', 'location.searchTerm', 'period.from', 'period.to'])
        ->assertNotDispatched('toast')
        ->assertSee('Gib einen Titel ein')
        ->assertSee('Nicht mehr als 150 Zeichen')
        ->assertSee('Gib eine Beschreibung ein')
        ->assertSee('Nicht mehr als 200 Zeichen')
        ->assertSee('Maximale Zeichenlänge erreicht')
        ->assertSee('Gib einen Ort ein')
        ->assertSee('Nicht im Format dd.mm.yyyy')
        ->assertSee('Gib ein Enddatum ein');

    expect($project->fresh()->title)->toBe('Nachbarschaftsgarten');
});

it('does not let a stale end date block the save of a flexible period (BUG-022)', function () {
    $project = fullProject(User::factory()->create());

    editing($project)->set('period.from', '1.3.2027')->set('period.to', '1.1.2027')->set('period.flexible', true)->set('motto', 'x')
        ->call('saveProject')->assertHasNoErrors();

    expect($project->fresh()->period)->toEqual(['flexible' => true, 'from' => '', 'to' => '']);
});

it('shows a field error on blur, as in the wizard', function () {
    editing(fullProject(User::factory()->create()))->set('title', '')->call('blurred', 'title')->assertSee('Gib einen Titel ein');
});

// --- Search synchronization ---------------------------------------------------------------

it('re-indexes a changed searchable field of a public project through Scout', function () {
    $project = fullProject(User::factory()->create());
    $queued = [];

    // The Searchable observer fires on save; it is what keeps the index in sync.
    Project::saved(function (Project $saved) use (&$queued): void {
        $queued[] = $saved->toSearchableArray()['title'];
    });

    editing($project)->set('title', 'Gartenprojekt Neu')->call('saveProject');

    expect($queued)->toContain('Gartenprojekt Neu');
});

// --- Einstellungen -----------------------------------------------------------------------------

it('saves visibility and contact from the settings view', function () {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $project = fullProject($owner, ['visibility' => 'public', 'contact' => Project::NUSSZOPF_CONTACT]);

    editing($project)
        ->call('selectView', 'Einstellungen')
        ->set('visibility', 'private')
        ->set('contact', true)
        ->call('saveSettings')
        ->assertDispatched('toast', type: 'success', message: 'Projekt wurde aktualisiert.');

    expect($project->fresh()->visibility)->toBe('private')
        ->and($project->fresh()->contact)->toBe('owner@example.test');

    editing($project->fresh())->call('selectView', 'Einstellungen')->set('contact', false)->call('saveSettings');

    expect($project->fresh()->contact)->toBe(Project::NUSSZOPF_CONTACT);
});

it('lets the owner publish a private project through the same update path', function () {
    $owner = User::factory()->create();
    $project = fullProject($owner, ['visibility' => 'private']);

    editing($project)->call('selectView', 'Einstellungen')->set('visibility', 'public')->call('saveSettings');

    expect($project->fresh()->visibility)->toBe('public');
});

it('rejects a tampered visibility value instead of hitting the database constraint', function () {
    $project = fullProject(User::factory()->create());

    editing($project)->call('selectView', 'Einstellungen')->set('visibility', 'secret')->call('saveSettings')
        ->assertDispatched('toast', type: 'error', message: 'Sorry, die Änderungen konnten nicht gespeichert werden.');

    expect($project->fresh()->visibility)->toBe('public');
});

it('does not save settings from the description view, nor the description from the settings view', function () {
    $project = fullProject(User::factory()->create());

    editing($project)->set('visibility', 'private')->call('saveSettings');
    expect($project->fresh()->visibility)->toBe('public');

    editing($project)->call('selectView', 'Einstellungen')->set('title', 'Anders')->call('saveProject');
    expect($project->fresh()->title)->toBe('Nachbarschaftsgarten');
});

it('does not save settings from the settings view when nothing changed', function () {
    $project = fullProject(User::factory()->create());

    editing($project)->call('selectView', 'Einstellungen')->call('saveSettings')->assertNotDispatched('toast');
});

// --- Views ---------------------------------------------------------------------------------------

it('discards unsaved edits when the view changes', function () {
    $project = fullProject(User::factory()->create());

    editing($project)
        ->set('title', 'Nicht gespeichert')
        ->call('selectView', 'Einstellungen')
        ->assertSet('view', 'Einstellungen')
        ->assertSet('title', 'Nachbarschaftsgarten')
        ->call('selectView', 'Beschreibung')
        ->assertSet('title', 'Nachbarschaftsgarten');
});

it('shows the Gesuche view with its historical copy (scaffolding until ProjectRequests exist)', function () {
    editing(fullProject(User::factory()->create()))
        ->call('selectView', 'Gesuche')
        ->assertSee('Projektgesuche')
        ->assertSee('Aktuelle Gesuche')
        ->assertSee('Alles zopfig! Derzeit gibt es keine Gesuche.');
});

it('ignores an unknown view', function () {
    editing(fullProject(User::factory()->create()))->call('selectView', 'Admin')->assertSet('view', 'Beschreibung');
});

// --- Deletion ---------------------------------------------------------------------------------------

it('lets the owner delete the project from the settings view', function () {
    $project = fullProject(User::factory()->create());

    editing($project)->call('selectView', 'Einstellungen')
        ->assertSee('Projekt löschen')
        ->assertSee('Nach dem Löschen können die Daten nicht wieder hergestellt werden.')
        ->call('deleteProject')
        ->assertRedirect(route('projects.mine'));

    expect(Project::find($project->id))->toBeNull()
        ->and(session('toast'))->toBe(['type' => 'success', 'message' => 'Das Projekt wurde gelöscht.']);
});

it('cannot delete another user\'s project', function () {
    $project = fullProject(User::factory()->create());

    editing($project, User::factory()->create())->assertNotFound();

    expect(Project::find($project->id))->not->toBeNull();
});

// --- Contact e-mail verification (decision A-3, docs/rewrite/decisions-register.md) --------------

it('refuses to save "Persönlich" as the contact for an unverified owner', function () {
    $owner = User::factory()->unverified()->create();
    $project = fullProject($owner, ['visibility' => 'public', 'contact' => Project::NUSSZOPF_CONTACT]);

    editing($project, $owner)
        ->call('selectView', 'Einstellungen')
        ->set('contact', true)
        ->call('saveSettings')
        ->assertHasErrors(['contact']);

    expect($project->fresh()->contact)->toBe(Project::NUSSZOPF_CONTACT);
});

it('lets a verified owner save "Persönlich" as the contact', function () {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $project = fullProject($owner, ['visibility' => 'public', 'contact' => Project::NUSSZOPF_CONTACT]);

    editing($project, $owner)
        ->call('selectView', 'Einstellungen')
        ->set('contact', true)
        ->call('saveSettings')
        ->assertHasNoErrors(['contact']);

    expect($project->fresh()->contact)->toBe('owner@example.test');
});

it('still lets an unverified owner keep or choose "Über Nusszopf"', function () {
    $owner = User::factory()->unverified()->create();
    $project = fullProject($owner, ['visibility' => 'public', 'contact' => 'owner@example.test']);

    editing($project, $owner)
        ->call('selectView', 'Einstellungen')
        ->set('contact', false)
        ->call('saveSettings')
        ->assertHasNoErrors(['contact']);

    expect($project->fresh()->contact)->toBe(Project::NUSSZOPF_CONTACT);
});
