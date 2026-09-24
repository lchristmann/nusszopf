# P-7 Production Compose verification (2026-09-24)

Exit evidence (`master-roadmap.md` §4): the whole Playwright suite also runs against the production images, which until
now CI had only built. The maintainer added the following checks:

- a clean start from the documented configuration;
- the critical journeys;
- health and readiness;
- the queue and scheduler in the production topology;
- search;
- the mail wiring, without a real relay;
- security headers, debug and version behaviour;
- a re-measurement of P-6's search round trip and per-card rendering on the production images;
- the standalone, image-only path.

The fresh install by a new person (P-8), upgrades (P-9), backup/restore (P-10), real SMTP (P-13) and the release
candidate (P-16) are out of scope.

## Topologies tested

All three run the production images built from this working copy by `docker/php/Dockerfile`:
- `ghcr.io/lchristmann/nusszopf-web`: nginx with the built assets;
- `ghcr.io/lchristmann/nusszopf-php-fpm`: PHP 8.5-FPM, `composer --no-dev`, no Xdebug, no `.env` baked in.

Each stack consists of `web`, `php-fpm`, `queue-worker`, `scheduler`, `postgres:16-alpine`, `redis:alpine` and
`getmeili/meilisearch:v1.11`, with `APP_ENV=production` and `APP_DEBUG=false`.

| # | What | Files | How |
|---|---|---|---|
| A | The operator checks | `docker-compose.yaml` + `compose.prod.yaml` (build) | `sh scripts/smoke-test.sh` |
| B | The whole browser suite, the drills, the measurements | A + `tests/E2E/production/compose.e2e.yaml` | `sh scripts/prod-e2e.sh` (new) |
| C | The standalone operator path | `docker-compose.yaml` alone, no build, no test doubles | `install.sh`, then a plain `docker compose up -d`, as in `docs/deployment/README.md` |

- **B's test doubles.** B adds only test doubles: Mailpit as the SMTP relay (`MAIL_HOST=mailpit`, port 1025) and the
  LocationIQ stub from `compose.dev.yaml`. Meilisearch is also published on the host for the index-recovery spec.
- **B's test-only settings,** appended to the `.env` that `install.sh` wrote:
  - the two test doubles;
  - `NUSSZOPF_REGISTER_LIMIT=10000`, as in `compose.dev.yaml`;
  - `SEARCH_PAGE_SIZE=5` for the paging spec (removed for the measurements).
- **Where Playwright runs.** Playwright runs from the pinned image (`v1.63.0-noble`) on the host network, so the
  browser's origin is `APP_URL` (`http://127.0.0.1:<port>`). That matters because production builds every link from
  `APP_URL` (P-4, SEC-01).
- **C's images.** C used images tagged locally, because no release tag exists on GHCR yet. Pulling from GHCR is part
  of P-16.
- **No TLS.** Every stack is plain http on the loopback interface. `install.sh` sets `SESSION_SECURE_COOKIE=false` for
  an `http://` address, as documented. No reverse proxy or TLS was in front of any of them.

## Results

| Check | Topology | Result |
|---|---|---|
| Smoke test (install, build, `--wait` for every healthcheck, version, pages, assets, legal pages, avatars through the read-only mount, migrations, caches, 404 without debug output, security headers, no version disclosure, links on `APP_URL` whatever the `Host`, `/health` with and without the token, `search:reindex`) | A | **passed**, including the new P7-01 step |
| Playwright, desktop projects (`chromium`, `firefox`, `webkit`) | B | **171 passed, 0 failed, 6 skipped** |
| Playwright, device projects (`mobile-safari`, `mobile-chrome`, `tablet-safari`) | B | **110 passed, 0 failed, 2 skipped** |
| Critical journeys with no test doubles and the default limits: register, log out, log in; My Projects publish/hide/delete; the request lifecycle (wizard, project page, edit screen, search), including the phone variant | C | **5 of 5 passed** (Chromium) |
| Clean start from the documented configuration | C | `docker compose up -d` returned after 12 s; every service `healthy` about a minute later; `nusszopf:health` all `ok`, version `e2e` |

