<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectRequest;
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

        $this->info('Search index rebuilt'.(config('scout.queue') ? ' (documents are indexed by the queue worker; give it a moment).' : '.'));

        return self::SUCCESS;
    }
}
