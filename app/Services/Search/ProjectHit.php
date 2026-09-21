<?php

namespace App\Services\Search;

use App\Models\Project;

/**
 * A project with the requests of it that matched (HitCard.js): its title and
 * goal, the one-line summary and the nested request hits. The texts are
 * escaped HTML with the matches wrapped in `<em>`.
 */
final readonly class ProjectHit
{
    /**
     * @param  list<RequestHit>  $requests
     */
    public function __construct(
        public Project $project,
        public string $titleHtml,
        public string $goalHtml,
        public string $infoHtml,
        public array $requests,
    ) {}
}
