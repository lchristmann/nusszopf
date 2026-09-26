# P-12 Queue and scheduler verification (2026-09-26)

Exit evidence (`master-roadmap.md` §4): "See O-2", that is, kill Meilisearch, SMTP and Redis during a write; jobs retry,
fail visibly and recover. O-2 (2026-09-22) established that. P-12 covers what is left: the production Compose
configuration under failure, restarts, the scheduler, operator visibility, and the case P-11 deferred, `search:reindex`
while the queue is failing.

The maintainer's scope:
- the queue worker with the production Compose configuration;
- failed jobs, retries, backoff and the final failure;
- recovery after Redis is unavailable;
- `search:reindex` with the queue unavailable or failing;
- the scheduler: execution, recovery, no duplicates and no lost runs across a restart;
- health and operator visibility;
- restarts of the containers;
- the operator documentation must match what was observed;
- no invented workloads or guarantees; reuse O-2 evidence wherever it already satisfies a criterion.

Real SMTP delivery (P-13), the release (P-16) and every later phase are not part of this one.

## Environment

The environment is the one of P-10 and P-11: one host with its own Docker daemon (`docker:28-dind`), started empty on the
workstation, and this working copy's release installed as an operator installs it (`install.sh`, the release's
`docker-compose.yaml`, `.env`), with a Mailpit in a `compose.override.yaml` as the relay. The images were built locally
and loaded into the host, standing in for the GHCR pull. The dataset is `tests/Upgrade/seed.php` (80 projects, 198
requests, 151 index documents). The queue worker and the scheduler run exactly the commands of `docker-compose.yaml`.

The drill is `sh scripts/queue-scheduler-test.sh` (about an hour with the build; `docs/testing/README.md`, "Queue and
scheduler drill"). Its complete run from a new host passed, and the numbers below are from that run. (Step 7's second half and
step 8 were also run on their own before that, on a kept host, while the script was being fixed; the acceptance run
is the one from scratch.)

## 1. O-2 and earlier evidence that was reused, not repeated

| Criterion | Existing evidence | Used how |
|---|---|---|
| A failed job is retried with backoff and ends in `failed_jobs` (BUG-009) | O-2 (2026-09-22), `operations.md`, "What happens when a dependency is down"; `SearchSyncFailureTest` and `ContactMailTest` | Reused for the policy. Only the index-job variant was measured again (step 5), because the worker's log timestamps were never recorded for it |
| A mail is retried when the relay is down, delivered by a retry once it is back, kept in `failed_jobs` after five attempts, and delivered by `queue:retry all` | P-7 (2026-09-24): retry recovery, exhaustion after 245 s, `queue:retry all` | **Reused and not rerun.** It is the same worker, policy and mailables; a second run would prove nothing new. The only new fact about mail is what happens to a mail for a deleted account (P12-03) |
| Redis down: pages 500, `/health` 503, the worker crash-loops and recovers, the queue is not lost | O-2 | Reused for the graceful case. **Step 4** covers what O-2 never did: a Redis that is killed, not stopped (P12-01), with the scheduler observed too |
| Meilisearch down: pages work, search shows no hits, `/health` 503 | O-2, P-7 (health table) | Reused |
| `search:reindex` against an unreachable Meilisearch fails visibly | P-11 (case d) | Reused: Meilisearch down stops the command at its first step, before anything is dropped |
| Heartbeats keep both containers healthy; `schedule:list` shows the three tasks | P-7 | Reused for the steady state; step 1 records it again as the baseline |

## 2. New drills

The nine steps of the script. Each begins from a healthy stack.

### 1. The configuration as shipped
`docker inspect` shows `queue:work --tries=5 --backoff=10,30,60,120 --max-time=3600` and `schedule:work`, both
`restart: unless-stopped`. `nusszopf:health` is all `ok`. Thirty mails queued through the application were all
received by Mailpit, each once, and `failed_jobs` was 0.

