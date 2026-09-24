<?php

use App\Livewire\Projects\MyProjects;
use App\Livewire\Projects\ProjectEdit;
use App\Livewire\Search\Search;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Services\Search\ProjectSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * P-6 (docs/release/parity/P-06-performance.md): no list screen issues a query per item. Each screen is rendered with
 * one item and with twenty, and must issue the same number of queries both times. This checks that the count stays
 * flat; it sets no budget.
 */
function countQueries(callable $action): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $action();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * An owner with `$n` public projects of three requests each; the first project also has `$n` more requests. Search
 * answers with every one of them, as Meilisearch would (the engine itself is not needed to count database queries).
 *
 * @return array{User, Project}
 */
function listScreensWith(int $n): array
{
    $owner = User::factory()->create();
    $projects = Project::factory()->for($owner)->public()->count($n)->create();
    $projects->each(fn (Project $project) => ProjectRequest::factory()->for($project)->count(3)->create());
    ProjectRequest::factory()->for($projects->first())->count($n)->create();

    $documents = [];
    foreach ($projects as $project) {
        $documents[] = ['id' => $project->id, 'group_id' => $project->id, 'req_type' => 'none', 'title' => $project->title, 'goal' => $project->goal,
            '_formatted' => ['title' => $project->title, 'goal' => $project->goal]];
        foreach ($project->requests as $request) {
            $documents[] = ['id' => $request->id, 'group_id' => $project->id, 'req_type' => $request->category, 'req_title' => $request->title,
                '_formatted' => ['req_title' => $request->title, 'req_description' => $request->description]];
        }
    }
    app()->instance(ProjectSearch::class, new class($documents) extends ProjectSearch
    {
        /** @param  list<array<string, mixed>>  $documents */
        public function __construct(private readonly array $documents) {}

        public function fetch(string $query, array $categories, int $pages): array
        {
            return ['hits' => $this->documents, 'estimatedTotalHits' => count($this->documents)];
        }
    });

    return [$owner, $projects->first()];
}

/**
 * @return array<string, int>
 */
function queriesPerScreen(int $n): array
{
    [$owner, $project] = listScreensWith($n);

    return [
        'my projects' => countQueries(fn () => Livewire::actingAs($owner)->test(MyProjects::class)->call('load')->assertSee($project->title)),
        'search' => countQueries(fn () => Livewire::test(Search::class)->call('load')->assertSee($project->title)),
        'project page' => countQueries(fn () => test()->get(route('projects.show', $project))->assertOk()),
        'edit: requests' => countQueries(fn () => Livewire::actingAs($owner)->test(ProjectEdit::class, ['project' => $project])
            ->call('load')->call('selectView', 'Gesuche')),
        'sitemap' => countQueries(fn () => test()->get(route('sitemap'))->assertOk()),
    ];
}

it('issues as many queries for twenty items as for one on every list screen', function () {
    $one = queriesPerScreen(1);
    $twenty = queriesPerScreen(20);

    expect($twenty)->toBe($one);
});
