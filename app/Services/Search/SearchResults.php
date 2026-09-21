<?php

namespace App\Services\Search;

/**
 * One page of a search, grouped: the project hits in relevance order, and
 * whether the index holds more documents than were fetched ("Mehr laden").
 */
final readonly class SearchResults
{
    /**
     * @param  list<ProjectHit>  $hits
     */
    public function __construct(
        public array $hits,
        public bool $hasMore,
        public bool $failed = false,
    ) {}
}
