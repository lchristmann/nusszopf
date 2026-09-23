# Operations

> **Status: health, logs, queue and scheduler, upgrades, rollback, search recovery and troubleshooting are implemented and verified
> (operational track O-1/O-2, 2026-09-22).** The backup and restore sections remain a documented proposal until the backup drill (P-10).
> Commands assume the operator's directory (`docker-compose.yaml` and `.env` next to each other); contributors add
> `-f compose.dev.yaml` and use the `workspace` service.

## Table of contents

- [Health checks](#health-checks)
- [Logs](#logs)
- [Queue worker and scheduler](#queue-worker-and-scheduler)
- [What happens when a dependency is down](#what-happens-when-a-dependency-is-down)
- [Search index recovery](#search-index-recovery)
- [Newsletter subscribers](#newsletter-subscribers)
- [Backups](#backups)
- [Restore](#restore)
- [Upgrades](#upgrades)
- [Rollback](#rollback)
- [Running one-off Artisan commands](#running-one-off-artisan-commands)
- [Troubleshooting](#troubleshooting)

## Health checks

```bash
docker compose ps                                        # every service "healthy"
docker compose exec php-fpm php artisan nusszopf:health  # running version + database, redis, search, scheduler, queue
curl -s https://nusszopf.example.org/health              # {"status":"ok"} (200) or {"status":"degraded"} (503) — point a monitor here
curl -s -H "Authorization: Bearer $HEALTH_TOKEN" https://nusszopf.example.org/health   # + version and each check's reason
```

`/up` is the container liveness probe and tests nothing behind the application. The scheduler and queue worker prove themselves with a heartbeat once a minute,
so a freshly started stack reports `degraded` for up to a minute — that is the check working, not a fault. Details: `docs/deployment/README.md`, "Health checks".

## Logs

`docker compose logs -f <service>` for any service (the application logs to stderr in production). No log aggregation is proposed for v1 — matches all three references, none of which run a log shipper.

## Queue worker and scheduler

The historical product had no scheduled work (its cron triggers were empty), so the scheduler runs the two heartbeats plus one task added with the newsletter (slice 9): `newsletter:purge-unconfirmed`, daily at 03:30, deletes newsletter subscriptions nobody confirmed within 14 days (decision A-1). New scheduled work is added only with the slice that needs it.
The queue carries search indexing and mail (`App\Mail\ContactMail` since the sixth slice, the newsletter mails since the ninth). The worker runs `queue:work --tries=5 --backoff=10,30,60,120 --max-time=3600`: a failing job is tried again after 10 s, 30 s, 1 min and 2 min,
then kept in the `failed_jobs` table — the historical webhooks gave up silently after three tries (BUG-009).

```bash
docker compose logs -f queue-worker                      # RUNNING / DONE / FAIL per job, errors with the reason
docker compose exec php-fpm php artisan queue:failed     # jobs that ran out of tries
docker compose exec php-fpm php artisan queue:retry all  # run them again (after fixing the cause)
docker compose exec php-fpm php artisan queue:flush      # forget them (search is rebuilt by search:reindex anyway)
docker compose restart queue-worker                      # after a bad deploy or a stuck job; it also restarts itself hourly (--max-time)
docker compose logs scheduler                            # the two heartbeats, once a minute
```

## What happens when a dependency is down

Verified on the production stack (2026-09-22) by stopping each service, working, and starting it again
(the two Google/OAuth rows are reasoned from the code instead — Google itself isn't a Compose service
to stop):

| Down | What the visitor sees | What the stack does | Recovery |
|---|---|---|---|
| **Meilisearch** | Pages work; search shows no hits ("Verzopft…" — the historical behavior for a failed query); a project saved meanwhile is not searchable yet | `/health` → 503 with `search` failing. Index jobs fail and retry at +10 s, +30 s, +1 min, +2 min; after five attempts (about 3½ minutes) the job lands in `failed_jobs` | Start Meilisearch. The retry that follows finds it; give it up to a minute (the worker's DNS cache). Jobs already in `failed_jobs`: `queue:retry all`, or simply `search:reindex` |
| **Redis** | Every page is a 500 (sessions live in Redis); `/up` stays 200 | `/health` → 503 (it needs no session, so it still answers); the queue worker crash-loops and Docker restarts it; nothing is lost that was queued before | Start Redis; everything resumes by itself, no manual step |
| **PostgreSQL** | Pages that read data fail; the search page shell still renders | `/health` → 503 with `database` failing; `php-fpm` stays "healthy" (its check is PHP-FPM's own ping) | Start PostgreSQL; resumes by itself |
| **SMTP** | Contact form, registration, "forgot password", a newsletter sign-up/unsubscribe request, a login lockout: the triggering action still succeeds (every mail is queued, never sent inline) | Every mailable (`App\Mail\ContactMail`/`WelcomeMail`/`ChangePasswordMail`/`VerifyEmailMail`/`BlockedAccountMail`, all `ShouldQueue`) rides the same queue and retry/backoff policy as search indexing; an unreachable mail server fails and retries the same way, landing in `failed_jobs` after five attempts. Verified at the Feature-test level (e.g. `tests/Feature/Mail/ContactMailTest.php`, "leaves a failed contact send in failed_jobs instead of losing it"), not yet with a live production-stack drill the way the three rows above were | `queue:retry all` once SMTP is reachable again |
| **Google (OAuth)** — no GOOGLE_CLIENT_ID/SECRET configured | The login screen simply has no Google button; password/username login and registration are entirely unaffected | Both `/auth/google/*` routes 404 | Set `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` and restart; not a dependency the app requires to function |
| **Google (OAuth)** — configured but unreachable/erroring mid-login | A generic "Sorry, da lief etwas schief." toast on the login screen; no account is created or modified | `GoogleController::callback()` catches the failure and redirects to `/login`, synchronously (this is a request-time auth exchange, not a background job — there is nothing to retry) | The visitor retries, or uses password/username login instead |

A saved change is never lost when search is down: the database is written first and the index job is retried; `search:reindex` repairs whatever still went wrong.

## Search index recovery

The search index (Meilisearch, one shared `items` index) is **derived data**: everything in it comes from PostgreSQL, so it is
never backed up, only rebuilt. One command does it, and it is safe to run at any time, as often as needed:

```bash
docker compose exec php-fpm php artisan search:reindex
```

It applies the versioned index settings (`config/scout.php`), drops every document and imports all public projects and their
requests again. With `SCOUT_QUEUE=true` (the default) the documents are indexed by the queue worker, so search fills up over the
next seconds to minutes depending on the number of projects; watch it with `docker compose logs -f queue-worker`. Private projects
and their requests are never imported.

Use it after: restoring a backup, wiping or replacing the `meilisearch-data` volume, upgrading to a release that changes the
index (see "Upgrades"), or when search results are missing or stale (for example after Meilisearch was down for longer than the
queue's retries). If the command names a step that failed, Meilisearch is unreachable or misconfigured — fix that and run it again.
Failed sync jobs stay visible in the `failed_jobs` table (`php artisan queue:failed`) and can be retried with `queue:retry all`;
reindexing makes them unnecessary.

## Newsletter subscribers

Nusszopf collects and confirms newsletter subscribers (double opt-in on every path) but does not send newsletter issues —
historically they were written and sent by hand from an external tool (decision A-6). To send one:

```bash
docker compose exec -T php-fpm php artisan newsletter:export > subscribers.csv       # confirmed subscribers only
docker compose exec php-fpm php artisan newsletter:export --output=/tmp/s.csv       # or into a file inside the container
```

Columns: `email`, `name`, `confirmed_at`, `requested_at`, `source` (`form`, `registration`, `profile`), `consent_version`.

- **Every issue you send must carry a link to `https://<your-host>/newsletter/unsubscribe/lead`.** Unsubscribing must happen
  in Nusszopf so its subscriber table stays the only list; export again right before each send instead of keeping a copy.
- `NEWSLETTER_CONSENT_VERSION` (`.env`) is stored with each subscription as part of its consent record. Change it whenever
  your Datenschutz text changes, so you can tell which text each subscriber agreed to. No IP address is stored.
- Unconfirmed subscriptions are deleted automatically 14 days after their latest request; run
  `php artisan newsletter:purge-unconfirmed` to do it by hand.
- Deleting an account deletes the subscription for the same address.
- The confirmation links in the mails are signed with `APP_KEY` and expire after 7 days — rotating `APP_KEY` invalidates
  every link still in someone's inbox (they can simply subscribe again).

## Backups

**(needs approval — which tier to adopt for v1)**. Two demonstrated tiers, both Confirmed from evidence:

**Tier 1 — Waffle Dashboard's baseline** (`docs/code/backup.sh` in that repo): a plain shell script run via host crontab.

```sh
#!/bin/sh
BACKUP_DIR="/opt/nusszopf-backups/$(date +%Y-%m-%d_%H-%M)"
mkdir -p "$BACKUP_DIR"
docker compose exec -T postgres pg_dump --format=custom -d "$DB_DATABASE" -U "$DB_USERNAME" > "$BACKUP_DIR/postgres.dump"
docker run --rm -v nusszopf_laravel-storage:/data -v "$BACKUP_DIR:/backup" ubuntu tar czf "/backup/storage-volume.tar.gz" /data
find /opt/nusszopf-backups -mindepth 1 -maxdepth 1 -type d -mtime +30 -exec rm -rf {} \;
```

Scheduled with a root crontab entry (`crontab -e`), e.g. weekly. This is the minimum viable, evidence-backed backup story and matches the "no unnecessary operational dependencies" principle in `.claude/rules/06-self-hosting.md`.

**Tier 2 — LCxHolz's maturity level** (`spatie/laravel-backup`): encrypted archives (AES-256, gated on a `BACKUP_ARCHIVE_PASSWORD` env var), tiered retention (all backups for 7 days, then daily/weekly/monthly/yearly thinning), a `BackupsCheck` health-check integration that flags a missing/stale backup, and email notification on failure. Requires a PostgreSQL-compatible dump driver (the LCxHolz config is MySQL-specific; Nusszopf would need `pg_dump` wired through the same package).

**Neither tier backs up Meilisearch.** Meilisearch's index is fully derivable from PostgreSQL by reindexing (assuming Nusszopf's search indexing is idempotent and triggered from domain data, per standard Laravel Scout/Meilisearch integration patterns) — so the recommended default is **don't back up the index, document a reindex command as the recovery path**, but this is Unknown/unverified until `docs/search/README.md` establishes how indexing actually works.

What must always be backed up regardless of tier (Confirmed necessity from LCxHolz's reasoning, directly applicable): the database, the storage volume (user uploads), and `.env` (contains `APP_KEY` — losing it makes any `APP_KEY`-encrypted data permanently unrecoverable).

## Restore

Confirmed, tested procedure (adapted from Waffle Dashboard's, which is the only one of the three actually run end-to-end by a third party rather than just by the original author):

```bash
# 1. Restore the database (stack running, or at least postgres up)
cat "$RESTORE_DIR/postgres.dump" | docker compose exec -T postgres \
  pg_restore -d "$DB_DATABASE" -U "$DB_USERNAME" --clean --if-exists

# 2. Stop the app so nothing writes to storage mid-restore
docker compose down

# 3. Restore the storage volume
docker run --rm \
  -v nusszopf_laravel-storage:/data \
  -v "$RESTORE_DIR:/backup" \
  ubuntu bash -c "rm -rf /data/* /data/.[!.]* /data/..?* ; tar xzf /backup/storage-volume.tar.gz -C /data --strip-components=1"

# 4. Start the app again and rebuild caches
docker compose up -d
docker compose exec php-fpm php artisan optimize

# 5. The search index is not part of the backup: rebuild it (see "Search index recovery")
docker compose exec php-fpm php artisan search:reindex
```

An untested restore procedure is not a backup — LCxHolz's own documentation makes this point explicitly and recommends periodic restore drills against a disposable database; the same discipline applies here.

## Upgrades

1. Read the release's `CHANGELOG.md` section for **Breaking:** and **Migration required:** entries (`docs/release/breaking-changes.md`).
2. Back up (see [Backups](#backups)) — a migration cannot be undone by starting an older image.
3. Set `NUSSZOPF_VERSION` in `.env` to the new release, then:

```bash
docker compose pull
docker compose up -d
docker compose ps                                          # wait for "healthy"; a migration can make php-fpm take a while
docker compose exec php-fpm php artisan nusszopf:health    # shows the new version
```

Nothing else is needed: the `php-fpm` container's entrypoint applies pending migrations (locked, so it happens once) and rebuilds the caches before it serves; `queue-worker` and `scheduler`
start after it; named volumes are untouched. If the changelog says the search index changed (Slice 3: index renamed `items`; Slice 4: `req_type` filterable), also run
`docker compose exec php-fpm php artisan search:reindex` once. Upgrading across several releases in one step is supported the same way; read every changelog section in between.

`docs/release/upgrades.md` and `docs/release/breaking-changes.md` own the compatibility policy; this document owns the mechanics.

## Rollback

Set `NUSSZOPF_VERSION` back to the previous release, `docker compose pull && docker compose up -d`. **This rolls back the application code only, not database migrations.** If the release you are leaving
ran a migration that older code cannot live with, restore the pre-upgrade backup instead (the policy is "restore from backup, not migrate down"). Then run `search:reindex` if the index changed in between.

## Running one-off Artisan commands

```bash
docker compose exec php-fpm php artisan <command>
```

For commands that must run against a container that hasn't yet executed the entrypoint's `config:cache` (e.g. generating the first `APP_KEY`, or any command whose environment-dependent behavior would otherwise read cached config), use a fresh container with an overridden entrypoint instead of `exec`-ing into the running one — Confirmed necessity from LCxHolz's deployment documentation, which hit and documented exactly this failure mode:

```bash
docker compose run --rm --no-deps --entrypoint php php-fpm artisan key:generate --show
```

## Troubleshooting

- **`docker compose up` says a variable is missing** (`Set NUSSZOPF_VERSION…`, `Set DB_PASSWORD…`): Compose is reading your `.env`; fill in the named line.
- **`php-fpm` exits at once with "APP_KEY is not set"**: generate one (Running one-off Artisan commands) and put it into `.env`.
- **`php-fpm` stays "starting" for minutes after an upgrade**: a migration is running (`docker compose logs -f php-fpm`); it has up to three minutes before it counts as unhealthy. If a migration failed the container keeps restarting and the log names it — restore the pre-upgrade backup and report it.
- **`queue-worker`/`scheduler` "unhealthy" right after start**: they need their first heartbeat (up to a minute after `php-fpm` is up). Persistently unhealthy: `docker compose logs scheduler queue-worker`; `nusszopf:health` names the failing check and how old the last heartbeat is.
- **Search shows nothing / is stale**: `nusszopf:health` (is `search` ok?), `queue:failed`, then `search:reindex`.
- **Links or redirects use `http://` behind a proxy, or the login loops**: `TRUSTED_PROXIES`, `APP_URL=https://…` and `SESSION_SECURE_COOKIE` in `.env`, then `docker compose up -d` again (`config:cache` is rebuilt on start).
- **A changed `.env` has no effect**: the configuration is cached at start — `docker compose up -d` recreates the containers with the new environment; `restart` alone does too.
- **Assets 404 or a stale UI after a deploy**: the images of one release always carry matching assets; check that `web` and `php-fpm` run the same `NUSSZOPF_VERSION` (`docker compose images`) and `docker compose pull` was run.
- **Backup restore doesn't match production dump-tool version**: PostgreSQL's `pg_dump`/`pg_restore` are version-tolerant, but always restore using a client version compatible with the target server's major version.
