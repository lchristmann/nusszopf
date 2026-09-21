<?php

namespace App\Health;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Meilisearch\Client as Meilisearch;
use Throwable;

/**
 * The dependency-by-dependency view of an installation (docs/rewrite/architecture-decisions.md,
 * "Health-check depth"): what an operator needs to answer "why is search broken" or "are my
 * background jobs running". `/up` (Laravel's own) stays the container liveness probe; this is
 * what /health and `php artisan nusszopf:health` report.
 *
 * A check that does not apply to the configuration (no Redis driver in use, search or queue not
 * configured) is left out rather than reported as passing.
 */
class HealthChecker
{
    public const SCHEDULER_HEARTBEAT = 'health:scheduler-heartbeat';

    public const QUEUE_HEARTBEAT = 'health:queue-heartbeat';

    /**
     * @return array<string, array{ok: bool, detail: string}>
     */
    public function run(): array
    {
        $checks = ['database' => $this->attempt(function () {
            DB::select('select 1');

            return 'reachable';
        })];

        if ($this->usesRedis()) {
            $checks['redis'] = $this->attempt(function () {
                Redis::connection()->ping();

                return 'reachable';
            });
        }

        if (config('scout.driver') === 'meilisearch') {
            $checks['search'] = $this->attempt(function () {
                $health = (new Meilisearch(config('scout.meilisearch.host'), config('scout.meilisearch.key')))->health();

                return $health['status'] === 'available' ? 'available' : throw new \RuntimeException("status {$health['status']}");
            });
        }

        $checks['scheduler'] = $this->heartbeat(self::SCHEDULER_HEARTBEAT, 'the scheduler is not running (php artisan schedule:work)');

        if (config('queue.default') !== 'sync') {
            $checks['queue'] = $this->heartbeat(self::QUEUE_HEARTBEAT, 'the queue worker is not running (php artisan queue:work)');
        }

        return $checks;
    }

    /**
     * @param  array<string, array{ok: bool}>  $checks
     */
    public function healthy(array $checks): bool
    {
        return collect($checks)->every(fn (array $check) => $check['ok']);
    }

    private function usesRedis(): bool
    {
        return in_array('redis', [config('cache.default'), config('session.driver'), config('queue.default')], true);
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function attempt(callable $check): array
    {
        try {
            return ['ok' => true, 'detail' => $check()];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function heartbeat(string $key, string $missing): array
    {
        return $this->attempt(function () use ($key, $missing) {
            $at = Cache::get($key);

            if ($at === null) {
                throw new \RuntimeException("no heartbeat yet — {$missing}");
            }

            $age = (int) Carbon::now()->timestamp - (int) $at;

            return $age <= config('nusszopf.heartbeat_max_age')
                ? "last heartbeat {$age}s ago"
                : throw new \RuntimeException("last heartbeat {$age}s ago — {$missing}");
        });
    }
}
