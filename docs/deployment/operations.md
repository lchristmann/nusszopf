# Operations

> **Status: health, logs, queue and scheduler, upgrades, rollback, search recovery and troubleshooting are implemented and verified
> (operational track O-1/O-2, 2026-09-22); upgrades were tested again on populated installations in P-9 (2026-09-25); backups, the restore onto an empty host and the rollback of an upgrade were drilled in P-10 (2026-09-25, `scripts/restore-test.sh`); the search index recovery in P-11 (2026-09-25, `scripts/search-recovery-test.sh`).**
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

The historical product had no scheduled work (its cron triggers were empty), so the scheduler runs the two heartbeats plus one task added with the newsletter (slice 9): `newsletter:purge-unconfirmed`, daily at 03:30 UTC (the application runs in UTC; there is no timezone setting), deletes newsletter subscriptions nobody confirmed within 14 days (decision A-1). New scheduled work is added only with the slice that needs it.
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
| **SMTP** | Contact form, registration, "forgot password", a newsletter sign-up/unsubscribe request, a login lockout: the triggering action still succeeds (every mail is queued, never sent inline) | Every mailable (`App\Mail\ContactMail`/`WelcomeMail`/`ChangePasswordMail`/`VerifyEmailMail`/`BlockedAccountMail`, all `ShouldQueue`) rides the same queue and retry/backoff policy as search indexing; an unreachable mail server fails and retries the same way, landing in `failed_jobs` after five attempts. Verified at the Feature-test level (e.g. `tests/Feature/Mail/ContactMailTest.php`, "leaves a failed contact send in failed_jobs instead of losing it"), and live on the production stack in P-7 (2026-09-24, `docs/release/parity/P-07-production-e2e.md`): with the relay stopped, a welcome mail failed and was delivered by the next retry once the relay was back; with the relay down for good, it reached `failed_jobs` after five attempts (about 4 minutes) and `queue:retry all` delivered it | `queue:retry all` once SMTP is reachable again |
| **Google (OAuth)** — no GOOGLE_CLIENT_ID/SECRET configured | The login screen simply has no Google button; password/username login and registration are entirely unaffected | Both `/auth/google/*` routes 404 | Set `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` and restart; not a dependency the app requires to function |
| **Google (OAuth)** — configured but unreachable/erroring mid-login | A generic "Sorry, da lief etwas schief." toast on the login screen; no account is created or modified | `GoogleController::callback()` catches the failure and redirects to `/login`, synchronously (this is a request-time auth exchange, not a background job — there is nothing to retry) | The visitor retries, or uses password/username login instead |

A saved change is never lost when search is down: the database is written first and the index job is retried; `search:reindex` repairs whatever still went wrong.

## Search index recovery

The search index (Meilisearch, one shared `items` index) is **derived data**: everything in it comes from PostgreSQL, so it is
never backed up, only rebuilt. No database restore is involved. One command does it, and it is safe to run at any time, as
often as needed:

<!-- P-11: scripts/search-recovery-test.sh runs this block exactly as printed. -->
```bash
docker compose exec php-fpm php artisan search:reindex
```

It does three things, in this order:

1. **Applies the index settings** from `config/scout.php`: which fields are searched, the category filter (`req_type`), the
   ranking with its tie-breaks (`updated_at:desc`, then `id:asc`), and the hit cap. It waits until Meilisearch has
   applied them, and fails if it did not. Without the settings, search still answers, but the category filter shows
   nothing, and the order and the "Mehr laden" limit are wrong.
2. **Drops every document.** A leftover document, for example from a project that has since become private or been
   deleted, cannot survive.
3. **Imports all public projects and their requests** from PostgreSQL. Private projects and their requests are never
   imported.

`Index settings applied.` and `Search index rebuilt` mean all three steps were accepted. With `SCOUT_QUEUE=true` (the
default), the queue worker writes the documents, so search fills up over the next seconds to minutes, depending on the number
of projects. It is complete when `docker compose logs -f queue-worker` stops showing `MakeSearchable` jobs. In P-11, 151
documents took 1 to 2 seconds.

