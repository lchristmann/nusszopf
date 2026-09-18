# Deployment and Self-Hosting

> **Status: Proposal.** Nothing in this document has been implemented yet. It is the target architecture derived from `../infrastructure-reference/laravel-docker-examples`, `../development-reference/lcxholz`, and `../foss-reference/waffle-dashboard` (see `docs/references/laravel-docker-examples.md` for the underlying evidence and comparison). Decisions marked **(needs approval)** must not be treated as settled.

## Table of contents

- [Design goal](#design-goal)
- [Two audiences, two sets of Compose files](#two-audiences-two-sets-of-compose-files)
- [Services](#services)
- [Why a dedicated nginx image, not a shared assets volume](#why-a-dedicated-nginx-image-not-a-shared-assets-volume)
- [Required configuration](#required-configuration)
- [Installation (operator path)](#installation-operator-path)
- [Reverse proxy and TLS](#reverse-proxy-and-tls)
- [Persistent storage](#persistent-storage)
- [Health checks](#health-checks)
- [Backups, upgrades, recovery](#backups-upgrades-recovery)
- [Open questions](#open-questions)

## Design goal

Per `CLAUDE.md` and `.claude/rules/06-self-hosting.md`: a person who has never seen the Nusszopf source code should be able to obtain a release, configure a small number of environment variables, start it, initialize it, verify it's healthy, back it up, and upgrade it — using documented, copyable shell commands, without installing PHP, Composer, Node, PostgreSQL, Redis, or Meilisearch on their own machine.

Docker Compose is the reference deployment. No Kubernetes, no orchestration platform, no SaaS control plane.

## Two audiences, two sets of Compose files

Evidence (`docs/references/laravel-docker-examples.md` §5) shows two distinct patterns across the reference projects:

- **LCxHolz**: one `compose.prod.yaml`, used by the project's own maintainer on a server they control, that keeps both `image:` (what actually runs, pulled from a registry) and `build:` (so a developer can rebuild the exact same image locally) on every service.
- **Waffle Dashboard**: a separate, `image:`-only `docker-compose.yaml` at the repository root, designed to be `curl`'d directly by an operator who has never cloned the repository and never builds anything themselves.

Nusszopf needs both, because it has both audiences — contributors who build and test the images, and self-hosting operators who only ever run them:

| File | Audience | Contains |
|---|---|---|
| `compose.dev.yaml` | Contributors | Bind-mounted source, Xdebug, a `workspace` sidecar for Composer/Node/Artisan, live-reloading Vite |
| `compose.prod.yaml` | Contributors / CI | `build:` + `image:` on every service, for building and locally verifying the exact production image before it's published |
| `docker-compose.yaml` (repo root) | Operators | `image:`-only, pinned to a released version tag, no build context needed at all |

**(needs approval)**: exact filenames above follow Waffle Dashboard's naming; LCxHolz calls its operator-analog file `compose.prod.yaml` (it has no separate curl'd file because it isn't distributed to third-party operators). Nusszopf, being FOSS and self-hosted by third parties, is closer to Waffle Dashboard's situation.

## Services

| Service | Image | Role |
|---|---|---|
| `web` | Thin nginx image built `FROM` the matching application image (see below) | HTTP, serves static assets, proxies PHP to `php-fpm` |
| `php-fpm` | Application image (PHP 8.5-FPM, Laravel 13, Livewire 4) | Request handling |
| `queue-worker` | Same application image, `command: php artisan queue:work` | Background jobs (mail, search indexing) |
| `scheduler` | Same application image, `command: php artisan schedule:work` | Cron-less scheduled tasks (backups, cleanup, search reconciliation) |
| `postgres` | `postgres:16` (matches all three references) | Primary datastore |
| `redis` | `redis:alpine` (matches all three references) | Cache, session store (Confirmed as the reference default; Nusszopf's actual session/cache driver choice is Unknown pending domain archaeology), queue backend |
| `meilisearch` | Official Meilisearch image | Search index — **no reference project uses Meilisearch**; this service's compose wiring, health check, and backup strategy are Nusszopf-original and unverified against any prior art |
| `workspace` (dev only) | Node + Composer + CLI tools | Contributor shell, asset builds, Artisan, tests |

There is deliberately no bundled reverse proxy or mail server — see [Reverse proxy and TLS](#reverse-proxy-and-tls) and `docs/email/README.md`.

## Why a dedicated nginx image, not a shared assets volume

`laravel-docker-examples`' production Compose file shares a named volume between `web` and `php-fpm` to keep Vite's `manifest.json` and hashed filenames consistent between the two containers. This has a real, demonstrated flaw: Docker only seeds a new named volume from an image the first time it's created, so every redeploy after the first leaves stale assets in place unless something actively re-syncs the volume on every boot (full evidence in `docs/references/laravel-docker-examples.md` §4).

LCxHolz papers over this with a custom nginx entrypoint that deletes and re-copies assets into the volume on every boot. Nusszopf instead follows **Waffle Dashboard's** structurally simpler approach: build Vite assets once, inside the application image's own multi-stage `Dockerfile` (no separate Node install in the nginx build), then build the nginx image `FROM <application-image>:<same-tag>` and `COPY --from=php-fpm-source /var/www/public /var/www/public`. Because both images are produced from the same source tree at the same version, the manifest can never drift, and no shared volume or runtime re-sync step is needed at all.

## Required configuration

**(needs approval — exact variable list)**. Minimum expected, following the `.env` shape common to all three references plus Meilisearch:

```
APP_NAME=
APP_ENV=production
APP_KEY=                 # generated once via `docker compose run --rm --entrypoint php php-fpm artisan key:generate --show`
APP_URL=
APP_TIMEZONE=

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=redis

MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=

MAIL_MAILER=              # see docs/email/README.md — historical Nusszopf used Auth0/SendGrid; Nusszopf uses Laravel-native mail
```

Every variable must have a documented default or an explicit "you must set this" note in `.env.example`, following Waffle Dashboard's `WAFFLE-INSTALLATION-GUIDE.md` pattern of naming exactly which lines a first-time operator has to edit (there: `APP_ENV`, `APP_DEBUG`, `APP_URL`, and optionally `APP_TIMEZONE`/`APP_LOCALE`).

## Installation (operator path)

**(needs approval — draft, modeled directly on `../foss-reference/waffle-dashboard/docs/WAFFLE-INSTALLATION-GUIDE.md`, which is the only reference of the three actually written for a third-party operator):**

1. `mkdir /opt/nusszopf && cd /opt/nusszopf`
2. Download the release's `docker-compose.yaml` and `.env.example` (renamed to `.env`).
3. Edit `.env`: set `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, database/Meilisearch credentials.
4. `docker network create nusszopf-network && docker compose up -d`
5. Generate and set `APP_KEY`, restart.
6. Create the first administrator account via a documented Artisan command (exact command Unknown until authentication/authorization archaeology is complete).
7. Verify health (see `docs/deployment/operations.md`).

## Reverse proxy and TLS

**Unknown / needs approval.** Two of three references (LCxHolz, Waffle Dashboard) document Nginx Proxy Manager as the TLS-terminating layer in front of the stack, reached over a shared external Docker network, with the application's own `web` service never publishing a host port once a domain is configured. Neither bundles it as a Compose service — it's presented as a separate, pre-existing or operator-installed stack. Nusszopf should likely follow the same pattern (document the shared external network + NPM as one option, Caddy/Traefik as alternatives) rather than bundling a reverse proxy, but this has not been decided.

## Persistent storage

Named volumes, one per stateful concern (never one shared "data" volume, so that restoring or wiping one never risks another — this separation is explicit in LCxHolz's backup documentation):

- `postgres-data` — database
- `laravel-storage` — uploaded files, generated files (invoices, exports — exact contents Unknown pending domain archaeology)
- `meilisearch-data` — search index (Nusszopf-original; rebuildable from Postgres via reindexing, so arguably lower backup priority than the database — see `docs/deployment/operations.md`)

No shared assets volume — see [above](#why-a-dedicated-nginx-image-not-a-shared-assets-volume).

## Health checks

Confirmed mechanisms from the references, all recommended for Nusszopf:

- `php-fpm-healthcheck` script (all three references) for `php-fpm`, `queue-worker`, `scheduler` images.
- `pg_isready` for `postgres` (all three references).
- `redis-cli ping` for `redis` (`laravel-docker-examples`, LCxHolz, Waffle's dev/prod compose — absent from Waffle's operator-facing file since it has no Redis health check wired, worth noting as a gap rather than copying it).
- Meilisearch's own `/health` HTTP endpoint — **no reference covers this**; this is a Nusszopf-original addition.

`depends_on: condition: service_healthy` should gate startup order end-to-end (`web` → `php-fpm` → `postgres`/`redis`/`meilisearch`), as in all three references.

## Backups, upgrades, recovery

See `docs/deployment/operations.md` for the full procedures. Summary of what each reference contributes:

- **Waffle Dashboard**: the only reference actually written as an operator-facing guide — `pg_dump`/`tar` via a cron-scheduled shell script, with a tested restore procedure. This is the baseline UX bar for Nusszopf.
- **LCxHolz**: a materially more mature mechanism (`spatie/laravel-backup`: encrypted archives, tiered retention, health-check monitoring, a restore procedure actually run end-to-end while implementing it) — the engineering-quality bar per `.claude/rules/05-engineering-quality.md`, but tied to MySQL/spatie's dumper in that project; Nusszopf would need the PostgreSQL equivalent.
- **Neither reference covers Meilisearch backup.** Meilisearch supports its own dump mechanism (`meilisearch --dump-dir`, `POST /dumps`); whether Nusszopf backs up the search index or always rebuilds it from Postgres on restore is an open question — see below.

## Open questions

Recorded for `docs/rewrite/architecture-decisions.md`:

1. Container registry: GHCR (LCxHolz's private-package pattern) vs. Docker Hub (Waffle Dashboard's public pattern) — Nusszopf, being FOSS, likely wants a public registry, but this hasn't been decided.
2. Whether to bundle a reverse proxy/TLS solution or document it as operator-owned (all evidence points to "operator-owned," but no reference makes this an explicit product decision — it's just what each project happened to do).
3. Whether Meilisearch's index is backed up or always rebuilt from PostgreSQL on restore.
4. Exact `.env` variable list and defaults — pending completion of domain/authentication/search archaeology.
5. First-admin-account bootstrap command — depends on Nusszopf's authentication implementation (Laravel-native, replacing Auth0 — see `docs/authentication/README.md`), not yet designed.