About the Playwright runs:
- **Skips.** The skips are by design: the axe scan outside Chromium, and the avatar gesture test in WebKit. Unlike a
  local development run, the paging spec and the index-recovery spec ran here, because the script provides their
  settings.
- **The aria dump is excluded.** `zz-aria.spec.ts` only writes the accessibility tree of the visual reference dataset
  to files and asserts nothing. Production refuses to seed that dataset (`VisualReferenceSeeder`), so the script leaves
  it out.
- **After the suite:**
  - every container was `healthy`;
  - `nusszopf:health` reported all `ok`;
  - `schedule:list` showed the two heartbeats and `newsletter:purge-unconfirmed` at 03:30;
  - no application log had an error line.

  `failed_jobs` held 10 jobs, all the known P-12 item: welcome and verification mails for accounts the specs deleted
  before the worker sent them.

### Health and readiness (B)

| State | `/health` | With the token | Pages |
|---|---|---|---|
| All up | 200 `{"status":"ok"}` | version `e2e`, all five checks `ok` | 200 |
| Meilisearch stopped | 503 | only `search` failing | `/`, `/search`, `/login` 200 |
| PostgreSQL stopped | 503 | only `database` failing | project page 500: the Nusszopf error page, with no stack trace, SQL or framework path in the body |
| Either started again | 200 | all `ok` | 200, with nothing done by hand |

A wrong token gets only `{"status":"ok"}`. `/up` stays 200 throughout.

### Queue, scheduler and mail (B, and C without a relay)

- **Queue.** Search indexing and every mail ran through `queue-worker` during the suite. The specs that read Mailpit
  (contact, newsletter, password reset, lockout, Home sign-up) passed, so the mails were actually sent over SMTP.
- **Scheduler.** It ran `schedule:work`, and the two heartbeats kept `scheduler` and `queue-worker` healthy.
- **SMTP drill, recovery** (the drill `operations.md` still listed as not done):
  1. Mailpit stopped.
  2. A `WelcomeMail` queued with the same call registration makes.
  3. First attempt failed after 5 s.
  4. Mailpit started.
  5. The retry 10 s later delivered it: subject "Willkommen beim Nusszopf!", from `MAIL_FROM_ADDRESS`, to the
     account's address.

  `failed_jobs` stayed empty.
- **SMTP drill, exhaustion.**
  1. Mailpit stayed stopped.
  2. Five attempts (21:15:08, 21:15:25, 21:16:00, 21:17:05, 21:19:10: the 10/30/60/120 s backoff plus a 5 s SMTP
     timeout each).
  3. The job reached `failed_jobs` after 245 s.
  4. Mailpit started; `queue:retry all` delivered it; `failed_jobs` was empty again.
- **No relay configured** (C, `MAIL_HOST` empty as shipped). Registration succeeded, and each mail attempt failed in the
  worker log with the reason (`Connection could not be established with host ":587"`) and was retried. That is the
  documented behaviour.

### Search (A, B and C)

Every search spec passed on B, including the category filter, paging to the end, and recovery from a wiped index with
`search:reindex`. That was true only after the fix for P7-01.

### Security headers, debug and version (A and B)

- CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy` are on every page
  response, including the 404 and `/up`.
- The static files carry `nosniff` and `SAMEORIGIN`.
- `Server: nginx` names no version, and there is no `X-Powered-By`.
- Unknown pages return the Nusszopf 404, and a database failure the Nusszopf error page, both without debug output.
- The version appears only in the image labels, `nusszopf:health`, and `/health` with the token.
- The `csp.spec.ts` browser spec passed on all three desktop engines.

## Findings

| ID | Finding | Severity | State |
|---|---|---|---|
| P7-01 | A fresh production install never applied the search index settings: the category filter showed no hits, hits were capped at 1,000, the ranking tie-break was missing | High | Fixed |

### P7-01 — Index settings never applied in production (High)

- **Found:** the first run of the suite on B. `filters by request category` failed in all three engines: every filtered
  search showed "no hits". On the freshly started stack, `/indexes/items/settings/filterable-attributes` was `[]`.
- **Cause:** `config/scout.php` defines the index settings, which were versioned in response to BUG-008:
  - `req_type` filterable;
  - `maxTotalHits` 100,000;
  - the `updated_at:desc` ranking rule;
  - the searchable attributes.

  But only `search:reindex` and `scout:sync-index-settings` apply them, and nothing ran either in production:
  - the entrypoint, the install steps and the upgrade steps never call them;
  - the first indexed document created the index with Meilisearch's defaults;
  - a failed filter query looks like "no hits" (the historical behaviour).

  Development and CI never saw it, because their setup runs `search:reindex` or `scout:sync-index-settings` explicitly.
  The smoke test ran `search:reindex` as its last step, which hid it.
- **Impact on a real install:** until an operator happened to run `search:reindex`,
  - the search filter did not work;
  - "Mehr laden" stopped at 1,000 hits;
  - equal matches lost their "newest first" order.

  After an upgrade that changed the settings, the old settings would have stayed.
- **Fix:** `docker/php/entrypoint.sh` runs `php artisan scout:sync-index-settings` on every `php-fpm` start, after the
  caches. Unchanged settings are a no-op. If Meilisearch is unreachable it only warns and the site still starts, which
  fits `operations.md` ("pages work when search is down"); `search:reindex` applies the settings later. The comment in
  `docker-compose.yaml`, `docs/deployment/README.md` (the `php-fpm` row and installation step 2) and `operations.md`
  (upgrades) now say so.
- **Reconciled:** BUG-008 in `bugs.md` and its entry in `intentional-changes.md` had promised the settings "on deploy".
  Both now record the gap and the fix.
- **Regression tests:**
  - `scripts/smoke-test.sh` has a new step right after the stack is healthy and before anything reindexes. The index
    must already have `req_type` filterable, `maxTotalHits` 100,000 and `updated_at:desc`. The stack from the first run,
    before the fix, had `filterableAttributes: []`, which this step rejects.
  - The whole suite on B (CI job `production-e2e`) covers the filter itself.

## Performance on the production images

P-6 measured on the development stack (`APP_DEBUG=true`, Xdebug loaded, nothing cached). P-6's dataset seeder refuses
production and needs Faker, which the production image does not ship. So `PerformanceDatasetSeeder`'s data was
generated in development and copied into both databases, the production one and the development one, with
`pg_dump --data-only` (users, projects, project requests: 2,000 projects, 4,946 requests, 5,281 search documents).
Both stacks were then reindexed and measured with the same scripts, one after the other, on the same workstation. The
figures therefore compare production with development on identical data. They are not comparable one-to-one with P-6's
tables, which came from differently generated text.

**`ProjectSearch::search`** (Meilisearch plus the database), 25 runs each, median. Production runs with the default
page size of 50; the E2E setting was removed for this.

| Query | Hits (projects) | Production | Development |
|---|---|---|---|
| empty, page 1 | 17 | 8.3 ms | 8.2 ms |
| a common word | 18 | 9.2 ms | 9.2 ms |
| "Leipzig" / "Leipzg" | 17 | 9.0 / 8.9 ms | 9.1 / 9.3 ms |
| filter "materials" | 37 | 9.2 ms | 9.5 ms |
| empty, page 2 / 5 / 10 | 45 / 96 / 194 | 14.4 / 30.6 / 57.2 ms | 14.0 / 29.8 / 57.9 ms |

The same in both: the time goes to Meilisearch and PostgreSQL, not to PHP's debug settings.

**Rendering the results** (Livewire `Search` component, OPcache on in both as in PHP-FPM, median):

| Render | Production | Development |
|---|---|---|
| mount only (skeleton) | 3.5 ms | 4.2 ms |
| mount + first results (17 cards) | 29.4 ms | 29.9 ms |
| "Mehr laden" to page 2 (45 cards) | 45.2 ms, of which search 14.9 | 43.1 ms |
| to page 5 (96 cards) | 103.0 ms, of which search 29.2 | 99.6 ms |
| to page 10 (194 cards) | 203.7 ms, of which search 57.4 | 200.6 ms |

- **Per card:** a card costs **about 0.75–1 ms** to render, in production and in development alike.
- **Correction to P-6:** its "about 2 ms per card" came from a CLI benchmark with OPcache off. `P-06-performance.md`
  carries a note.

**In the browser** (Chromium, submit to Livewire response):

| Round trip | Production | Development |
|---|---|---|
| search submit, median of 9 | 126–168 ms | 202–291 ms |
| "Mehr laden" to page 10, one run | 680 ms | 675 ms |

- The response is gzipped in both: 12–14 KB on the wire for page 1, 86 KB for page 10 (1.2 MB raw).
- **Why the submit is faster in production:** the difference is development's per-request overhead (debug mode,
  uncached configuration and routes), not rendering.
- **Deep pages cost the same in both.** Of the 680 ms at page 10, about 200 ms is the server. The rest lies outside PHP
  (transfer of 1.2 MB raw, and the browser's work before and after the request) and was not broken down further.

**Page weight** (cold cache, bytes transferred):

| Page | Production | Development (P-6, after PERF-01) |
|---|---|---|
| Home | 239 KB | 301 KB |
| Search | 231 KB | 287 KB |
| Project page | 175 KB | 237 KB |
| Login | 179 KB | 240 KB |

Script is 104 KB on every one of these pages: minified Livewire (about 100 KB) plus `app.js` (4.6 KB). That confirms
P-6's estimate of about 60 KB less than development.

The P-6 recommendations stay open and non-blocking, as the maintainer decided. One more, from this phase:

5. **OPcache timestamp checks in production.** The image's `php.ini` is shared with development and keeps
   `opcache.validate_timestamps=1` (`revalidate_freq=0`), so PHP checks every file's timestamp on each request although
   the code in the image never changes. Turning it off in the production stage is conventional. It was not measured and
   not applied.

## Regression coverage added

- **`scripts/prod-e2e.sh` and `tests/E2E/production/compose.e2e.yaml`:** the whole suite on the production images.
  It exits non-zero if either pass fails.
- **The CI job `production-e2e`:** runs the script on every push and pull request, with 2 workers and no retries. It
  uploads the report on failure.
- **The P7-01 step in `scripts/smoke-test.sh`,** which runs in the existing CI job `production-stack`.

## Limitations

- **The CI job has not run yet.** `production-e2e` was verified locally, not on GitHub's runners. Its first run will
  show its duration: locally, the build plus both passes took about 15 minutes with 4 workers.
- **No TLS or reverse proxy** was in front of any stack. `SESSION_SECURE_COOKIE=true` behind https is untested here,
  and is P-8/P-16 territory.
- **Pulling from GHCR** was not tested, because no tag exists yet (P-16).
- **No real SMTP relay** was used: Mailpit stood in for it. Deliverability is P-13.
- **The measurements** come from one workstation, with the browser and the stack on the same host. They are not a load
  test (roadmap: none required).
- **The known P-12 item** (mails for accounts deleted before sending) reappeared: 10 jobs after the full suite. It is
  already carried to P-12.

## Status

**Done.** Awaiting the maintainer's review. The production images pass the whole Playwright suite, the operator path,
the drills and the header/debug/version checks. The one defect found, P7-01, is fixed with regression coverage. Nothing
needs a decision to close the phase; recommendation 5 is optional.
