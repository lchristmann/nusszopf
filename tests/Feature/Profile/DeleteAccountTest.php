<?php

use App\Livewire\Profile\Profile;
use App\Models\Project;
use App\Models\ProjectAnalytics;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\AvatarUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * docs/rewrite/open-questions.md, "Account deletion and orphaned external
 * state": `App\Support\AccountDeleter` deletes every owned `Project` one at
 * a time through Eloquent — not a raw DB cascade — specifically so the
 * search de-indexing `App\Models\Project::booted()` already wires up still
 * fires. These tests prove that by attaching a probe to `Project::deleting`.
 */
it('deletes the account, its projects, requests and analytics', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->public()->create();
    ProjectRequest::factory()->for($project)->create();
    $project->analytics()->create(['views' => 3]);

    Livewire::actingAs($user)->test(Profile::class)
        ->call('deleteAccount')
        // Not `route('home')`: it is itself a redirect to `/search`
        // (routes/web.php scaffolding), which would swallow the flashed
        // toast before anything renders it.
        ->assertRedirect(route('search'));

    expect(User::find($user->id))->toBeNull()
        ->and(Project::find($project->id))->toBeNull()
        ->and(ProjectRequest::where('project_id', $project->id)->count())->toBe(0)
        ->and(ProjectAnalytics::where('project_id', $project->id)->count())->toBe(0);
});

it('deletes every owned project through Eloquent, not a raw cascade, so it still de-indexes', function () {
    $user = User::factory()->create();
    $projects = Project::factory()->for($user)->public()->count(3)->create();

    $deleted = [];
    Project::deleting(function (Project $project) use (&$deleted) {
        $deleted[] = $project->id;
    });

    Livewire::actingAs($user)->test(Profile::class)->call('deleteAccount');

    expect($deleted)->toHaveCount(3)
        ->and(collect($deleted)->sort()->values()->all())
        ->toBe($projects->pluck('id')->sort()->values()->all());
});

it('removes the local avatar file but leaves nothing else in the way of a retry on failure', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    AvatarUploader::store($user, UploadedFile::fake()->image('avatar.jpg', 200, 200));
    $path = $user->fresh()->picture;

    Livewire::actingAs($user)->test(Profile::class)->call('deleteAccount');

    Storage::disk('public')->assertMissing($path);
});

it('logs the account out and never leaves it deletable by anyone else', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->delete($owner, $owner))->toBeTrue()
        ->and($policy->delete($stranger, $owner))->toBeFalse();

    Livewire::actingAs($owner)->test(Profile::class)->call('deleteAccount');

    $this->assertGuest();
});
