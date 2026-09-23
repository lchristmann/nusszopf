<?php

use App\Livewire\Projects\ProjectEdit;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The edit screen's "Gesuche" view (`RequestsView.js`) — docs/rewrite/third-slice.md:
 * the list newest first, create / edit / delete one write at a time with the
 * historical toasts, owner-only, and never reaching another project's request.
 */
function requestsView(Project $project, ?User $as = null): Testable
{
    return Livewire::actingAs($as ?? $project->user)->test(ProjectEdit::class, ['project' => $project])->call('load')->call('selectView', 'Gesuche');
}

function editDialogInput(Testable $view, string $title, string $category, string $text): Testable
{
    return $view
        ->set('requestTitle', $title)
        ->set('requestCategory', $category)
        ->set('requestDescription', ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]])
        ->call('saveRequest');
}

it('shows the historical empty state for a project without requests', function () {
    requestsView(Project::factory()->create())
        ->assertSee('Projektgesuche')
        ->assertSee('Aktuelle Gesuche')
        ->assertSee('Alles zopfig! Derzeit gibt es keine Gesuche.')
        ->assertSeeHtml('data-test="btn_create_requests-view"')
        ->assertDontSeeHtml('disabled');
});

it('lists the requests newest first as cards with their date and a menu', function () {
    $project = Project::factory()->create();
    ProjectRequest::factory()->for($project)->create(['title' => 'Ältestes', 'created_at' => now()->subDays(3)]);
    ProjectRequest::factory()->for($project)->create(['title' => 'Neuestes', 'created_at' => now()->subDay()]);
    ProjectRequest::factory()->for($project)->create(['title' => 'Mittleres', 'created_at' => now()->subDays(2)]);

    $view = requestsView($project);
    $html = $view->html();

    expect(strpos($html, 'Neuestes'))->toBeLessThan(strpos($html, 'Mittleres'))
        ->and(strpos($html, 'Mittleres'))->toBeLessThan(strpos($html, 'Ältestes'));

    $view
        ->assertSee('Erstellt am '.now()->subDay()->format('j.n.Y'))
        ->assertDontSee('Alles zopfig! Derzeit gibt es keine Gesuche.')
        ->assertSeeHtml('data-test="menu_edit-request-card"')
        ->assertSee('Bearbeiten')
        ->assertSee('Löschen')
        ->assertSee('Gesuch Menü');
});

it('lists only the requests of this project', function () {
    $project = Project::factory()->create();
    ProjectRequest::factory()->for($project)->create(['title' => 'Eigenes']);
    ProjectRequest::factory()->create(['title' => 'Fremdes']);

    requestsView($project)->assertSee('Eigenes')->assertDontSee('Fremdes');
});

it('creates a request with the historical success toast', function () {
    $project = Project::factory()->public()->create();

    editDialogInput(requestsView($project)->call('openRequestDialog'), 'Hochbeete', 'materials', 'Wir suchen Holz.')
        ->assertDispatched('toast', type: 'success', message: 'Gesuch wurde erstellt.')
        ->assertSet('requestDialogOpen', false)
        ->assertSee('Hochbeete');

    $request = $project->requests()->sole();
    expect($request->title)->toBe('Hochbeete')
        ->and($request->category)->toBe('materials')
        ->and($request->description)->toBe('Wir suchen Holz.')
        ->and($request->description_template['content'][0]['content'][0]['text'])->toBe('Wir suchen Holz.');
});

it('labels the dialog for creating and for saving, with the matching loading toast', function () {
    $project = Project::factory()->create();
    $request = ProjectRequest::factory()->for($project)->create();

    requestsView($project)->call('openRequestDialog')
        ->assertSee('Erstellen')
        ->assertSee('Gesuch erstellen...')
        ->call('closeRequestDialog')
        ->call('editRequest', $request->id)
        ->assertSee('Speichern')
        ->assertSee('Änderungen speichern...');
});

it('does not create a request that is invalid, and keeps the dialog open with the errors', function () {
    $project = Project::factory()->create();

    requestsView($project)->call('openRequestDialog')->call('saveRequest')
        ->assertHasErrors(['requestTitle', 'requestCategory', 'requestDescription'])
        ->assertSet('requestDialogOpen', true)
        ->assertNotDispatched('toast');

    expect($project->requests()->count())->toBe(0);
});

it('edits a request and shows the historical update toast', function () {
    $project = Project::factory()->create();
    $request = ProjectRequest::factory()->for($project)->category('rooms')->create(['title' => 'Alt']);

    $view = requestsView($project)->call('editRequest', $request->id)
        ->assertSet('requestKey', $request->id)
        ->assertSet('requestTitle', 'Alt')
        ->assertSet('requestCategory', 'rooms')
        ->assertSet('requestDescription', $request->description_template);

    editDialogInput($view, 'Neu', 'financials', 'Geld.')
        ->assertDispatched('toast', type: 'success', message: 'Gesuch wurde aktualisiert.')
        ->assertSet('requestDialogOpen', false);

    expect($request->fresh())->title->toBe('Neu')->category->toBe('financials')->description->toBe('Geld.');
});

