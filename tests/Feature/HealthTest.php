<?php

use App\Health\HealthChecker;
use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * docs/deployment/operations.md, "Health checks": /up is the liveness probe, /health the operator's
 * dependency view, `nusszopf:health` the same from the shell.
 */
function beat(string $key, int $secondsAgo = 0): void
{
    Cache::put($key, now()->timestamp - $secondsAgo);
}

it('answers 200 ok when the database, scheduler and queue worker are alive', function () {
    config(['queue.default' => 'database']);
    beat(HealthChecker::SCHEDULER_HEARTBEAT);
    beat(HealthChecker::QUEUE_HEARTBEAT);

    $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
});

it('answers 503 degraded when the scheduler has not beaten for too long', function () {
    config(['queue.default' => 'database']);
    beat(HealthChecker::SCHEDULER_HEARTBEAT, secondsAgo: 600);
    beat(HealthChecker::QUEUE_HEARTBEAT);

    $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'degraded']);
});

it('answers 503 until the first heartbeat has arrived', function () {
    $this->getJson('/health')->assertStatus(503);
});

it('skips the queue check when jobs run synchronously', function () {
    config(['queue.default' => 'sync']);

    expect(array_keys((new HealthChecker)->run()))->not->toContain('queue');
});

it('answers 503, not an error, when the database is unreachable', function () {
    DB::partialMock()->shouldReceive('select')->andThrow(new RuntimeException('connection refused'));

    $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'degraded']);
});

it('reveals the version and each check only to a caller holding the health token', function () {
    config(['nusszopf.health_token' => 'geheim', 'nusszopf.version' => '1.2.3', 'queue.default' => 'database']);
    beat(HealthChecker::SCHEDULER_HEARTBEAT);
    beat(HealthChecker::QUEUE_HEARTBEAT);

    $this->getJson('/health')->assertExactJson(['status' => 'ok']);
    $this->getJson('/health', ['Authorization' => 'Bearer falsch'])->assertExactJson(['status' => 'ok']);

    $this->getJson('/health', ['Authorization' => 'Bearer geheim'])
        ->assertOk()
        ->assertJsonPath('version', '1.2.3')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.scheduler.ok', true)
        ->assertJsonPath('checks.queue.ok', true);
});

it('never shows details without a configured token, even for an empty bearer', function () {
    config(['nusszopf.health_token' => null]);

    $this->getJson('/health', ['Authorization' => 'Bearer '])->assertJsonMissingPath('checks');
});

it('starts no session, so it still answers while the session store is down', function () {
    $this->getJson('/health')->assertHeaderMissing('Set-Cookie');
});

it('reports the version and fails the command while a check fails', function () {
    config(['nusszopf.version' => '9.9.9']);

    expect(Artisan::call('nusszopf:health'))->toBe(1)
        ->and(Artisan::output())->toContain('Nusszopf 9.9.9')->toContain('FAILED');

    beat(HealthChecker::SCHEDULER_HEARTBEAT);
    expect(Artisan::call('nusszopf:health', ['--only' => 'scheduler']))->toBe(0)
        ->and(Artisan::call('nusszopf:health', ['--only' => 'nothing']))->toBe(1);
});

it('heartbeats from the scheduler: one entry each, every minute', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('scheduler-heartbeat')->toContain('queue-heartbeat');
});

it('records a queue heartbeat when the job runs', function () {
    (new QueueHeartbeat)->handle();

    expect((int) Cache::get(HealthChecker::QUEUE_HEARTBEAT))->toBeGreaterThan(now()->timestamp - 5);
});

it('reports the release the image was built from', function () {
    expect(config('nusszopf.version'))->toBe('dev');
});

/**
 * P-12, finding P12-02: a worker that is alive but failing every job passes the queue heartbeat, so the jobs that
 * ran out of tries have their own check.
 */
it('fails while a job waits in failed_jobs, and says what to do', function () {
    config(['queue.default' => 'database']);
    beat(HealthChecker::SCHEDULER_HEARTBEAT);
    beat(HealthChecker::QUEUE_HEARTBEAT);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);

    $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'degraded']);

    config(['nusszopf.health_token' => 'geheim']);
    $this->getJson('/health', ['Authorization' => 'Bearer geheim'])
        ->assertStatus(503)
        ->assertJsonPath('checks.queue.ok', true)
        ->assertJsonPath('checks.failed_jobs.ok', false)
        ->assertJsonPath('checks.failed_jobs.detail', fn (string $detail) => str_contains($detail, '1 failed job') && str_contains($detail, 'queue:retry all') && str_contains($detail, 'queue:flush'));

    // The worker container's own check looks only at the heartbeat: a failed job must not make Docker call it unhealthy.
    expect(Artisan::call('nusszopf:health', ['--only' => 'queue']))->toBe(0)
        ->and(Artisan::call('nusszopf:health', ['--only' => 'failed_jobs']))->toBe(1);

    DB::table('failed_jobs')->delete();
    $this->getJson('/health')->assertOk();
});

it('has no failed_jobs check when jobs run synchronously', function () {
    config(['queue.default' => 'sync']);

    expect(array_keys((new HealthChecker)->run()))->not->toContain('failed_jobs');
});
