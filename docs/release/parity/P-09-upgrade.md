# P-9 Upgrade/migration testing (2026-09-25)

Exit evidence (`master-roadmap.md` §4): the upgrade from N-1 to N was tested on data from the previous tag, including
the rollback statement in `operations.md`, and `upgrades.md` matches.

The maintainer set these conditions:
- install the previous version cleanly;
- fill it with representative data;
- upgrade it using only the documented procedure;
- check that the data is intact and usable, with particular attention to migrations running on populated data rather
  than on an empty database.

## Which versions: there is no previous release yet

No tag exists and nothing was ever published (P-8, P8-01), so **a true N-1 → N upgrade cannot be tested yet.** No
result here claims otherwise. P-9 used the two closest reproducible versions instead. Both were built the way
`release.yml` builds a release: images with the version baked in, and the three operator files prepared as the
workflow's "Operator files" step does.

| Role | Commit | Version label | Why this one |
|---|---|---|---|
| N-1 (a) | `4de0194` | `0.1.0-p8` | The build P-8 installed as a release, so the closest thing to a "previous release". Same schema as N: no pending migration. Its operator files differ from N's (`MAIL_FROM_ADDRESS` required, the P-8 header changes) |
| N-1 (b) | `8c4a2eb` | `0.0.1-o1` | The first commit with the operator stack (O-1). It is **four migrations behind N**, two of which alter the populated `users` table. It also has an older `docker-compose.yaml` (no storage mount on `web`, no `./legal` mount) and an older `.env` template (`TRUSTED_PROXIES=*`, `mail@nusszopf.org`). This is the migration-heavy case |
| N | working copy on `1403cd8`, then with this phase's fixes | `0.1.0-p9`, `upgrade-to` | The current state |

No migration was ever edited after its commit (`git log` per file), so an installation of either N-1 has exactly the
schema its commit defines.

The images were built locally and never pulled from GHCR, as in P-8. `docker compose pull` was run as documented. It
fetched the third-party images and was refused for the two unpublished Nusszopf images (`denied`). Those came from the
local build instead (`--ignore-pull-failures`).

## Starting dataset and how it was made

The data was written by **the N-1 release's own code**, so that it has the shapes that release really produces. Three
sources:
1. **N-1's own browser suite, through the real UI** (manual run A: `4de0194`'s Playwright suite, Chromium, 59
   journeys passed; run B: `8c4a2eb`'s suite, 17 passed). This created accounts by registration, projects by the
   wizard, requests, contact mails, avatar uploads, newsletter sign-ups, visitor counts, lockouts and deletions. Run
   B's three failures are that old build's own gaps, not upgrade problems: two need the LocationIQ stub, which was not
   configured for B, and one is the P7-01 defect (no `req_type` filter), fixed later.
2. **A bulk dataset through N-1's own models.** `tests/Upgrade/seed.php` adapts to the schema of the release it runs
   in. It is deterministic.
3. **State in transit at the moment of the upgrade:**
   - a browser signed in (`tests/Upgrade/session.mjs`);
   - with the queue worker stopped, a search-index update, a password-reset mail, a newsletter confirmation mail and
     a contact mail left queued in Redis (`tests/Upgrade/inflight.php`; the old release queues only the kinds it
     has);
   - links mailed but not yet opened: an e-mail verification, a password reset, a newsletter confirmation and a
     newsletter unsubscription.

`seed.php` writes the following, where the release has it:

| Data | What |
|---|---|
| Accounts | 40, with passwords. Two in three are verified. One is a Google-linked account with no password and a Google avatar URL (the BUG-004 shape) |
| Avatars | 8 real uploads through the release's own `AvatarUploader` (resize, crop, versioned file). 5 were replaced once, so `avatar_version` is 2 and the old file is gone |
| Projects | 80: public and private; remote and located (with coordinates); flexible and fixed periods; with and without team and motto; personal and "Über Nusszopf" contact. Umlauts, `&`, quotes and literal `<b>` in the text |
| Requests | 198 in all five categories |
| Visitor counts | 40 `project_analytics` rows |
| Newsletter | 15 subscribers from all three sources, confirmed and unconfirmed |
| Password reset | 1 pending token |