### 2. A mail for an account deleted before it is sent (the known item carried since P-3 and P-7)
A welcome mail queued, its account deleted, then the worker started. Before the fix the job failed at once with
`ModelNotFoundException` (58 ms, no retry) and stayed in `failed_jobs`. Now it is dropped: nothing sent, nothing kept.

### 3. Worker restarts with 300 mails waiting

| | Result |
|---|---|
| a. `docker compose restart queue-worker` after 29 mails | The worker finished its job (`Worker STOPPED Interrupted`), and all 300 arrived, each once |
| b. `queue:restart` (what `--max-time=3600` does hourly) | Exit code 0; Docker started the worker again (restarts 0 → 1); it worked the queue and was healthy |
| c. `kill -9` after 28 mails | 273 jobs waiting and 1 held by the dead worker. All 300 recipients received their mail. Mailpit held **301** messages: the held job came back after `retry_after` (90 s) and ran again. `failed_jobs`: 0 |

c is what "at least once" means: nothing is lost, and the one job in flight when a worker is killed may run twice. That is
inherent in a queue without idempotency keys, and it is documented rather than engineered away.

### 4. Redis

| | Result |
|---|---|
| a. Redis **as it was** (snapshots only), 30 mails queued right after a save, `kill -9`, start | **0 of 30 mails delivered**, nothing in `failed_jobs`. All 30 were silently lost (P12-01) |
| b. Redis as shipped now (`--appendonly yes`), the same | 30 of 30 delivered, each once |
| c. `docker compose restart redis` with 30 mails waiting | 30 of 30 delivered |
| d. Redis stopped for 60 s while the stack ran | `/health` 503. The worker crash-looped (10 restarts in the minute) and the scheduler kept running, logging `getaddrinfo for redis failed` once a minute. After the start, every check and container was healthy after 51 s with no command run. Five mails queued afterwards arrived |

### 5. A failing job, through every retry, into `failed_jobs`, and out again
Meilisearch stopped, one project edited (two index jobs), worker started. The worker's log:

| Attempt | Time | After the previous |
|---|---|---|
| 1 | 13:12:18 | – |
| 2 | 13:12:30 | 12 s (10 s backoff) |
| 3 | 13:13:00 | 30 s |
| 4 | 13:14:00 | 60 s |
| 5 | 13:16:00 | 120 s |

The queue was empty 223 s after the first attempt: 10 failed attempts for 2 jobs, five each, then both in
`failed_jobs`.
With Meilisearch back:
- `nusszopf:health`: `failed_jobs | FAILED | 2 failed jobs — see php artisan queue:failed; run queue:retry all …`, and
  `/health` 503 (P12-02). `queue-worker` stayed `healthy`: a failing job is not a dead worker.
- Nothing retried them by itself. `queue:retry all` ran them; the edit was searchable, `failed_jobs` was empty, and
  `/health` was 200 again.

### 6. `search:reindex` and the queue (the case P-11 deferred)

| | Situation | Result |
|---|---|---|
| a | The queue worker is not running | The command finished its steps and **waited 30 s**, then printed `Search index rebuilt, but 3 queue job(s) … still waiting after 30 s. … check that the queue worker is running (docker compose ps queue-worker)`, exit 0. The index held 0 of 151 documents. `nusszopf:health` still said `queue ok` (its heartbeat was 87 s old, within the three minutes). After the worker started: 151 documents, identical to the reference |
| b | Redis is down | `The queue cannot be reached (…); nothing was changed`, exit 1. The index still held 151 of 151 documents (P12-04) |
| c | Meilisearch is lost (volume removed) while the documents wait in the queue | The worker wrote them into a new, empty Meilisearch: 151 documents, but the index has no settings. `nusszopf:health` failed `search`, naming each setting. The documented `search:reindex` made the index identical to the reference again: documents and settings |

Before the fixes, a printed only `Search index rebuilt (documents are indexed by the queue worker; give it a moment)`
with no warning, and b dropped every document first and only then failed.

