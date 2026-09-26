<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Services\Search\IndexSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
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
 *
 * With the queue in use (`SCOUT_QUEUE=true`, the production default) the documents are written by the queue worker,
 * so the queue is part of a rebuild (docs/release/parity/P-12-queue-scheduler.md): it is checked before the index is
 * emptied, because a queue that cannot take the import would leave a working index empty (P12-04), and the command
 * waits for the worker afterwards and says so when nothing is writing the documents (P12-05).
 */
#[Signature('search:reindex')]
#[Description('Rebuild the search index from the database: apply the index settings, drop every document and import all public projects and requests')]
class ReindexSearch extends Command
{
    public function handle(): int
    {
        if (($problem = $this->queueProblem()) !== null) {
            $this->error("The queue cannot be reached ({$problem}); nothing was changed. Fix the cause and run search:reindex again.");

            return self::FAILURE;
        }

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

        if (! $this->queued()) {
            $this->info('Search index rebuilt.');

            return self::SUCCESS;
        }

        $waiting = $this->awaitQueue();

        if ($waiting === 0) {
            $this->info('Search index rebuilt (the queue worker has written every document).');
        } else {
            $this->warn("Search index rebuilt, but {$waiting} queue job(s) that write the documents were still waiting after "
                .(int) (config('search.queue_wait_attempts') / 4).' s. Search shows nothing until they have run: check that the queue worker is running (docker compose ps queue-worker) and let it finish.');
        }

        return self::SUCCESS;
    }

    private function queued(): bool
    {
        return (bool) config('scout.queue');
    }

    /**
     * Scout's own settings: `true` means the default connection and queue, an array names them.
     */
    private function queueSize(): int
    {
        $queue = config('scout.queue');

        return Queue::connection(is_array($queue) ? ($queue['connection'] ?? null) : null)
            ->size(is_array($queue) ? ($queue['queue'] ?? null) : null);
    }

    /**
     * @return string|null why the queue cannot take the import, or null when it can (or is not used)
     */
    private function queueProblem(): ?string
    {
        if (! $this->queued()) {
            return null;
        }

        try {
            $this->queueSize();

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return int the jobs still waiting (or running) once the worker has had its time; 0 when it has written everything
     */
    private function awaitQueue(): int
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                $waiting = $this->queueSize();
            } catch (Throwable) {
                $waiting = 1;
            }

            if ($waiting === 0 || $attempt >= (int) config('search.queue_wait_attempts')) {
                return $waiting;
            }

            usleep(250_000);
        }
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