What was in each installation at the moment of the upgrade:

| | Run A (`0.1.0-p8`) | Run B (`8c4a2eb`) |
|---|---|---|
| `users` | 79 | 58 |
| `projects` / `project_requests` | 95 / 209 | 85 / 201 |
| `project_analytics` | 49 | (table does not exist yet) |
| `leads` | 17 | (table does not exist yet) |
| `password_reset_tokens` | 2 | (no password reset yet) |
| Files in the storage volume | 19 (avatars) | 3 |
| Search documents | 172 | 157 |
| Jobs queued across the upgrade | 5 (index, reset, newsletter, contact) | 2 (index) |

## Upgrade procedure

**Runs A and B (manual, 15:38 and 15:48)** followed `operations.md` "Upgrades" as it stood at `1403cd8`:
1. read the changelog (empty: no release has one);
2. back up;
3. change `NUSSZOPF_VERSION` in `.env`;
4. `docker compose pull`, then `docker compose up -d`, `docker compose ps` and `nusszopf:health`.

Run B showed that this procedure is incomplete (P9-01). It was changed to the following, which the harness runs
(`scripts/upgrade-test.sh`) and `operations.md` now documents:

```sh
# 1. read the CHANGELOG sections in between
# 2. back up
docker compose exec -T postgres sh -c 'pg_dump --format=custom -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > …/postgres.dump
cp -rp .env legal …/
# 3. upgrade
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/download/<version>/install.sh   # local release files here
sh install.sh --upgrade <version>
docker compose pull
docker compose up -d
docker compose ps
docker compose exec php-fpm php artisan nusszopf:health
```

Test doubles, as in P-7: Mailpit stood in for the SMTP relay, and there was a LocationIQ stub. Both were layered on
through `COMPOSE_FILE` (`tests/E2E/production/compose.e2e.yaml`), so every command was typed exactly as documented.

## Migration results and data-integrity checks

| Check | Run A (`0.1.0-p8` → N) | Run B (`8c4a2eb` → N) |
|---|---|---|
| Migrations | none pending ("Nothing to migrate"); `migrate:status` identical | **4 applied on the populated data**: `create_project_analytics_table` 15.5 ms, `add_auth_completion_columns_to_users_table` 9.6 ms (adds `email_verified_at` and a unique `google_id`, makes `password` nullable), `add_avatar_version_to_users_table` 2.2 ms, `create_leads_table` 13.5 ms. No pending migration afterwards |
| Rows and columns that existed before | **byte-identical**. Every table was dumped, sorted by every column, and compared by sha256 | **byte-identical** for every pre-existing column. The new columns got their defaults on all 58 rows: `email_verified_at` NULL, `google_id` NULL, `avatar_version` 0. No password became NULL |
| Stored files | identical (19 files, sha256). All 10 referenced avatars are served by `web` byte-identical to the stored file | identical |
| Search documents | identical, except the one project and its request updated by the job queued before the upgrade (now `…INFLIGHT umbenannt`) | the same |
| Search index settings | unchanged (already current) | **updated by the entrypoint with no manual step**: `req_type` and `updated_at` filterable, `updated_at:desc` ranking, the searchable fields, and `maxTotalHits` 1000 → 100000. No reindex was needed |
| Start-up | `up -d` returned after 12 s; all healthy 19 s after it started | returned after 8 s; all healthy after 15 s |
| Downtime (probe once a second on `/` and `/health`) | 14 s: 502, then connection refused, 15:38:50–15:39:04 | 7 s, 15:48:47–15:48:54 |
| Queue | all 5 jobs serialized by N-1 were run by N in its first second: MakeSearchable, RemoveFromSearch, ChangePasswordMail, NewsletterSubscribeMail, ContactMail. The three mails arrived in Mailpit. No new failed job | the index job ran; no failed job |
| Scheduler | `schedule:list`: both heartbeats, and `newsletter:purge-unconfirmed` daily at 03:30 | both heartbeats healthy |
| Health | `nusszopf:health`: every check ok; version `0.1.0-p9` | the same |
| Signed-in session from N-1 | still signed in; My Projects lists the user's four projects | the same |
| Links mailed under N-1, opened under N | password reset: form shown, new password set, sign-in with it works. Verification: account verified. Newsletter confirmation: confirmed. Unsubscription: subscriber removed | (the old build had none of these) |
| Sign-in with N-1 passwords | by name and by e-mail address | the same |
| Public and private data | the public project page shows its requests; a private project is 404 for a visitor and absent from search; search finds seeded projects and requests | the same |
| Legal pages | the texts from `./legal` shown | old compose file: not configurable (P9-01) |
| Whole browser suite of N on the upgraded installation | **171 passed**, 6 skipped (axe runs only on Chromium, by design), on Chromium, Firefox and WebKit | **171 passed**, 6 skipped (harness run, after the fix) |