### 7. The scheduler
- **a. Restarted and killed at the turn of the minute**, four times (`docker compose restart` and `kill -9` + start,
  each started at :58/:59). The logs show 9 minutes, and 9 runs of each heartbeat: **none twice, none missing**.
- **b. The purge at 03:30 UTC through the scheduler itself.** A script sets the clock and runs `schedule:run` in the
  production image, as the container does every minute. At 03:29 the scheduler starts the two heartbeats. At 03:30 it
  also starts `newsletter:purge-unconfirmed`, which deleted the two 15-day-old unconfirmed subscriptions. The same
  minute run again deleted nothing.
- **c. Stopped for five minutes.** At 180 s `scheduler` fails ("last heartbeat 181 s ago"). At 300 s both `scheduler`
  and `queue` fail: the scheduler is what queues the queue's heartbeat, so a stopped scheduler makes a healthy worker
  look stopped (P12-06). `/health` was 503, `scheduler` `exited/unhealthy`, `queue-worker` `running/unhealthy`. Eighty-one
  seconds after the start, every check and container was healthy again, with nothing else done.

### 8. The worker stopped for four minutes
Three mails queued. After 156 s the queue check failed ("last heartbeat 181 s ago"; the scheduler stayed `ok`), and
`/health` was 503. The three mails and one heartbeat a minute waited in Redis (6 jobs). After the worker started they
were sent, exactly 3, and `/health` was 200 again without any other action.

### 9. The state at the end
Every container healthy; `nusszopf:health` all six checks `ok`; `failed_jobs` empty; the index's documents and settings
identical to the reference.

### Regression checks on the production images
- `sh scripts/smoke-test.sh` (the CI job "Production stack"): passed, including `/health` 200 with the new
  `failed_jobs` check and the `search:reindex` step.
- `PROD_E2E_WORKERS=1 sh scripts/prod-e2e.sh --project=chromium tests/E2E/specs/visitor/search.spec.ts`: 10 passed,
  including the spec that wipes the index and runs the documented `search:reindex`, which now printed
  `Search index rebuilt (the queue worker has written every document)`. The whole browser suite was not rerun; no
  screen, route or copy changed.
- The Pest suite (622 tests), Pint and Larastan are clean.

## 3. What the scheduler does not do

