<?php

namespace App\Services\Search;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Support\SearchHighlight;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Meilisearch\Endpoints\Indexes;
use Throwable;

/**
 * The search page's query side (`search.service.js`): one Meilisearch query
 * over the shared `items` index, its documents grouped into their project.
 *
 * Only the index is asked what matches; what is *shown* is checked against
 * PostgreSQL again — a document of a project that has since become private or
 * been deleted, or of a request that no longer exists, never reaches the page
 * (BUG-002 defence in depth, docs/search/README.md).
 */
class ProjectSearch
{
    /** The filter's options — the request categories and "no requests" (`FilterPopover.js`). */
    public const CATEGORIES = [...ProjectRequest::CATEGORIES, 'none'];

    /** `MEILI_CONFIG.attributesToHighlight` */
    private const HIGHLIGHTED = ['title', 'goal', 'description', 'team', 'motto', 'location_text', 'author', 'req_title', 'req_description'];

    /** `OFFSET` in search.service.js (50): documents, not projects, per page. */
    public static function pageSize(): int
    {
        return max(1, (int) config('search.page_size'));
    }

    /**
     * @param  list<string>  $categories  the checked filter options; none or all checked filters nothing
     * @param  int  $pages  how many pages of {@see self::pageSize()} documents to show ("Mehr laden" adds one)
     */
    public function search(string $query, array $categories = [], int $pages = 1): SearchResults
    {
        try {
            return $this->present($this->fetch($query, $categories, $pages));
        } catch (Throwable $e) {
            // Historically a failed query showed the "nothing found" state (`search()` catch: `setHits()`);
            // it is reported here so an outage is at least visible to the operator.
            report($e);

            return new SearchResults([], false, failed: true);
        }
    }

    /**
     * The `_mapFilterQuery` of search.service.js: an OR over the checked options, none when nothing
     * or everything is checked. Unknown values are ignored.
     *
     * @param  list<string>  $categories
     */
    public static function filterExpression(array $categories): ?string
    {
        $checked = array_values(array_intersect(self::CATEGORIES, $categories));

        if ($checked === [] || count($checked) === count(self::CATEGORIES)) {
            return null;
        }

        return implode(' OR ', array_map(fn (string $category) => "req_type = {$category}", $checked));
    }

    /**
     * @param  list<string>  $categories
     * @return array<string, mixed> Meilisearch's raw response
     */
    public function fetch(string $query, array $categories, int $pages): array
    {
        $filter = self::filterExpression($categories);

        return Project::search($query, function (Indexes $index, string $query, array $options) use ($filter, $pages) {
            $options['limit'] = self::pageSize() * max(1, $pages);
            $options['offset'] = 0;
            $options['attributesToHighlight'] = self::HIGHLIGHTED;
            $options['highlightPreTag'] = SearchHighlight::OPEN;
            $options['highlightPostTag'] = SearchHighlight::CLOSE;

            if ($filter !== null) {
                $options['filter'] = $filter;
            }

            return $index->rawSearch($query, $options);
        })->raw();
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function present(array $raw): SearchResults
    {
        /** @var list<array<string, mixed>> $documents */
        $documents = $raw['hits'] ?? [];

        // Grouped by project, in the order each project first appears (lodash `groupBy` + `Object.entries`).
        $groups = collect($documents)->groupBy(fn (array $document) => (string) $document['group_id']);

        $projects = Project::visible(null)->whereIn('id', $groups->keys())->get()->keyBy('id');
        $requests = ProjectRequest::visible(null)
            ->whereIn('id', collect($documents)->where('req_type', '!=', 'none')->pluck('id'))
            ->pluck('project_id', 'id');

        $hits = [];
        foreach ($groups as $projectId => $group) {
            $project = $projects->get($projectId);

            if ($project === null) {
                continue;
            }

            $hits[] = $this->hit($project, array_values($group->all()), $requests);
        }

        $total = (int) ($raw['estimatedTotalHits'] ?? $raw['totalHits'] ?? $raw['nbHits'] ?? 0);

        return new SearchResults($hits, $total > count($documents));
    }

    /**
     * @param  list<array<string, mixed>>  $documents  the project's documents, best match first
     * @param  Collection<string, string>  $existingRequests  request id => project id
     */
    private function hit(Project $project, array $documents, $existingRequests): ProjectHit
    {
        $formatted = $documents[0]['_formatted'] ?? [];

        // HitCard.js: the description, place, team, motto and author of the best document, without
        // the empty ones, joined and cut at 90 characters.
        $info = collect(['description', 'location_text', 'team', 'motto', 'author'])
            ->map(fn (string $field) => (string) ($formatted[$field] ?? ''))
            ->filter(fn (string $value) => $value !== '')
            ->implode(' | ');

        $requests = [];
        foreach ($documents as $document) {
            $id = (string) $document['id'];

            if ($document['req_type'] === 'none' || $existingRequests->get($id) !== $project->id) {
                continue;
            }

            $requestFormatted = $document['_formatted'] ?? [];
            $description = (string) ($requestFormatted['req_description'] ?? '');

            $requests[] = new RequestHit(
                $id,
                (string) $document['req_type'],
                SearchHighlight::html((string) ($requestFormatted['req_title'] ?? $document['req_title'] ?? '')),
                $description === '' ? '' : SearchHighlight::html(SearchHighlight::truncate($description)),
            );
        }

        return new ProjectHit(
            $project,
            SearchHighlight::html((string) (Arr::get($formatted, 'title') ?: $project->title)),
            SearchHighlight::html((string) (Arr::get($formatted, 'goal') ?: $project->goal)),
            SearchHighlight::html(SearchHighlight::truncate($info)),
            $requests,
        );
    }
}
