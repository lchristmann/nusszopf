# P-10 Backup/restore drill (2026-09-25)

Exit evidence (`master-roadmap.md` §4): restoring into an empty host reproduces the users, projects, requests and
avatars.

The maintainer set these conditions:
- start from a populated installation;
- make the documented backup, including the database and every persistent file (the avatars);
- check that the backup holds what the restore expects;
- destroy the source, and restore into a genuinely empty host using only the documented procedure;
- verify the data, privacy, search, authentication, queue, scheduler and configuration afterwards;
- verify P-9's rollback path, which is restoring the pre-upgrade backup, in practice.

## Environments

Every host was its own Docker daemon (`docker:28-dind`: Docker 28.5.2, Compose v2.40.3, Alpine 3.22). Each had its own
image store, volumes and file system, and it started with nothing in it. They ran on one workstation (Linux
7.0.0-31, Docker 28.5.1), one host at a time, all publishing `127.0.0.1:18110`. So the restored installation answered
at the source's `APP_URL`, as it does when DNS moves to a replacement machine.

| Host | Role | What was on it |
|---|---|---|
| source | The installation that is lost | `install.sh` + the release files of the working copy (`0.1.0-p10` in the manual run, `restore-to` in the harness). Test doubles in the operator's own `compose.override.yaml`: Mailpit as the SMTP relay and the LocationIQ stub |
| target | The replacement host | Nothing but Docker, `curl`, `openssl` (the documented prerequisites) and `python3` (only for the comparison script) |
| rollback | An installation that is upgraded and rolled back | `8c4a2eb` (`0.0.1-o1`/`restore-from`), four migrations behind, installed with its own `install.sh` |

The Nusszopf images were built locally and loaded into each host (`docker save | docker load`), standing in for the
GHCR pull, as in P-8/P-9. Every third-party image (PostgreSQL 16.15, Redis 8, Meilisearch 1.11, Mailpit, nginx) was
really pulled from the registry by each empty host.

## The data

- **Manual run (the procedure as it stood at `bf0159c`):**
  - `tests/Upgrade/seed.php`: 41 accounts (verified, unverified, one Google-linked), 8 real avatar uploads (5
    replaced once), 80 public and private projects, 198 requests in all five categories, 40 visitor-count rows, 15
    newsletter subscribers, a pending password reset;
  - then the whole Chromium suite through the real UI (58 passed): registrations, the project wizard, avatar uploads,
    deletions, lockouts, contact mails.

  In total: 79 users, 95 projects, 209 requests, 49 visitor counts, 16 subscribers, 172 search documents and 395
  stored files.
- **Harness runs (`scripts/restore-test.sh`):** the same seed on the fixed image: 375 rows in 6 tables, 151 search
  documents and 11 stored files (8 avatars and 3 `.gitignore`).

## 1. The procedure as documented: what happened

`operations.md` at `bf0159c` was followed literally.