After that, **normal indexing** takes over again by itself. Every saved, published, unpublished or deleted project or request
updates the index through the queue. That is not a recovery tool: it only writes what changes. On a lost index it even
recreates `items` **without its settings**, so the category filter fails until `search:reindex` runs.

**When to run it:**
- after restoring a backup;
- after losing, deleting or replacing the `meilisearch-data` volume;
- after upgrading to a release whose changelog says the search documents changed (see "Upgrades");
- when `nusszopf:health` reports `search` failing with "run php artisan search:reindex";
- when search results are missing or stale, for example after Meilisearch was down for longer than the queue's retries.

The health check notices a missing index and missing settings. It does not notice a single missing or stale document, so
run the command whenever results look wrong.

If the command names a step that failed, Meilisearch is unreachable or misconfigured: fix that and run it again. Failed sync
jobs stay visible in the `failed_jobs` table (`php artisan queue:failed`) and can be retried with `queue:retry all`;
reindexing makes them unnecessary.

**If Meilisearch itself does not start.** `docker compose ps` then shows `meilisearch` restarting, and
`docker compose logs meilisearch` shows an error such as `MDB_INVALID: File is not an LMDB file`: its data on disk is damaged.
The same happens if a future Meilisearch release cannot read the old data. Throw the data away and rebuild it. Nothing
else is touched: PostgreSQL, the uploaded files and the sessions stay as they are.

<!-- P-11: scripts/search-recovery-test.sh runs this block exactly as printed. -->
```bash
docker compose rm --stop --force meilisearch
docker volume rm nusszopf_meilisearch-data
docker compose up -d --wait
docker compose exec php-fpm php artisan search:reindex
```

While Meilisearch is down or empty, the site keeps working and search shows "Verzopft…" (no hits). Projects saved meanwhile
are not lost: they are in PostgreSQL, and the reindex brings them in.

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

