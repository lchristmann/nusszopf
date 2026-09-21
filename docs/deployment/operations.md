# Operations

> **Status: Proposal.** Day-2 operations for a self-hosted Nusszopf instance, derived from `../development-reference/lcxholz/docs/backups/README.md`, `../development-reference/lcxholz/docs/deployment/README.md`, `../development-reference/lcxholz/deploy.sh`, and `../foss-reference/waffle-dashboard/docs/WAFFLE-INSTALLATION-GUIDE.md` + `docs/code/backup.sh`. See `docs/references/laravel-docker-examples.md` for full evidence. Nothing here is implemented; items marked **(needs approval)** are undecided.

## Table of contents

- [Health checks](#health-checks)
- [Logs](#logs)
- [Queue worker and scheduler](#queue-worker-and-scheduler)
- [Backups](#backups)
- [Restore](#restore)
- [Upgrades](#upgrades)
- [Rollback](#rollback)
- [Running one-off Artisan commands](#running-one-off-artisan-commands)
- [Troubleshooting](#troubleshooting)

## Health checks

- Container-level: `docker compose ps` shows `(healthy)`/`(unhealthy)` for `php-fpm`, `postgres`, `redis`, `meilisearch` once their healthchecks are wired (see `docs/deployment/README.md`).
- Application-level: Laravel's built-in `/up` route, unauthenticated, unaffected by any auth gate — confirmed pattern from LCxHolz (`App\Providers\AppServiceProvider::configureHealthChecks()`, `spatie/laravel-health`). Whether Nusszopf adopts `spatie/laravel-health` for a richer status page, or stays with the framework's bare `/up`, is **(needs approval)** — Unknown until engineering-quality decisions are finalized.
- Meilisearch's own `GET /health` — no reference project covers this since none use Meilisearch; add it as a Docker Compose healthcheck (`curl -f http://localhost:7700/health`) and, if a status page is built, as an additional check there.

## Logs

`docker compose logs -f <service>` for any service. No log aggregation is proposed for v1 — matches all three references, none of which run a log shipper.

## Queue worker and scheduler

Confirmed pattern from LCxHolz (the only reference with dedicated queue/scheduler services — `laravel-docker-examples` and Waffle Dashboard have neither):

```bash
docker compose exec queue-worker php artisan queue:work --status   # inspect
docker compose restart queue-worker                                  # after a bad deploy or stuck job
docker compose logs -f scheduler                                     # confirm scheduled tasks are firing
```

Nusszopf's actual queued/scheduled work (mail sending, search index reconciliation, any recurring cleanup) depends on domain archaeology not yet complete — Unknown which jobs exist until `docs/domain/workflows.md` and `docs/search/README.md` are populated.

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

# 5. If the search index isn't part of the backup, reindex
docker compose exec php-fpm php artisan <reindex-command>   # exact command Unknown until search archaeology is complete
```

An untested restore procedure is not a backup — LCxHolz's own documentation makes this point explicitly and recommends periodic restore drills against a disposable database; the same discipline applies here.

## Upgrades

> **Upgrading to the release with project requests (Slice 3):** the schema migration runs with the entrypoint's
> `migrate --force`, but the search index is renamed (`items`) and needs a one-time rebuild afterwards:
> `php artisan scout:sync-index-settings`, then `php artisan scout:import "App\Models\Project"` and
> `php artisan scout:import "App\Models\ProjectRequest"`; the old `projects` index can be deleted. Until then search
> returns nothing. Details: `docs/search/README.md`, "Third slice".

Confirmed pattern, identical across LCxHolz (`deploy.sh <tag>`) and Waffle Dashboard (manual `docker-compose.yaml` edit): bump the image tag, `docker compose down && docker compose up -d` (or `pull` + `up -d`), rely on the entrypoint's unconditional `php artisan migrate --force` to bring the schema up to date. Named volumes are untouched by this, so user data persists across the version bump.

```bash
# Waffle Dashboard's operator-facing form:
nano docker-compose.yaml   # bump the image tag(s)
docker compose pull
docker compose up -d
```

`docs/release/upgrades.md` and `docs/release/breaking-changes.md` own the versioning/compatibility policy for what "safe to upgrade across" means; this document only owns the mechanics.

## Rollback

Same mechanism as upgrade, previous tag. **Confirmed limitation from LCxHolz, directly applicable**: this rolls back application code only, not database migrations. If the version being rolled back from ran a destructive migration, rolling back the image alone does not undo it — restore from a pre-upgrade backup instead. Not solved more generically than that by any reference; adopting the same trade-off for Nusszopf unless a future decision changes this.

## Running one-off Artisan commands

```bash
docker compose exec php-fpm php artisan <command>
```

For commands that must run against a container that hasn't yet executed the entrypoint's `config:cache` (e.g. generating the first `APP_KEY`, or any command whose environment-dependent behavior would otherwise read cached config), use a fresh container with an overridden entrypoint instead of `exec`-ing into the running one — Confirmed necessity from LCxHolz's deployment documentation, which hit and documented exactly this failure mode:

```bash
docker compose run --rm --entrypoint php php-fpm artisan key:generate --show
```

## Troubleshooting

- **`php-fpm`/`queue-worker`/`scheduler` crash-looping on a genuinely empty database**: if migrations are ever run with an isolation lock backed by the database cache driver, the very first migration run can fail because the lock table itself doesn't exist yet. LCxHolz hit this exact failure and documented the fix (bootstrap once without the isolation flag, then let normal boots use it). Whether Nusszopf's migration strategy has the same isolation requirement is Unknown until the entrypoint script is actually implemented — flagged here so the same class of bug is checked for, not blindly assumed absent.
- **Assets 404 or a stale UI after a deploy**: see `docs/deployment/README.md`'s "why a dedicated nginx image, not a shared assets volume" — if this is ever seen, it means the build pipeline regressed to the shared-volume pattern this architecture was explicitly designed to avoid.
- **Backup restore doesn't match production dump-tool version**: LCxHolz documented three real MySQL-vs-MariaDB client incompatibilities from exactly this mismatch. PostgreSQL's `pg_dump`/`pg_restore` are more version-tolerant, but always restore using a client version compatible with the target server's major version.
