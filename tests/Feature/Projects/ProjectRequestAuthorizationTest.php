<?php

use App\Livewire\Projects\ProjectDetail;
use App\Livewire\Projects\ProjectEdit;
use App\Livewire\Projects\ProjectWizard;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * docs/security/authorization-matrix.md, "Request" rows, and BUG-002: a
 * request is only as visible as its project on every read path. Each ability
 * has an allow and a deny case, and the `visible()` scope agrees with the
 * policy for every kind of viewer.
 */
function requestOf(string $visibility, ?User $owner = null): ProjectRequest
{
    $project = Project::factory()->when($owner, fn ($factory) => $factory->for($owner))->create(['visibility' => $visibility]);

    return ProjectRequest::factory()->for($project)->create();
}

// --- view -------------------------------------------------------------------

it('lets anyone view a request of a public project', function () {
    $request = requestOf('public');

    expect(Gate::forUser(null)->allows('view', $request))->toBeTrue()
        ->and(User::factory()->create()->can('view', $request))->toBeTrue();
});

it('denies viewing a request of a private project to a guest and to a non-owner (BUG-002)', function () {
    $request = requestOf('private');

    expect(Gate::forUser(null)->allows('view', $request))->toBeFalse()
        ->and(User::factory()->create()->can('view', $request))->toBeFalse();
});

it('lets the owner view the requests of their own private project', function () {
    $owner = User::factory()->create();

    expect($owner->can('view', requestOf('private', $owner)))->toBeTrue();
});

it('applies the same rule as a query scope for a guest, a stranger and the owner (BUG-002)', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $public = requestOf('public');
    $private = requestOf('private', $owner);

    expect(ProjectRequest::visible(null)->pluck('id')->all())->toBe([$public->id])
        ->and(ProjectRequest::visible($stranger->id)->pluck('id')->all())->toBe([$public->id])
        ->and(ProjectRequest::visible($owner->id)->pluck('id')->sort()->values()->all())->toBe(collect([$public->id, $private->id])->sort()->values()->all());
});

it('takes the current user for the scope when it is called without one', function () {
    $owner = User::factory()->create();
    $private = requestOf('private', $owner);

    expect(ProjectRequest::visible()->count())->toBe(0);

    $this->actingAs($owner);

    expect(ProjectRequest::visible()->pluck('id')->all())->toBe([$private->id]);
});

it('follows the project when it turns private or public', function () {
    $request = requestOf('public');

    expect(ProjectRequest::visible(null)->count())->toBe(1);

    $request->project->update(['visibility' => 'private']);
    expect(ProjectRequest::visible(null)->count())->toBe(0);

    $request->project->update(['visibility' => 'public']);
    expect(ProjectRequest::visible(null)->count())->toBe(1);
});

// --- create -----------------------------------------------------------------

it('lets a user create a request only in their own project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    expect($owner->can('create', [ProjectRequest::class, $project]))->toBeTrue()
        ->and(User::factory()->create()->can('create', [ProjectRequest::class, $project]))->toBeFalse()
        ->and(Gate::forUser(null)->allows('create', [ProjectRequest::class, $project]))->toBeFalse();
});

it('denies creating a request in a public project the user does not own', function () {
    $project = Project::factory()->public()->create();

    expect(User::factory()->create()->can('create', [ProjectRequest::class, $project]))->toBeFalse();
});

// --- update / delete ---------------------------------------------------------

it('lets only the project owner update or delete a request, whatever the visibility', function (string $visibility, string $ability) {
    $owner = User::factory()->create();
    $request = requestOf($visibility, $owner);

    expect($owner->can($ability, $request))->toBeTrue()
        ->and(User::factory()->create()->can($ability, $request))->toBeFalse()
        ->and(Gate::forUser(null)->allows($ability, $request))->toBeFalse();
})->with(['public', 'private'])->with(['update', 'delete']);

// --- Every read path (BUG-002 audit, third-slice closure) ------------------------------

it('exposes no route that addresses a request on its own', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

    expect($routes->filter(fn (string $uri) => str_contains(strtolower($uri), 'request')))->toBeEmpty();
});

it('stops listing a public project\'s requests in an already open detail page once the project turns private', function () {
    $project = Project::factory()->public()->create();
    ProjectRequest::factory()->for($project)->create(['title' => 'Nur solange öffentlich']);

    $component = Livewire::test(ProjectDetail::class, ['project' => $project])->assertSee('Nur solange öffentlich');

    $project->update(['visibility' => 'private']);

    $component->call('$refresh')->assertDontSee('Nur solange öffentlich');
});

it('does not let the owner\'s edit screen reach a request of another public project either', function () {
    $project = Project::factory()->create();
    $foreign = ProjectRequest::factory()->for(Project::factory()->public())->create(['title' => 'Öffentliches Fremdes']);

    Livewire::actingAs($project->user)->test(ProjectEdit::class, ['project' => $project])->call('selectView', 'Gesuche')
        ->call('editRequest', $foreign->id)->assertSet('requestDialogOpen', false)->assertDontSee('Öffentliches Fremdes')
        ->call('deleteRequest', $foreign->id)->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

it('never lets the wizard read a request of any project: its list is form state only', function () {
    $foreign = ProjectRequest::factory()->create(['title' => 'Nicht im Wizard']);

    Livewire::actingAs(User::factory()->create())->withQueryParams(['step' => 0])->test(ProjectWizard::class)
        ->call('editRequest', $foreign->id)->call('editRequest', '0')
        ->assertSet('requestDialogOpen', false)->assertDontSee('Nicht im Wizard');
});
