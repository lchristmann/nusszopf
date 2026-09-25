<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Services\Search\IndexSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * The documented search recovery path (docs/deployment/operations.md): the
 * index is derived data, so an empty, stale or wrongly configured one is
 * rebuilt from PostgreSQL. Historically no such operation existed
 * (docs/search/README.md, "Indexing triggers"); BUG-008 asks for reproducible
 * index settings, this command is what applies them together with the data.
 *
 * Idempotent: settings are re-applied, every document is dropped, then every
 * public project (or its requests) is imported again, so running it twice — or
 * on an index that drifted — always ends in the same state as live syncing.
 *
 * Meilisearch applies settings asynchronously, and `scout:sync-index-settings`
 * exits successfully even when it could not send them, so the command checks
 * the live index against the configuration before it reports success
 * (docs/release/parity/P-11-search-recovery.md, finding P11-02).
 */
#[Signature('search:reindex')]
#[Description('Rebuild the search index from the database: apply the index settings, drop every document and import all public projects and requests')]
class ReindexSearch extends Command
{
    public function handle(): int
    {
        // `scout:flush` on a Project flushes the whole shared `items` index, and
        // requires it to exist; applying the settings creates it when missing.
        $steps = [
            ['scout:sync-index-settings', []],
            ['scout:flush', ['model' => Project::class]],
            ['scout:import', ['model' => Project::class]],
            ['scout:import', ['model' => ProjectRequest::class]],
        ];

        foreach ($steps as [$command, $arguments]) {
            try {
                $succeeded = $this->call($command, $arguments) === self::SUCCESS;
                $reason = 'it reported an error';
            } catch (Throwable $e) {
                $succeeded = false;
                $reason = $e->getMessage();
            }

            if (! $succeeded) {
                $this->error("`{$command}` failed ({$reason}); the search index is incomplete. Fix the cause and run search:reindex again.");

                return self::FAILURE;
            }
        }

        $problems = $this->awaitSettings();

        if ($problems !== []) {
            $this->error('The index settings were not applied ('.implode('; ', $problems).'); the search index is incomplete. Fix the cause and run search:reindex again.');

            return self::FAILURE;
        }

        $this->info('Index settings applied.');
        $this->info('Search index rebuilt'.(config('scout.queue') ? ' (documents are indexed by the queue worker; give it a moment).' : '.'));

        return self::SUCCESS;
    }

    /**
     * @return list<string> what is still wrong with the index settings after waiting for Meilisearch
     */
    private function awaitSettings(): array
    {
        $settings = IndexSettings::make();

        for ($attempt = 0; ; $attempt++) {
            try {
                $problems = $settings->problems();
            } catch (Throwable $e) {
                $problems = [$e->getMessage()];
            }

            if ($problems === [] || $attempt >= (int) config('search.settings_wait_attempts')) {
                return $problems;
            }

            usleep(250_000);
        }
    }
}