After the fixes, `scripts/upgrade-test.sh` repeated all of the above automatically. It passed five times:

| From | Suite | Result |
|---|---|---|
| `8c4a2eb` | `--suite` | passed; 171 passed, 6 skipped |
| `4de0194` | `--suite` | passed; 171 passed, 6 skipped |
| `8c4a2eb` | without the suite | passed (after the Pint edits; then the failing-migration probe below) |
| `4de0194` | without the suite | passed (after the Pint edits) |
| `4de0194` | without the suite | passed (final run, after the last `install.sh` change) |

`sh scripts/smoke-test.sh` also passed with the new regression step, after the last change too.

### A migration that fails

On the upgraded run-B stack, a migration that first adds a column and then fails (`SELECT 1/0`) was put into
`php-fpm`, and `php-fpm` was restarted. The results:
- `php-fpm` went into a restart loop, and the site answered 502;
- the log named the migration with `FAIL` and the database error;
- PostgreSQL rolled the migration back completely: the added column was not there, and the migration had no row in
  `migrations`;
- `queue-worker`, `scheduler` and `web` kept running.

So a failed migration leaves the database exactly as the previous release had it. The troubleshooting entry in
`operations.md` now says this, verified.

## Findings and fixes

| # | What happened | Kind | Resolution |
|---|---|---|---|
| P9-01 | **Upgrade defect.** The documented upgrade changed only `NUSSZOPF_VERSION`, so the operator kept the old release's `docker-compose.yaml`. Upgrading `8c4a2eb` → N this way gave a stack that reported healthy and was partly broken: `web` had no storage mount, so every avatar uploaded after the upgrade answered 404 (verified); the legal pages could not be configured, because there was no `./legal` mount (verified). The `.env` also kept `TRUSTED_PROXIES=*` (the P-4 concern) and `mail@nusszopf.org` (P8-03) silently. From `0.1.0-p8` it happened to work only because that compose file differs from N's by the `MAIL_FROM_ADDRESS` check alone | Defect in the upgrade procedure and tooling (operator files, not historical product behaviour; nothing in `bugs.md` applies) | **Fixed.** `install.sh --upgrade [version]` replaces `docker-compose.yaml` and `.env.production.example` with the release's, keeps the old files as `*.previous` (`.env.previous` mode 600; a second run for the same release keeps them), sets `NUSSZOPF_VERSION`, keeps every other `.env` value, creates `legal/`, and lists empty required settings, the two unsafe legacy values, and new settings. `operations.md` "Upgrades" uses it, with the version in the download URL. **Regression:** `scripts/smoke-test.sh` (CI) runs `--upgrade` on an older installation and asserts all of the above, including that a second run keeps the `.previous` files. It fails with the previous `install.sh` (checked). `scripts/upgrade-test.sh` fails if the compose file is not replaced, if `web` does not serve a file written after the upgrade, or if the legal pages do not come from `./legal` |
| P9-02 | The pre-upgrade backup failed as written. The documented `pg_dump -d "$DB_DATABASE" -U "$DB_USERNAME"` runs in the operator's shell, which does not have the `.env` values: `FATAL: role "root" does not exist`, an empty dump, exit 1. An operator following "back up, then upgrade" would have upgraded without a backup | Documentation (a command that cannot work) | **Fixed** in "Upgrades" step 2 and the Backups tier-1 script: `sh -c '…'` with the container's own `$POSTGRES_USER`/`$POSTGRES_DB`, verified here. The same fix was applied to Restore step 1, not verified: the restore drill is P-10 |
| P9-03 | `redis:alpine` moved from 8.8.1 to 8.10.2 during run A's `docker compose pull` (the P-8 observation, now seen). A major Redis version could arrive the same way, with a data format older ones cannot read | Operator stack | **Fixed.** Pinned to `redis:8-alpine` in `docker-compose.yaml`, `compose.dev.yaml` and CI, like `postgres:16-alpine` and `getmeili/meilisearch:v1.11`. The upgrade pulls the same 8.10.2. Changing any of the three pins is now a **Migration required:** changelog entry (`breaking-changes.md`) |
| P9-04 | The rollback section led with "set `NUSSZOPF_VERSION` back", which rolls back the code only. It did not say how an operator can know whether the old code runs on the new schema, and it did not warn against `migrate:rollback` | Documentation (policy stated, but not as the primary path) | **Fixed.** "Rollback" says rollback means restoring the pre-upgrade backup plus the `.previous` files. Never `migrate:rollback`: the `down()` steps are untested and drop tables, for example `leads` and `project_analytics`. A code-only rollback is safe only when the release notes say so. Its observed behaviour is recorded (below) |
| P9-05 | "Upgrades" did not mention the downtime, did not say what happens to sessions, queued jobs and mailed links, and told operators to reindex for index *setting* changes that the entrypoint has applied by itself since P7-01 | Documentation | **Fixed** with the measured and verified facts above. A reindex is now named only for changed search *documents* |

