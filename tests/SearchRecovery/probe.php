<?php

// Search-recovery drill (scripts/search-recovery-test.sh, P-11): records what search answers on an installation, so
// the answers before the index is destroyed and after it is recovered can be compared line by line.
//
//   docker compose exec -T php-fpm sh -c 'cat > /tmp/probe.php' < probe.php
//   docker compose exec -T php-fpm php /tmp/probe.php > probe.json
//
// Every query goes through the search page's own query side (App\Services\Search\ProjectSearch): the same limit,
// offset, filter expression and highlighting, and the same PostgreSQL visibility re-check of what is shown. For each
// query, filter and page count it records Meilisearch's raw hit order and estimated total, and what the page shows
// (the cards in order, with their request hits in order, and whether "Mehr laden" is offered).
//
// The privacy section is computed from PostgreSQL, not from the index: the documents that ought to exist, the
// private projects and their requests, and whether any of them is in the index or in any answer.

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Services\Search\ProjectSearch;
use Illuminate\Contracts\Console\Kernel;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$search = app(ProjectSearch::class);
$meili = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
$index = Project::searchIndexName();

$queries = [
    '' => 'everything (the page as it opens)',
    'Garten' => 'project title and description',
    'Nachbarschaft' => 'goal',
    'Werkzeug' => 'request description only',
    'Zürich' => 'place',
    'Berlin' => 'a place where every project is private (must find nothing)',
    'Motto' => 'motto',
    'p9user05' => 'author',
    'Gemeinschaftgarten' => 'typo (one letter missing)',
    'P9TOKEN010' => 'one project',
    'P9TOKEN001' => 'a private project (typo tolerance finds its public neighbours, never it)',
    'Suche P9TOKEN014-2' => 'one request title',
    'Zzqxwv' => 'nothing',
];
$filters = [
    'none checked' => [],
    'companions' => ['companions'],
    'rooms' => ['rooms'],
    'materials' => ['materials'],
    'financials' => ['financials'],
    'others' => ['others'],
    'none (no requests)' => ['none'],
    'rooms + none' => ['rooms', 'none'],
    'all checked' => ProjectSearch::CATEGORIES,
];

$pageSize = ProjectSearch::pageSize();
$answers = [];
foreach ($queries as $query => $what) {
    foreach ($filters as $label => $filter) {
        // Enough pages to reach the end of the biggest answer ("Mehr laden" until it disappears).
        for ($pages = 1; $pages <= 4; $pages++) {
            $key = json_encode([$query, $label, $pages], JSON_UNESCAPED_UNICODE);
            try {
                $raw = $search->fetch($query, $filter, $pages);
                $shown = $search->present($raw);
                $answers[$key] = [
                    'raw_ids' => array_map(fn (array $hit) => $hit['id'], $raw['hits'] ?? []),
                    'estimated_total' => $raw['estimatedTotalHits'] ?? null,
                    'cards' => array_map(fn ($hit) => [
                        'project' => $hit->project->id,
                        'title' => $hit->project->title,
                        'requests' => array_map(fn ($request) => $request->id, $hit->requests),
                    ], $shown->hits),
                    'load_more' => $shown->hasMore,
                ];
            } catch (Throwable $e) {
                $answers[$key] = ['error' => class_basename($e).': '.$e->getMessage()];
            }
            // The page shows what ProjectSearch::search() returns; record its failure flag too (the "Verzopft…" state).
            $answers[$key]['page_failed'] = $search->search($query, $filter, $pages)->failed;
            if (! ($answers[$key]['load_more'] ?? false)) {
                break;
            }
        }
    }
}

// What the index ought to hold, from PostgreSQL: a public project without requests has its own document, a public
// project with requests one per request, nothing private (docs/search/README.md, "Third slice").
$expected = [];
foreach (Project::query()->where('visibility', 'public')->with('requests')->get() as $project) {
    foreach ($project->requests->isEmpty() ? [$project->id] : $project->requests->pluck('id') as $id) {
        $expected[] = (string) $id;
    }
}
sort($expected);
$private = Project::query()->where('visibility', 'private')->pluck('id')->map(fn ($id) => (string) $id);
$privateRequests = ProjectRequest::query()->whereIn('project_id', $private)->pluck('id')->map(fn ($id) => (string) $id);
$forbidden = $private->merge($privateRequests)->flip();

try {
    $documents = $meili->index($index)->getDocuments((new DocumentsQuery)->setLimit(100000)->setFields(['id', 'group_id']))->getResults();
    $indexed = array_map(fn (array $d) => (string) $d['id'], $documents);
    sort($indexed);
    $indexError = null;
} catch (Throwable $e) {
    $indexed = [];
    $indexError = class_basename($e).': '.$e->getMessage();
}

$leaks = array_values(array_filter($indexed, fn ($id) => $forbidden->has($id)));
foreach ($answers as $key => $answer) {
    foreach ([...($answer['raw_ids'] ?? []), ...array_column($answer['cards'] ?? [], 'project'), ...array_merge([], ...array_column($answer['cards'] ?? [], 'requests'))] as $id) {
        if ($forbidden->has((string) $id)) {
            $leaks[] = "$key: $id";
        }
    }
}

try {
    $info = $meili->getIndex($index);
    $settings = $meili->index($index)->getSettings();
    $state = ['exists' => true, 'primary_key' => $info->getPrimaryKey(), 'documents' => $meili->index($index)->stats()['numberOfDocuments'], 'settings' => $settings];
} catch (Throwable $e) {
    $state = ['exists' => false, 'error' => class_basename($e).': '.$e->getMessage()];
}

ksort($state);
echo json_encode([
    'index' => $state,
    'all_indexes' => array_map(fn ($i) => $i->getUid(), $meili->getIndexes()->getResults()),
    'page_size' => $pageSize,
    'expected_documents' => count($expected),
    'index_matches_database' => $indexError === null && $indexed === $expected,
    'missing_from_index' => count(array_diff($expected, $indexed)),
    'not_belonging_in_index' => count(array_diff($indexed, $expected)),
    'private' => ['projects' => $private->count(), 'requests' => $privateRequests->count(), 'leaks' => $leaks],
    'answers' => $answers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