Nothing here is invented. It is what the drill showed:
- It has three tasks, all confirmed by `SchedulerTest`: the two heartbeats and the daily purge.
- **A run whose minute fell into an outage is not made up.** The heartbeats do not need it. The purge deletes by age, so
  its first run after an outage removes everything that expired meanwhile (`SchedulerTest`, "lets the next run purge
  what expired while the scheduler was down").
- **Two schedulers were not run at once.** The documented stack has one, and no scaling is promised. The purge would be
  idempotent (the same minute run twice, step 7b), and the heartbeats are overwrites.

## 4. Findings and fixes

None of these contradicts a Confirmed claim about historical behavior. All concern the rewrite's own operation, like
P9-01 and P11-02; the historical product had no queue, no scheduler and no health check. They complete the intent of
BUG-009 (no invisible loss of queued work), which `bugs.md` and `intentional-changes.md` now say.

| # | What happened | Kind | Resolution |
|---|---|---|---|
| P12-01 | **A Redis crash silently dropped every queued job since its last snapshot.** With the default persistence, 30 welcome mails queued right after a snapshot were all gone after `kill -9` and a restart: 0 delivered, nothing in `failed_jobs`, no log line. The queue carries every mail the application sends, including password resets and a visitor's contact message, and the visitor is told it was sent. A graceful restart is safe; a power loss or an out-of-memory kill is not | Defect (durability) | **Fixed.** `docker-compose.yaml`: `command: ["redis-server", "--appendonly", "yes"]`. The same kill now loses nothing (30 of 30, and the 300-mail backlog). The remaining window is about a second (`appendfsync everysec`, Redis's default). The side effect is once, on a stack coming from a build without it: Redis then starts empty, so its sessions and queued jobs are gone. No release exists yet, so no operator has such a stack. Documented in `operations.md` |
| P12-02 | **The health checks could not see a worker that fails every job.** The heartbeat is a job that succeeds; a worker with an unreachable mail relay stays `healthy` while every mail lands in `failed_jobs`, and `/health` stays 200. BUG-009 promised that a failure is visible, and a monitor on `/health` did not see it | Defect (operator visibility) | **Fixed.** A `failed_jobs` check in `HealthChecker`: it fails while the table holds a row, with the count and the two commands that clear it. The `queue-worker` container's health ignores it. `/health` stays `degraded` until the operator retries or forgets the jobs, on purpose: each row is an undelivered mail or a missed index update |
| P12-03 | **A mail for an account deleted before the worker sent it failed at once and stayed in `failed_jobs` for good.** The known item from P-3 and P-7: `ModelNotFoundException`, no retry, and an operator reading `failed_jobs` sees a failure that needs no action. Laravel 13 takes `deleteWhenMissingModels` from the job, not from the mailable, so the property on the mailable would not have helped | Defect (noise), decided here as the earlier notes asked | **Fixed by dropping the job.** There is nobody left to mail. `App\Mail\SendQueuedMailUnlessModelGone` is bound in place of `SendQueuedMailable`. Only a vanished model is dropped; a mail that fails for any other reason still retries and ends in `failed_jobs` (`QueuedMailTest` covers both) |
| P12-04 | **`search:reindex` dropped every document before it found out the queue was down.** With Redis down, the import failed after `scout:flush`, which left a working index empty. The command said "incomplete" and to run it again, and `nusszopf:health` said `search ok`, because it does not count documents | Defect (recovery tooling) | **Fixed.** Before it changes anything, the command asks the queue for its size; if that fails, it stops with `nothing was changed` |
| P12-05 | **`search:reindex` with the worker down reported success and left search empty.** It finished, printed "give it a moment", and exited 0; the index stayed at 0 documents. Health said `queue ok` for up to three minutes | Defect (recovery tooling) | **Fixed.** After the import, the command waits up to 30 s for the queue to empty and either says so, or warns how many jobs wait and to check `docker compose ps queue-worker`. It still exits 0: everything it does itself was done, and nothing needs to be run again |
| P12-06 | **A stopped scheduler was reported as a stopped worker.** The queue's heartbeat is queued by the scheduler, so the `queue` check failed with "the queue worker is not running" while the worker was fine, and the `queue-worker` container turned `unhealthy` | Misleading message and undocumented behavior | **Fixed.** The message now also names the scheduler; `operations.md` says that both fail and to look at the scheduler first (health section, dependency table, troubleshooting) |

**Regression coverage:**
- `tests/Feature/Mail/QueuedMailTest.php`: a welcome mail for a deleted account is dropped; the same mail for an existing
  account is sent; a mail for an existing account with an unreachable relay still reaches `failed_jobs`. The first fails
  without the binding.
- `tests/Feature/HealthTest.php`: `failed_jobs` fails `/health` with the count and the commands, and clears again; the
  container's `--only=queue` still passes; no such check with the sync queue.
- `tests/Feature/Search/ReindexSearchTest.php`: the working index is untouched when the queue cannot be reached; a
  warning when no worker writes the documents; success once the queue is empty. All three fail without the fix.
- `tests/Feature/SchedulerTest.php`: exactly three scheduled tasks with their expressions, UTC, due at 03:30 and every
  minute and at no other time, and the catch-up by age.
- `scripts/queue-scheduler-test.sh`: everything in section 2, run against the production images. Step 4a is the
  before/after proof for P12-01: it removes the append-only file again, shows the loss, and puts it back.

## 5. Documentation