### Observations, not changed

- **Existing accounts are unverified after an upgrade across the verification migration (decision A-3).** From
  `8c4a2eb`, all 58 accounts have `email_verified_at` NULL afterwards. The A-3 check runs when a project is saved
  (`app/Livewire/Concerns/ManagesProjectFields.php`). So an existing project keeps its "Persönlich" contact. Its owner
  must verify, through the resend action next to the error, before saving it with that contact again. This is the
  decided behaviour, and it concerns only installations older than slice 7, of which none exist. No action.
- **Failed jobs from before the upgrade.** Run A had two failed jobs before the upgrade: `WelcomeMail` and
  `VerifyEmailMail`, `ModelNotFoundException`, for accounts the N-1 suite deleted straight away. This is the known
  P-12 item and is unrelated to upgrading. The upgrade added none.
- **`APP_TIMEZONE=Europe/Berlin`** stays in `.env` files written from templates older than P-8. It does nothing (P8-05)
  and is harmless, so `--upgrade` does not flag it.
- **The 502 during an upgrade.** There is no maintenance page. The historical product was never self-hosted, so there
  is nothing to reproduce, and seconds of downtime per release is acceptable for this kind of instance. Documented.

## Rollback policy

The policy "restore from backup, not migrate down" is what `operations.md` "Rollback" states and what P-9 relies on:
- Migrations are forward-only.
- Every `down()` exists, but none is part of any procedure.
- Rolling back means restoring the pre-upgrade backup and putting back `docker-compose.yaml.previous` and
  `.env.previous`.

**Tested here:** a code-only rollback, which is the documented exception, from N back to `8c4a2eb`, on the migrated
database, with data N had written in the meantime (a newsletter subscriber):
- `php-fpm` started ("Nothing to migrate"), and `migrate:status` of the old build does not list the four newer
  migrations;
- health was ok, `/search` and `/login` answered 200, and sign-in worked;
- the newer tables and columns were ignored and kept.

Rolling forward again ran nothing: the subscriber was still there, and there was no failed job. This worked because
that old code happens to tolerate the newer schema; "Rollback" says so and limits the path to releases whose notes say
it is safe.

**Not tested here:** restoring the backup into the stack. That is the P-10 drill. P-9 took the backup and checked it
was non-empty; it did not restore it.

## Required operator actions, ordering, downtime and prerequisites (as verified)