Tested end to end in P-10 (`docs/release/parity/P-10-backup-restore.md`): a backup made by this script from root's
crontab was restored onto a new, empty host with the commands in [Restore](#restore), and the restored installation
matched the original. Tier 1 of decision B2 (`docs/rewrite/decisions-register.md`): a shell script run by cron.
`spatie/laravel-backup` (encryption, thinning, a health check for stale backups) is the documented upgrade path, not
part of v1.

**What a backup contains**, and why:

| File | What | Why |
|---|---|---|
| `postgres.dump` | The whole database (`pg_dump`, custom format): accounts and password hashes, projects, requests, visitor counts, newsletter subscribers with their consent records, pending password resets, failed jobs | This is the application's data |
| `storage.tar.gz` | The `laravel-storage` volume: the avatars (`public/avatars`) and Livewire's temporary uploads | Uploaded files exist nowhere else |
| `installation.tar.gz` | The installation directory: `.env`, `docker-compose.yaml`, `legal/`, a `compose.override.yaml` and whatever else you keep there | `.env` holds `APP_KEY` (without it, links already mailed stop working) and the passwords the database was created with. `NUSSZOPF_VERSION` in it says which release the dump belongs to, and `docker-compose.yaml` is that release's |

**What a backup does not contain**, on purpose:
- **The search index** (`meilisearch-data`). It is derived from the database; the restore rebuilds it with
  `search:reindex` (see [Search index recovery](#search-index-recovery)).
- **Redis** (`redis-data`): sessions, queued jobs, the cache, rate-limit counters and the health heartbeats. After a
  restore everyone signs in again, and a job that was still queued when the backup ran (for example a mail) is not
  run. A mail that had failed is in `failed_jobs`, which is in the database.
- **The Docker images.** They are downloaded again for the release named in `.env`.
- **Your reverse proxy and its TLS certificates.** They are outside the installation; back them up with the proxy.

Save the script as `/usr/local/bin/nusszopf-backup.sh` and make it executable (`chmod 700`). Set `NUSSZOPF_DIR` if
your installation is not in `/opt/nusszopf`.

<!-- P-10: scripts/restore-test.sh runs this block exactly as written. -->
```sh
#!/bin/sh
# Nusszopf backup: the database, the uploaded files and the installation directory (docs/deployment/operations.md, "Backups").
set -eu
NUSSZOPF_DIR=/opt/nusszopf        # your installation: docker-compose.yaml and .env
BACKUP_ROOT=/opt/nusszopf-backups
KEEP_DAYS=30

umask 077                         # a backup holds .env, with APP_KEY and the passwords
cd "$NUSSZOPF_DIR"                # cron starts elsewhere, and docker compose must run here
BACKUP_DIR="$BACKUP_ROOT/$(date +%Y-%m-%d_%H-%M-%S)"
mkdir -p "$BACKUP_DIR.incomplete"

# The database first: a file uploaded while the rest runs is then an unused extra, never a missing avatar.
docker compose exec -T postgres sh -c 'pg_dump --format=custom -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > "$BACKUP_DIR.incomplete/postgres.dump"
docker compose exec -T postgres pg_restore --list < "$BACKUP_DIR.incomplete/postgres.dump" > /dev/null
docker compose run --rm --no-deps -T --entrypoint tar php-fpm czf - -C /var/www/storage/app . > "$BACKUP_DIR.incomplete/storage.tar.gz"
tar tzf "$BACKUP_DIR.incomplete/storage.tar.gz" > /dev/null
tar czf "$BACKUP_DIR.incomplete/installation.tar.gz" .

mv "$BACKUP_DIR.incomplete" "$BACKUP_DIR"
find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} +
echo "Backup written to $BACKUP_DIR"
```

Run it from root's crontab (`crontab -e`), for example every night at 3:00:

```
0 3 * * * /usr/local/bin/nusszopf-backup.sh >> /var/log/nusszopf-backup.log 2>&1
```

- **It needs the stack running** (it asks PostgreSQL for the dump) and takes a few seconds; the site stays up.
- **A failed backup is visible:** the script stops at the first error with a non-zero exit status, the log says why,
  and the folder keeps the suffix `.incomplete`. Only a folder without that suffix is a complete backup. Check the log
  and the folder list now and then; nothing warns you on its own.
- **Copy the backups to another machine.** A backup on the same disk does not survive the loss of the host. Copy
  `/opt/nusszopf-backups` elsewhere, for example with `rsync` from the other machine's crontab. The folders are
  readable by root only; keep the copies as private, since they contain `.env`.
- The dump is consistent in itself. The uploaded files are copied a moment later, so an avatar uploaded in between is
  in the backup but unused.

Two earlier versions of this script failed without saying so (P-9, P-10): the dump line read `$DB_DATABASE` from a
shell that does not have it (`role "root" does not exist`), and from cron, which does not start in the installation's
directory, every `docker compose` command found no `docker-compose.yaml` and wrote an empty dump while the script
still ended with success. That version also left `.env` and `legal/` out.

## Restore

Tested in P-10 on a new, empty host (no images, containers, volumes or files) and, as the rollback of an upgrade, on
an existing installation with a newer database schema. The same commands do both. They restore the database, the
uploaded files and the installation directory exactly as they were at the backup, and rebuild the search index.
Everything written after the backup is lost.

1. **Prepare.**
   - **On a new host:** install Docker Engine with the Compose plugin (as for [Installation](README.md#installation-operator-path)),
     copy the backup folder to it, and create the installation's directory: `mkdir -p /opt/nusszopf`.
   - **On the existing installation** (rolling back, or going back to an earlier backup): stop it with
     `docker compose down`, without `-v`.
2. **Choose the backup:** `RESTORE_DIR=/opt/nusszopf-backups/2026-09-25_03-00-00` (a complete folder, without
   `.incomplete`).
3. **Restore**, as root:

<!-- P-10: scripts/restore-test.sh runs this block exactly as written. -->
```bash
cd /opt/nusszopf
tar xzf "$RESTORE_DIR/installation.tar.gz"
docker compose pull
docker compose up -d --wait postgres redis
docker compose exec -T redis redis-cli FLUSHALL
docker compose exec -T postgres sh -c 'dropdb --if-exists -U "$POSTGRES_USER" "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'
docker compose exec -T postgres sh -c 'pg_restore --exit-on-error -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$RESTORE_DIR/postgres.dump"
docker compose run --rm --no-deps -T --entrypoint sh php-fpm -c 'find /var/www/storage/app -mindepth 1 -delete && tar xzf - -C /var/www/storage/app' < "$RESTORE_DIR/storage.tar.gz"
docker compose up -d --wait
docker compose exec php-fpm php artisan search:reindex
docker compose exec php-fpm php artisan nusszopf:health
```

What each line does:
- **`tar xzf …installation.tar.gz`** brings back `.env`, `legal/`, your `compose.override.yaml`, and the
  `docker-compose.yaml` of the release the backup was made with. `docker compose pull` then fetches that release's
  images, so the code always matches the database. Files that are not in the backup stay where they are.
- **Only PostgreSQL and Redis run** while the data goes back, so nothing else writes in between.
- **`FLUSHALL` empties Redis:** jobs queued after the backup belong to data that no longer exists, and sessions are
  signed out. On a new host it is empty anyway.
- **`dropdb`/`createdb` start from an empty database.** Do not use `pg_restore --clean` instead. After an upgrade, the
  database has tables the backup does not know. `--clean` then fails halfway: it cannot drop `projects` because the
  newer `project_analytics` table refers to it. It leaves a mix of both versions whose `migrations` table no longer
  matches, and the next upgrade then fails with `relation "project_analytics" already exists` (P-10, P10-03).
- **The storage volume is emptied and refilled** through the `php-fpm` image, so the files keep their owner.
- **`up -d --wait`** starts everything and returns once every service is healthy (about a minute). `php-fpm` applies
  no migration, since the code is the backup's own release. `search:reindex` fills the search index again, through
  the queue worker, within seconds to minutes.

If a line fails, fix the cause (for example, `docker compose pull` needs network access) and run all of them again
from the top: every line can be repeated.

**After a restore**, as verified in P-10:
- sign-in works with the passwords from the backup, but everybody has to sign in again;
- links mailed before the backup still work: password reset, e-mail verification, newsletter confirmation;
- public projects are found again and private ones are not;
- the avatars are served;
- the queue worker and the scheduler report healthy within a minute (`nusszopf:health`).

To move to a newer release after restoring an older backup, upgrade as usual ([Upgrades](#upgrades)).

Practise this now and then on a spare machine: an untested restore is not a backup.

## Upgrades

Tested from two earlier builds to the current one, on installations filled with data (P-9,
`docs/release/parity/P-09-upgrade.md`). A release is moved to with three commands. Your data is kept, a browser that
was signed in stays signed in, and work that was still queued is done by the new release.

1. **Read the release notes.** Read the `CHANGELOG.md` section of every release between yours and the new one. Look for
   **Breaking:** and **Migration required:** entries (`docs/release/breaking-changes.md`).
2. **Back up.** A migration cannot be undone by starting an older image; the way back is this backup (see
   [Rollback](#rollback)). Run the backup script from [Backups](#backups) and note the folder it names:

   ```bash
   /usr/local/bin/nusszopf-backup.sh      # "Backup written to /opt/nusszopf-backups/…"
   ```

   It holds the database, the uploaded files and the installation directory with this release's `.env` and
   `docker-compose.yaml`, which is everything a rollback needs (tested in P-10).
3. **Upgrade.** In the installation's directory (replace `0.2.0` with the release):

   ```bash
   curl -fsSLO https://github.com/lchristmann/nusszopf/releases/download/0.2.0/install.sh
   sh install.sh --upgrade 0.2.0
   docker compose pull
   docker compose up -d
   docker compose ps                                          # wait until every service is "healthy"
   docker compose exec php-fpm php artisan nusszopf:health    # shows the new version
   ```

   `sh install.sh --upgrade` without a version moves to the latest release.

`install.sh --upgrade` changes only these files:
- **`docker-compose.yaml`:** replaced by the new release's. A release can change this file, for example with a new
  mount or a new required setting. Setting only `NUSSZOPF_VERSION` would keep the old file, and the new release would
  then run without those changes (P-9, finding P9-01).
- **`.env`:** only `NUSSZOPF_VERSION` changes. Everything you set stays as it is.
- **`.env.production.example`:** replaced by the new release's template, to compare with.
- **`legal/`:** created if it does not exist.

The previous files stay next to the new ones as `docker-compose.yaml.previous`, `.env.previous` (mode 600: it holds
`APP_KEY`) and `.env.production.example.previous`. Running the command again for the same release keeps them. If you changed `docker-compose.yaml` yourself, your changes are in
the `.previous` file. Keep such changes in a `compose.override.yaml`, which Compose reads as well and no upgrade replaces.

The command then lists:
- **Required settings that are empty in `.env`.** `docker compose` refuses to start until you set them.
- **Values earlier releases wrote that are no longer safe**, such as `TRUSTED_PROXIES=*` or the historical project's
  sender address `…@nusszopf.org`. Change them in `.env`.
- **New settings your `.env` does not name.** Their defaults apply, so this is only for information.

Edit `.env` before `docker compose up -d` if anything is listed.

What `docker compose up -d` does:
- It recreates the containers whose image or settings changed.
- `php-fpm`'s entrypoint applies the pending migrations before it serves. The migrations are locked, so they run once.
  The entrypoint also rebuilds the caches and applies the new release's search index settings (filter, ranking, hit
  cap), so the index needs no reindex for them.
- `web`, `queue-worker` and `scheduler` start after `php-fpm` is healthy.
- The named volumes (database, storage, search index, Redis) are kept.

**Downtime.** During the upgrade the site is unreachable: first a 502, then no connection at all, until `web` is
running again. It lasted 7 s and 14 s in P-9's two runs, whose migrations took less than 0.1 s. A migration that
rewrites a large table adds its own time; `php-fpm` has three minutes before it counts as unhealthy. There is no
maintenance page.

**What carries over:**
- Sessions: they live in Redis, so signed-in visitors stay signed in.
- Queued jobs: mails and index updates waiting in Redis are run by the new release.
- Links already mailed: verification, password reset and newsletter links keep working. They are signed with
  `APP_KEY`, which an upgrade never changes.

**`docker compose pull` also updates PostgreSQL, Redis and Meilisearch** within the version `docker-compose.yaml` pins:
`postgres:16-alpine`, `redis:8-alpine` and `getmeili/meilisearch:v1.11`. These pins receive patch and minor releases
only, never a new major version with a new data format. P-9 saw `redis:8-alpine` move from 8.8.1 to 8.10.2 in one pull.
A release that changes one of these pins says so as **Migration required:**, with the steps.

**Search.** Only when the changelog says the search *documents* changed, also run
`docker compose exec php-fpm php artisan search:reindex` once.

**Several releases at once.** You can skip releases: run `install.sh --upgrade` with the newest version, and read every
changelog section in between. Upgrading from `8c4a2eb`, four migrations behind, worked this way in P-9.

`docs/release/upgrades.md` and `docs/release/breaking-changes.md` own the compatibility policy; this document owns the
mechanics.

## Rollback

**Rollback means restoring the pre-upgrade backup.** Migrations are never undone: do not run
`php artisan migrate:rollback`. Its `down()` steps are untested, and some of them drop tables with their data, such as
the newsletter subscribers and the visitor counts. To go back:
1. Stop the stack: `docker compose down` (without `-v`).
2. Restore the backup from step 2 of the upgrade with the commands in [Restore](#restore). They also bring back that
   release's `docker-compose.yaml` and `.env` from the backup, and start the stack.

Everything written after the upgrade is lost, because the database returns to the moment of the backup.

P-10 tested this from the current release back to `8c4a2eb`, four migrations older. The database had the old schema
again, exactly the backed-up rows, and none written after the upgrade; the old release ran, and upgrading again
afterwards worked. The earlier instructions (`pg_restore --clean` into the running database, then the `.previous`
files) left a mix of both schemas that could not be upgraded again (P10-03).

**Starting the previous release without restoring**, which keeps the data written since the upgrade, is safe only when
the release notes you are leaving list no migration, or explicitly say the previous release runs on the new schema. For
that, put back the two `.previous` files and run `docker compose up -d`. The newer migrations then stay applied:
- The older release does not know them. It reports "Nothing to migrate", and its `migrate:status` does not list them.
- It ignores the new columns and tables, and the rows in them.
- Upgrading again later does not repeat those migrations.

P-9 tested this path from the current release back to `8c4a2eb`, whose code happens to run on the newer schema. That
is not a promise for other releases. Run `search:reindex` afterwards if the search documents differ between the two
releases.

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
- **`php-fpm` stays "starting" for minutes after an upgrade**: a migration is running (`docker compose logs -f php-fpm`); it has up to three minutes before it counts as unhealthy. If a migration failed, `php-fpm` keeps restarting, the site answers 502, and the log names the migration with `FAIL` and the database error. PostgreSQL undoes a failed migration completely (verified in P-9), so the database stays as the previous release left it. Go back as described in [Rollback](#rollback), then report the error. Do not run migrations by hand.
- **`queue-worker`/`scheduler` "unhealthy" right after start**: they need their first heartbeat (up to a minute after `php-fpm` is up). Persistently unhealthy: `docker compose logs scheduler queue-worker`; `nusszopf:health` names the failing check and how old the last heartbeat is.
- **Search shows nothing / is stale**: `nusszopf:health` (is `search` ok?), `queue:failed`, then `search:reindex`. If `meilisearch` keeps restarting, see "Search index recovery", "If Meilisearch itself does not start".
- **Links or redirects use `http://` behind a proxy, or the login loops**: `TRUSTED_PROXIES`, `APP_URL=https://…` and `SESSION_SECURE_COOKIE` in `.env`, then `docker compose up -d` again (`config:cache` is rebuilt on start).
- **A link from an e-mail (verification, "Das bin ich!") answers 403**, or **styles and scripts do not load**: every link the application writes starts with `APP_URL`, which must be the exact public address; and a proxy outside the private networks must be listed in `TRUSTED_PROXIES`, or the application sees `http` where the signed link says `https` (`docs/deployment/README.md`, "Reverse proxy and TLS").
- **"Zu viele Versuche. Bitte warte kurz." for every visitor**: the application sees all visitors under the proxy's address — the proxy is not trusted (`TRUSTED_PROXIES`), so the per-address limits count everyone together.
- **A changed `.env` has no effect**: a container keeps the environment it was created with, and the configuration is cached at start — run `docker compose up -d`, which recreates the containers whose settings changed. `docker compose restart` is not enough: it restarts the containers with their old environment (verified in P-8).
- **Assets 404 or a stale UI after a deploy**: the images of one release always carry matching assets; check that `web` and `php-fpm` run the same `NUSSZOPF_VERSION` (`docker compose images`) and `docker compose pull` was run.
- **Backup restore doesn't match production dump-tool version**: PostgreSQL's `pg_dump`/`pg_restore` are version-tolerant, but always restore using a client version compatible with the target server's major version.