The operator documentation now says what the drill showed, and nothing else:
- `docs/deployment/operations.md`: the health section (three minutes, the scheduler's effect on `queue`,
  `failed_jobs`); "Queue worker and scheduler" (which command for which failure, and what was verified: restarts,
  a crash, the deleted account, Redis, the scheduler); the dependency table (Redis crash, queue worker, scheduler);
  "Search index recovery" (the wait, the warning, the preflight, a Meilisearch lost while documents wait);
  troubleshooting.
- `docs/deployment/README.md`: the Redis service, the worker's command, the `failed_jobs` check, the volume.
- `docs/rewrite/bugs.md` and `intentional-changes.md`: BUG-009's completion.
- `docs/testing/README.md`, `docs/development/README.md`, `README-DEV.md`: the drill and how to run it.
- `docs/release/parity/README.md`: this phase.

## 6. Limitations and deferred checks

| Limitation | Where it goes |
|---|---|
| Real SMTP delivery. All mail went to a Mailpit; the retry policy against a real relay's failures (greylisting, throttling, timeouts) is not measured | P-13 |
| One host, local images | GHCR pull and arm64: P-16, as in P-8…P-11 |
| The worker's hourly `--max-time` exit was not waited for. `queue:restart` takes the same exit path (step 3b) | Not a gap: same mechanism |
| A queue delivers **at least once**: a `kill -9` while a worker is holding a job can deliver that one mail twice (301 of 300 in the drill) | Documented in `operations.md`. No fix is planned: it would mean idempotency keys for mail, which the application does not need |
| Redis can lose about a second of queued work in a crash (`appendfsync everysec`) | Documented. `always` would cost every write an fsync for a queue that carries a few jobs a minute |
| A stack started from a build without the append-only file starts with an empty Redis once (sessions, queued jobs) | Only pre-release builds. `scripts/upgrade-test.sh` from `4de0194` or `8c4a2eb` (P-9's stand-ins for N-1) would therefore show sessions and in-flight jobs lost; P-16's N-1 is a real tag and already has the file |
| Two schedulers or two workers at once were not run | The documented stack has one of each; no scaling is promised |
| The health check names failed jobs, not a stuck queue or a backlog: a live worker with 10,000 waiting jobs stays `ok` while its heartbeat gets through. A backlog delays the heartbeat, so a *very* long one shows as `queue` failing after three minutes | Not built. Nothing historical or documented needs a backlog alarm |
| A Redis restart with the AOF was measured for 30 and 300 jobs, not for a large queue | The queue carries a few jobs a minute; Redis loads its AOF at start and `redis-cli ping` reports `LOADING` until it has, so the container is not healthy until then |
| Dev stack: a fresh `composer install` failed because the named volumes `vendor-cache` and `node-modules-cache` are root-owned. `chown 1000:1000 /var/www/vendor /var/www/node_modules` (as root in `workspace`) fixed it, and `.env` needs `MEILISEARCH_KEY=nusszopf-dev-master-key-change-me` for the `meilisearch`-group tests | Onboarding, P-14 (`README-DEV.md`) |

## Changed files

- `docker-compose.yaml`: Redis `--appendonly yes` (P12-01).
- `app/Health/HealthChecker.php`, `app/Console/Commands/HealthCheck.php`: P12-02, P12-06.
- `app/Mail/SendQueuedMailUnlessModelGone.php` (new), `app/Providers/AppServiceProvider.php`: P12-03.
- `app/Console/Commands/ReindexSearch.php`, `config/search.php`: P12-04, P12-05.
- Tests: `tests/Feature/Mail/QueuedMailTest.php` and `tests/Feature/SchedulerTest.php` (new), `HealthTest.php` and
  `Search/ReindexSearchTest.php`; `tests/QueueScheduler/work.php` and `at.php` (new); `scripts/queue-scheduler-test.sh` (new).
- Docs: see section 5.

## Status

Evidence complete, awaiting the maintainer. The exit criterion ("kill Meilisearch, SMTP and Redis during a write; jobs
retry, fail visibly and recover") is met with the O-2 and P-7 evidence and the drills above; six findings, all fixed
with regression coverage. P-13 and later phases are not started.
