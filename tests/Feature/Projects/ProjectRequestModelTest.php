<?php

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The `ProjectRequest` model and its table (docs/rewrite/third-slice.md,
 * docs/domain/entities.md `Request`): the relationship, the cascade, the
 * category constraint and the project-touching side effect.
 */
it('belongs to a project, which lists its requests', function () {
    $project = Project::factory()->create();
    $request = ProjectRequest::factory()->for($project)->create();

    expect($request->project->is($project))->toBeTrue()
        ->and($project->requests->pluck('id')->all())->toBe([$request->id]);
});

it('is deleted with its project, with the owner, and only then', function () {
    $owner = User::factory()->create();
    $kept = Project::factory()->create();
    ProjectRequest::factory()->for($kept)->create();
    $deleted = Project::factory()->for($owner)->create();
    ProjectRequest::factory()->count(2)->for($deleted)->create();

    $deleted->delete();

    expect(ProjectRequest::count())->toBe(1);

    $ownerProject = Project::factory()->for($owner)->create();
    ProjectRequest::factory()->for($ownerProject)->create();
    $owner->delete();

    expect(ProjectRequest::count())->toBe(1);
});

it('rejects a category outside the five real ones at the database layer', function (string $category) {
    $project = Project::factory()->create();

    DB::table('project_requests')->insert([
        'id' => (string) Str::uuid7(),
        'project_id' => $project->id,
        'title' => 'Titel',
        'category' => $category,
        'description' => 'Beschreibung',
        'description_template' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->with(['none', '', 'Companions', 'other'])->throws(QueryException::class);

it('accepts every historical category', function (string $category) {
    $request = ProjectRequest::factory()->category($category)->create();

    expect($request->fresh()->category)->toBe($category);
})->with(ProjectRequest::CATEGORIES);

it('labels the categories as the request form does', function () {
    expect(ProjectRequest::CATEGORY_LABELS)->toBe([
        'companions' => 'Mitstreiter:innen',
        'rooms' => 'Räume',
        'materials' => 'Materialien',
        'financials' => 'Finanzielles',
        'others' => 'Sonstiges',
    ]);
});

it('bumps the updated_at of a public project whenever a request is created, edited or deleted', function () {
    $project = Project::factory()->public()->create();
    $stale = fn () => Project::whereKey($project->id)->update(['updated_at' => now()->subDays(3)]);

    $stale();
    $request = ProjectRequest::factory()->for($project)->create();
    expect($project->fresh()->updated_at->isToday())->toBeTrue();

    $stale();
    $request->update(['title' => 'Neuer Titel']);
    expect($project->fresh()->updated_at->isToday())->toBeTrue();

    $stale();
    $request->delete();
    expect($project->fresh()->updated_at->isToday())->toBeTrue();
});

it('leaves the updated_at of a private project alone, like the historical indexer', function () {
    $project = Project::factory()->private()->create();
    Project::whereKey($project->id)->update(['updated_at' => now()->subDays(3)]);

    $request = ProjectRequest::factory()->for($project)->create();
    $request->update(['title' => 'Neuer Titel']);
    $request->delete();

    expect($project->fresh()->updated_at->isToday())->toBeFalse();
});

it('cannot be moved to another project by mass assignment', function () {
    $request = ProjectRequest::factory()->create();
    $other = Project::factory()->create();

    $request->update(['title' => 'Neuer Titel', 'project_id' => $other->id]);

    expect($request->fresh()->project_id)->not->toBe($other->id)->and($request->fresh()->title)->toBe('Neuer Titel');
});