| Step | Result |
|---|---|
| Tier-1 script, saved as a file and run by root's crontab as the section says | **Silent failure.** Cron does not start in the installation directory, so `docker compose exec` said `no configuration file provided: not found`. It wrote a **0-byte `postgres.dump`**, the storage archive next to it, and the script ended with exit status 0 (no `set -e`). Retention would have rotated the good backups out after 30 days |
| The same script run by hand in `/opt/nusszopf` | 47 KB dump (13 tables with data), 158 avatars in the archive. **`.env` and `legal/` are not in it**, though the text below says they must always be backed up; they were copied by hand. `compose.override.yaml` is not mentioned anywhere |
| Restore on the empty host | The section does not say how to prepare a new host. `docker-compose.yaml` came from the release, and `.env`/`legal/` from the backup. **Restore step 1 failed: `service "postgres" is not running`** |
| With the whole stack started first (the step's own hint), steps 1–5 | Worked: `pg_restore --clean --if-exists` exit 0, storage via the `ubuntu` image, `optimize`, `search:reindex`. Every table, stored file (owner uid 1000 kept), search document, index setting and the migration status matched the source byte for byte. Journeys passed. Sessions were signed out (Redis) |
| P-9 rollback (`8c4a2eb` → N → back): "Rollback" step 1 `docker compose down`, then step 2 = Restore step 1 | **Failed: `service "postgres" is not running`** |
| With only `postgres` started, Restore step 1 on the upgraded database | **`pg_restore` exit 1, 5 errors.** `DROP TABLE projects` was refused, because the newer `project_analytics` table has a foreign key to it. So `projects` kept its **post-upgrade** rows. `leads` and `project_analytics` stayed with their post-upgrade rows, while `migrations` went back to 5 rows |
| `.previous` files back, `up -d` | The old release started and reported **healthy** on this mixed database |
| Upgrading again | **`php-fpm` in a restart loop:** `relation "project_analytics" already exists`. This installation could never be upgraded again without manual SQL |

## 2. Findings and fixes

None of these concerns historical product behaviour. They are defects in the operator tooling and its documentation,
like P9-01, so nothing in `docs/rewrite/bugs.md` applies.

| # | What happened | Kind | Resolution |
|---|---|---|---|
| P10-01 | The backup script wrote an empty dump from cron and reported success. It never saved `.env` (holds `APP_KEY`, the database password), `legal/` or a `compose.override.yaml` | Defect (backup) | **Fixed.** The new script in "Backups": `set -eu`; `cd "$NUSSZOPF_DIR"`; it checks the dump with `pg_restore --list` and the archive with `tar tzf`; it writes into `….incomplete` and renames only on success; `umask 077`; a third file, `installation.tar.gz`, holds the installation directory. The storage goes through `docker compose run … php-fpm tar`, so it needs no hard-coded volume name and no `ubuntu` image |
| P10-02 | The restore could not run on a new host, and not after "Rollback" step 1: it needs PostgreSQL running, but nothing started it. No step said how to prepare a new host | Defect (restore procedure) | **Fixed.** One Restore block for both cases: prepare (new host: Docker, backup folder, `mkdir`; existing installation: `down`), then unpack `installation.tar.gz`, `pull`, start only `postgres redis`, restore, `up -d --wait`, reindex, health. `optimize` was dropped, because the entrypoint already warms the caches |
| P10-03 | `pg_restore --clean` onto a newer schema failed halfway and left a mix of both versions whose `migrations` table no longer matched. The old release ran on it, and every later upgrade failed | Defect (restore/rollback), severe | **Fixed.** `dropdb`/`createdb`, then `pg_restore --exit-on-error` into the empty database. `redis-cli FLUSHALL`, so no job or session from after the backup survives. "Rollback" = `down` + the Restore block. The backup's `installation.tar.gz` brings that release's `docker-compose.yaml` and `.env`, so the `.previous` files are no longer needed |
| P10-04 | `.dockerignore` did not exclude `storage/app`. A locally built image carried the working copy's uploads: 148 avatars of development accounts and 284 Livewire temporary files. Docker fills every new `laravel-storage` volume from the image, so every installation from a local build (contributors, `compose.prod.yaml`, the P-7…P-9 harnesses) got them. Release images are not affected, since CI builds from a clean checkout | Defect (build) | **Fixed.** `storage/app/public/*` and `storage/app/private/*` are excluded, except the `.gitignore` files. The image now has 3 files there instead of 435 |
| P10-05 | The documentation did not say what is outside the backup or what a restore loses | Documentation | **Fixed.** "Backups" has a table of what is included and why, and what is not: the search index (rebuilt), Redis (sessions, queue, cache, rate limits), the images and the TLS proxy. "Restore" lists what the operator sees afterwards, and "Backups" says to copy the backups off the host. The stale "needs approval — which tier" note was removed (decision B2 chose tier 1) |

**Regression coverage:**
- `scripts/restore-test.sh` takes the backup script and the Restore block out of `operations.md` by their markers and
  runs them as printed. With the old text it fails at P10-01 (cron writes no complete backup), P10-02 (the restore
  finds no running PostgreSQL) and P10-03 (the rollback's table and migration checks, and the second upgrade).
- `scripts/smoke-test.sh` (CI) puts a probe file into `storage/app/public` before building and fails if the image
  contains it (P10-04). With the old `.dockerignore`, the probe was baked in (checked); with the new one, the smoke
  test passed.

## 3. The backup procedure (as now documented and drilled)

Root's crontab ran `/usr/local/bin/nusszopf-backup.sh`, which is the "Backups" block, byte for byte:
`* * * * *` in the drill, `0 3 * * *` in the documentation. It wrote:

```
/opt/nusszopf-backups/2026-09-25_16-11-00/      drwx------
  postgres.dump         40441 B   pg_dump custom format; 13 tables with data
  storage.tar.gz         1322 B   the laravel-storage volume
  installation.tar.gz    5562 B   .env, docker-compose.yaml, compose.override.yaml, locationiq.conf, legal/
```

The harness then checked the backup before relying on it:
- the folder is mode 700;
- `pg_restore --list` reads the dump, and it has data for `users`, `projects`, `project_requests`,
  `project_analytics`, `leads`, `password_reset_tokens` and `migrations`;
- every avatar a user references (8 of 8) is in `storage.tar.gz`;
- `installation.tar.gz` has `.env` with the source's `APP_KEY`, `docker-compose.yaml`, the override with the file it
  mounts, and `legal/legal-notice.md`.

**Included, and why:** see the table in `operations.md`, "Backups". In short: the database is all the application's
data. The storage volume holds the only copy of uploaded files. The installation directory holds `APP_KEY`, the
database credentials, the release version and its compose file, the legal texts and the operator's overrides.

**Not included, on purpose:**
- **Meilisearch:** derived data. `search:reindex` rebuilt it identical to the source, document for document, with
  identical settings.
- **Redis:** sessions, queue, cache, rate limits and heartbeats; the heartbeats came back within a minute.
- **The images:** pulled again by version.
- **The TLS proxy:** outside the installation.

## 4. The restore procedure (as now documented and drilled)

On the target:
1. `mkdir -p /opt/nusszopf`;
2. the backup folder copied to `/opt/nusszopf-backups/`;
3. `RESTORE_DIR=…`;
4. then the Restore block of `operations.md`, as printed, except `--ignore-pull-failures` on `docker compose pull`,
   because the Nusszopf images are not published:

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

Every service was healthy **105 s and 107 s** after the first command, in the two harness runs; that includes pulling
all third-party images onto the empty host. `php-fpm` applied no migration.

## 5. Evidence that the target was genuinely empty, and the source gone

- **Target:** before anything was copied to it, the harness requires `docker images`, `docker ps -a` and
  `docker volume ls` to be empty, and no `/opt/nusszopf` or `/opt/nusszopf-backups` to exist. Output:
  `nusszopf-restore-target: empty (0 images, 0 containers, 0 volumes, no /opt/nusszopf or /opt/nusszopf-backups)`.
  The manual run showed the same: `images=0 containers=0 volumes=0 networks=0`, no `/opt/nusszopf`.
- **Source:** after the backup was copied off, the host was removed with `docker rm -f -v`: its daemon, its image
  store and its volumes. `http://127.0.0.1:18110` then answered nothing (curl `000`) until the target was up. The
  target never had access to the source's volumes; only the three backup files crossed over.
- The drill ran against the restored stack's HTTP port and its own `docker compose`. It did not unpack or inspect
  files instead of restoring.

## 6. Post-restore verification

| Check | Result |
|---|---|
| Every column of every data table (sorted dump, sha256 per table) | **identical** to the source (manual run: 79 users, 95 projects, 209 requests, 49 visitor counts, 16 subscribers, 1 reset token; harness: 375 rows) |
| Stored files (sha256 of every file in the volume) | **identical** (395 files manual, 11 harness); owner uid 1000 kept; every referenced avatar served by `web` |
| Search | after `search:reindex`, the documents are **identical** to the source's (172 / 151), and so are the index settings. Search finds seeded projects and requests. The private project is not found |
| Visibility/privacy | the private project from the backup is absent from search. On the restored installation, the suite's privacy specs passed: a private project is 404 for a visitor (`my-projects.spec.ts`), and a non-owner gets 404 for the edit form (`project-wizard.spec.ts`) |
| Authentication | sign-in with passwords from the backup, by name and by e-mail. A password-reset link mailed before the backup works and sets a new password. The e-mail verification link verifies |
| Sessions | a browser signed in on the source is signed out on the target: **expected and documented**, since Redis is not backed up |
| Newsletter | the confirmation link mailed before the backup confirms the subscriber |
| Configuration | `APP_KEY` identical (the signed links prove it). The legal pages show the restored `legal/` texts. The restored `compose.override.yaml` brought the SMTP relay and the LocationIQ stub back |
| Queue | the reindex ran through the worker. A password-reset mail queued after the restore arrived in Mailpit. No failed job (0, as on the source) |
| Scheduler | healthy, heartbeat within a minute; `schedule:list` shows the heartbeats and `newsletter:purge-unconfirmed` |
| Health | `nusszopf:health`: database, redis, search, scheduler and queue ok, version as backed up. Every container healthy |
| Writing | `php-fpm` writes into the restored volume, and `web` serves the new file |
| Whole Chromium suite on the restored installation (`--suite`) | **58 passed, 1 skipped.** The skipped test is the search-recovery spec, which needs the Meilisearch port and `E2E_REINDEX_COMMAND`, not set in this harness. P-11 owns it |

**Rollback (P-9's path), `--rollback-from 8c4a2eb`, with the fixed procedure:**
1. `8c4a2eb` installed and filled: 11 tables, 5 migrations.
2. The backup script as Upgrades step 2, then the upgrade to the working copy: 13 tables, 9 migrations. After it, a
   newsletter subscriber was added and an address changed.
3. `docker compose down` + the Restore block. `8c4a2eb` runs again. The table list, the `migrations` rows, every row
   of every table and every stored file are exactly the pre-upgrade backup's. The data written after the upgrade is
   gone.
4. `install.sh --upgrade` + `up -d --wait` again: healthy, no migration pending. That is the step P10-03 broke.

**Final harness run:** `sh scripts/restore-test.sh --suite --rollback-from 8c4a2eb`: `RESTORE TEST PASSED`.
`sh scripts/smoke-test.sh`: `SMOKE TEST PASSED`.

## 7. What could not be restored, or needed intervention

- **Needed by design, documented:**
  - everybody signs in again;
  - jobs that were still queued at the moment of the backup are not run;
  - rate-limit counters and login lockouts start fresh (Redis);
  - `search:reindex` is one of the restore's own lines.
- **No manual intervention beyond the documented steps.** Installing Docker and copying the backup folder to the new
  host are the documented preparation. The one change the harness makes to the printed block is
  `--ignore-pull-failures`, because the Nusszopf images are unpublished.
- **Not tested here, and who owns it:**

| Limitation | Owner |
|---|---|
| Pulling the Nusszopf images from GHCR onto the new host, and arm64 | P-16 |
| A physically separate machine. The hosts here were separate Docker daemons sharing one kernel. Nothing in the procedure depends on the host beyond Docker, and the backup crossed over as three files only | P-16 (RC host) |
| The off-host copy (`rsync`), which is the operator's own choice of tool. The drill copied the folder with `docker cp` | operator; documented as necessary |
| Encryption of backups at rest, alerting on a missing or stale backup | tier 2 of decision B2, a documented upgrade path, not v1 |
| Large installations: dump and archive took about a second here | future measurement; no migration or restore step is proportional to anything but data size |
| Search-index recovery on its own (wipe → reindex) | P-11 |
| Queue/scheduler failure behaviour | P-12 |
| Real SMTP delivery | P-13 |

## Changed files

- `docs/deployment/operations.md`:
  - "Backups": the new script, included/excluded, cron, off-host copies;
  - "Restore": one block for a new host and for rollback;
  - "Upgrades" step 2 and "Rollback";
  - the status banner.
- `.dockerignore`: `storage/app` uploads (P10-04).
- `scripts/restore-test.sh` (new): the repeatable drill.
- `scripts/smoke-test.sh`: the P10-04 regression step.
- `docs/deployment/README.md`, `docs/release/upgrades.md`, `docs/release/release-process.md`, `docs/testing/README.md`,
  `docs/development/README.md`, `README-DEV.md`, `docs/release/parity/README.md`, `docs/release/parity/00-doc-debts.md`.

## Status

**Done.** Closed by the maintainer on 2026-09-25 on the evidence above. P10-01…P10-05 are fixed, with regression
coverage where practical (`scripts/restore-test.sh`, the P10-04 step of `scripts/smoke-test.sh`).

These are deferred to P-16, not waived. P-16 must complete them before release:
- pulling the Nusszopf images from GHCR onto the new host;
- arm64;
- one restore on a physically separate machine;
- `sh scripts/restore-test.sh --rollback-from <previous tag>` from the first real tag.

Backup encryption and stale-backup alerting stay out of v1 scope, as decided (B2: tier 1 first, tier 2 is the
documented upgrade path). The other limitations in section 7 belong to the phases that own them (P-11, P-12, P-13).
They do not reopen P-10.