it('writes nothing and shows no toast when a request is saved unchanged', function () {
    $project = Project::factory()->create();
    $request = ProjectRequest::factory()->for($project)->create();
    Project::whereKey($project->id)->update(['updated_at' => now()->subDays(3)]);

    requestsView($project)->call('editRequest', $request->id)->call('saveRequest')
        ->assertSet('requestDialogOpen', false)
        ->assertNotDispatched('toast');

    expect($request->fresh()->updated_at->equalTo($request->updated_at))->toBeTrue()
        ->and($project->fresh()->updated_at->isToday())->toBeFalse();
});

it('deletes a request with the historical success toast', function () {
    $project = Project::factory()->create();
    $request = ProjectRequest::factory()->for($project)->create(['title' => 'Weg damit']);
    $kept = ProjectRequest::factory()->for($project)->create(['title' => 'Bleibt']);

    requestsView($project)->call('deleteRequest', $request->id)
        ->assertDispatched('toast', type: 'success', message: 'Gesuch wurde gelöscht.')
        ->assertDontSee('Weg damit')
        ->assertSee('Bleibt');

    expect($project->requests()->pluck('id')->all())->toBe([$kept->id]);
});

it('asks the historical native confirmation before deleting, in the menu item', function () {
    $project = Project::factory()->create();
    ProjectRequest::factory()->for($project)->create();

    requestsView($project)->assertSee('Möchtest Du das Gesuch wirklich löschen?', escape: false)->assertSee('Wird gelöscht...', escape: false);
});

it('returns to the empty state after the last request is deleted', function () {
    $project = Project::factory()->create();
    $request = ProjectRequest::factory()->for($project)->create();

    requestsView($project)->call('deleteRequest', $request->id)->assertSee('Alles zopfig! Derzeit gibt es keine Gesuche.');
});

it('deletes the requests with the project from the settings view', function () {
    $project = Project::factory()->create();
    ProjectRequest::factory()->count(2)->for($project)->create();

    Livewire::actingAs($project->user)->test(ProjectEdit::class, ['project' => $project])
        ->call('selectView', 'Einstellungen')->call('deleteProject');

    expect(ProjectRequest::count())->toBe(0);
});

it('discards an open dialog when the view is switched', function () {
    requestsView(Project::factory()->create())->call('openRequestDialog')->call('selectView', 'Beschreibung')->assertSet('requestDialogOpen', false);
});

// --- Authorization ---------------------------------------------------------------

it('404s the edit screen, and so the requests view, for a non-owner of a public project', function () {
    $project = Project::factory()->public()->create();
    ProjectRequest::factory()->for($project)->create();

    $this->actingAs(User::factory()->create())->get(route('projects.edit', $project))->assertNotFound();
});

it('never reaches a request of another project through the dialog, the id being client-supplied', function () {
    $project = Project::factory()->create();
    $foreign = ProjectRequest::factory()->create(['title' => 'Fremdes Gesuch']);

    requestsView($project)->call('editRequest', $foreign->id)
        ->assertSet('requestDialogOpen', false)
        ->assertSet('requestTitle', '')
        ->assertDontSee('Fremdes Gesuch');
});

it('does not update another project\'s request even with a forged request key', function () {
    $project = Project::factory()->create();
    $foreign = ProjectRequest::factory()->create(['title' => 'Fremdes Gesuch']);

    $view = requestsView($project)->call('openRequestDialog')->set('requestKey', $foreign->id);
    editDialogInput($view, 'Übernommen', 'others', 'Text')->assertNotFound();

    expect($foreign->fresh()->title)->toBe('Fremdes Gesuch');
});

it('does not delete another project\'s request', function () {
    $project = Project::factory()->create();
    $foreign = ProjectRequest::factory()->create();

    requestsView($project)->call('deleteRequest', $foreign->id)->assertNotFound();

    expect(ProjectRequest::whereKey($foreign->id)->exists())->toBeTrue();
});

it('treats a malformed or unknown request id as not found', function (string $key) {
    $project = Project::factory()->create();
    ProjectRequest::factory()->for($project)->create();

    requestsView($project)->call('editRequest', $key)->assertSet('requestDialogOpen', false);
    requestsView($project)->call('deleteRequest', $key)->assertNotFound();

    expect($project->requests()->count())->toBe(1);
})->with(['not-a-uuid', '0', "1' OR '1'='1", Str::uuid7()->toString()]);

it('lets a stranger who reaches the component in some other way do nothing', function () {
    $project = Project::factory()->public()->create();
    $request = ProjectRequest::factory()->for($project)->create();

    // The Livewire component is only mounted for the owner; a forged mount for someone else 404s.
    Livewire::actingAs(User::factory()->create())->test(ProjectEdit::class, ['project' => $project])->assertNotFound();

    expect($request->fresh())->not->toBeNull();
});
