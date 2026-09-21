<?php

namespace App\Services\Search;

/**
 * One matching request inside a hit card (HitRequestCard.js); the texts are
 * escaped HTML with the matches wrapped in `<em>`.
 */
final readonly class RequestHit
{
    public function __construct(
        public string $id,
        public string $category,
        public string $titleHtml,
        public string $descriptionHtml,
    ) {}
}