- **Prerequisites:**
  - a backup, as in step 2 (database, `.env`, `legal/`, and the storage volume for the avatars);
  - the new release's `install.sh`;
  - the same host requirements as installing: Docker Engine with Compose v2, `curl` and `openssl`.
- **Ordering:**
  1. `install.sh --upgrade`;
  2. edit `.env` if the script lists something;
  3. `docker compose pull`;
  4. `docker compose up -d`.

  Nothing else is ordered by hand:
  - Compose starts the containers in dependency order;
  - `php-fpm` migrates under a lock before it serves, and applies the index settings;
  - the worker and the scheduler start after it.

  Skipping releases works the same way (four migrations at once from `8c4a2eb`).
- **Operator actions that may be needed:**
  - a required setting the script names as empty;
  - replacing `TRUSTED_PROXIES=*` or an `@nusszopf.org` sender when the script flags them;
  - `search:reindex` when the changelog says the search documents changed;
  - anything a **Migration required:** entry says.
- **Downtime:** seconds (7 s and 14 s measured) plus the migrations' own time. The site answers 502 or refuses
  connections meanwhile. Sessions, queued jobs and mailed links survive.

## Limitations and what must be repeated

| Limitation | What to do | Owner |
|---|---|---|
| **No real N-1.** Both starting points are release-equivalent builds of earlier commits, not published releases | Run `sh scripts/upgrade-test.sh <previous tag> --suite` from the first real tag to the release candidate, and before every release after that (now in `release-process.md`) | P-16, then every release |
| The upgrade from the real GitHub Release and GHCR: `install.sh` downloaded from `releases/download/<version>/`, `--upgrade` without a version (`latest`), and `docker compose pull` actually fetching the Nusszopf images (here refused and loaded locally) | Part of the RC exercise on a real host | P-16 |
| arm64 images | As for P-8 | P-16 |
| Restoring the pre-upgrade backup, which is the real rollback path. Restore step 1 has the P9-02 variable fix, but it has not been run | Drill the restore | P-10 |
| Changing the Meilisearch pin (the index volume must be recreated and reindexed) | Inferred from Meilisearch's documentation; exercise it when a release first changes the pin | the release that changes it |
| Large installations: the migrations here took milliseconds on about 80 users and 100 projects | Nothing to do now: no current migration rewrites a table. A future migration that does must state its expected time in the changelog | future releases |

## Changed files

- `scripts/install.sh`: the `--upgrade [version]` mode (P9-01); the refusal on an existing `.env` points to it.
- `scripts/smoke-test.sh`: the P9-01 regression step.
- `scripts/upgrade-test.sh` (new): the repeatable P-9. It builds `<from-ref>`, installs, fills and upgrades it, and
  checks it.
- `tests/Upgrade/` (new): `seed.php`, `inflight.php`, `snapshot.sh`, `session.mjs`, `journeys.mjs`.
- `docker-compose.yaml`: `redis:8-alpine` (P9-03); the header describes the new upgrade command.
- `compose.dev.yaml`, `.github/workflows/ci.yml`: `redis:8-alpine`.
- `.env.production.example`: the header describes the new upgrade command.
- `docs/deployment/operations.md`:
  - "Upgrades", "Rollback" and the failed-migration troubleshooting entry;
  - the `pg_dump`/`pg_restore` variables in "Backups" and "Restore" (P9-02).
- `docs/deployment/README.md`: status line, the file table, the Redis image, the upgrade remark.
- `docs/release/upgrades.md`, `docs/release/breaking-changes.md`, `docs/release/release-process.md`: the tested
  procedure, the compatibility rules, the release step.
- `docs/testing/README.md`, `README-DEV.md`, `docs/development/README.md`: the upgrade test.
- `docs/release/parity/README.md`: the status and the carried-forward items.

## Status

**Done (awaiting the maintainer's closure).** The upgrade procedure was run on populated installations from two earlier
builds. The migrations work on populated data, and the pre-existing data is byte-identical afterwards. P9-01 (a genuine
upgrade defect) is fixed with regression coverage, and the documentation now matches what was verified.

A true N-1 → N test from a published release is not possible before the first tag exists. It is deferred to P-16
(above), not waived.
