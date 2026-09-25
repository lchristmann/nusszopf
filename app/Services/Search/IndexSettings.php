<?php

namespace App\Services\Search;

use App\Models\Project;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

/**
 * Whether the live `items` index is the one `config/scout.php` describes: it exists and carries every configured
 * setting. Without them search still answers, but wrongly — the category filter fails, the ranking loses its
 * tie-breaks and hits are capped at 1,000 — so a lost or recreated index is only noticed here
 * (docs/release/parity/P-11-search-recovery.md, finding P11-02).
 *
 * Used by the health check (`search`) and by `search:reindex`, which must not report success while the settings are
 * missing.
 */
class IndexSettings
{
    /** Settings Meilisearch returns as a set, sorted: their order in the configuration means nothing. */
    private const UNORDERED = ['filterableAttributes', 'sortableAttributes'];

    public function __construct(private readonly Client $client) {}

    public static function make(): self
    {
        return new self(new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key')));
    }

    /**
     * What is wrong with the live index, in words for the operator; empty when it is as configured.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $index = Project::searchIndexName();

        try {
            $actual = $this->client->index($index)->getSettings();
        } catch (ApiException $e) {
            if ($e->httpStatus === 404) {
                return ["the index `{$index}` does not exist"];
            }

            throw $e;
        }

        $problems = [];
        foreach ($this->configured() as $name => $expected) {
            $live = $actual[$name] ?? null;

            if (is_array($expected) && array_is_list($expected) && in_array($name, self::UNORDERED, true)) {
                sort($expected);
                $live = is_array($live) ? $live : [];
                sort($live);
            } elseif (is_array($expected) && ! array_is_list($expected)) {
                $live = array_intersect_key(is_array($live) ? $live : [], $expected);
            }

            if ($live != $expected) {
                $problems[] = "`{$name}` of `{$index}` is not the configured one";
            }
        }

        return $problems;
    }

    /**
     * @return array<string, mixed>
     */
    private function configured(): array
    {
        return config('scout.meilisearch.index-settings')[Project::class] ?? [];
    }
}
