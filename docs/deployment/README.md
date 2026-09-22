# Deployment and Self-Hosting

> **Status: implemented and verified (operational track O-1/O-2, 2026-09-22).** The install path, the images, the
> release workflow and the health checks below exist and are exercised by `scripts/smoke-test.sh` (run by CI on every
> change). Not yet done, by roadmap: a real tag has never been published (the release workflow is untested until the
> first one), the backup scripts and restore drill (P-10), and the Playwright suite against these images (P-7).

## Table of contents

- [Design goal](#design-goal)
- [The files](#the-files)
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

## The files

| File | Audience | What it is |
|---|---|---|
| `docker-compose.yaml` (repository root, attached to every release) | Operators | The whole stack, `image:`-only: no build context, nothing to compile. Which release runs is `NUSSZOPF_VERSION` in `.env` |
| `.env.production.example` (attached to every release with its version filled in) | Operators | Every setting of a production installation, the ones that must be set marked `REQUIRED` |
| `scripts/install.sh` (attached to every release) | Operators | Downloads the two files above, generates `APP_KEY` and the database, search and health secrets, writes `.env` |
| `compose.prod.yaml` | Contributors / CI | An *override* that only adds `build:` to `web` and `php-fpm`: `docker compose -f docker-compose.yaml -f compose.prod.yaml up -d --build` runs the working copy's own images in the operator's stack |
| `compose.dev.yaml` | Contributors | Bind-mounted source, Xdebug, a `workspace` sidecar for Composer/Node/Artisan, the Vite dev server, Playwright |
| `scripts/smoke-test.sh` | Contributors / CI | Builds both images, installs into a clean directory with `install.sh`, starts the stack and checks it end to end |

Following Waffle Dashboard, the operator file is a separate, curl-able, image-only artifact; unlike Waffle, a single `.env`
value pins the release, so an upgrade is one line (see `docs/deployment/operations.md`).

## Services

| Service | Image | Role |
|---|---|---|
| `web` | `ghcr.io/lchristmann/nusszopf-web` — nginx built `FROM` the application image's own assets | HTTP; serves static assets, passes PHP to `php-fpm`. The only published port (`APP_BIND`:`APP_PORT`) |
| `php-fpm` | `ghcr.io/lchristmann/nusszopf-php-fpm` (PHP 8.5-FPM, Laravel 13, Livewire 4, non-root) | Request handling. Its entrypoint refuses to start without `APP_KEY`, runs `migrate --force --isolated`, then warms the config, route, view and event caches |
| `queue-worker` | same application image | `queue:work --tries=5 --backoff=10,30,60,120`: background jobs (search indexing, contact-form mail). Healthy while it processes the scheduler's heartbeat job |
| `scheduler` | same application image | `schedule:work`. Its only tasks are the two heartbeats (`routes/console.php`) — the historical product had no periodic work. Healthy while its heartbeat is fresh |
| `postgres` | `postgres:16-alpine` | Primary datastore |
| `redis` | `redis:alpine` | Sessions, cache, queue |
| `meilisearch` | `getmeili/meilisearch:v1.11`, `MEILI_ENV=production`, master key from `MEILISEARCH_KEY` | Search index — derived data, rebuilt with `search:reindex` |
| `workspace` (dev only) | Node + Composer + CLI tools | Contributor shell |

`queue-worker` and `scheduler` wait for `php-fpm` to be healthy, i.e. for the migrations to have finished; nothing else migrates.
There is deliberately no bundled reverse proxy or mail server — see [Reverse proxy and TLS](#reverse-proxy-and-tls) and `docs/email/README.md`.

## Why a dedicated nginx image, not a shared assets volume

`laravel-docker-examples`' production Compose file shares a named volume between `web` and `php-fpm` to keep Vite's `manifest.json` and hashed filenames consistent between the two containers. This has a real, demonstrated flaw: Docker only seeds a new named volume from an image the first time it's created, so every redeploy after the first leaves stale assets in place unless something actively re-syncs the volume on every boot (full evidence in `docs/references/laravel-docker-examples.md` §4).

LCxHolz papers over this with a custom nginx entrypoint that deletes and re-copies assets into the volume on every boot. Nusszopf instead follows **Waffle Dashboard's** structurally simpler approach: build Vite assets once, inside the application image's own multi-stage `Dockerfile` (no separate Node install in the nginx build), then build the nginx image `FROM` the application stage and `COPY --from=php-fpm /var/www/public /var/www/public`. Because both images are produced from the same source tree at the same version, the manifest can never drift, and no shared volume or runtime re-sync step is needed at all. The smoke test checks that a built stylesheet is served.

## Required configuration

`.env.production.example` is the source of truth — every variable with its default or a `REQUIRED` marker (release, `APP_KEY`, `APP_URL`, `DB_PASSWORD`, `MEILISEARCH_KEY`).
`install.sh` generates the secrets. Notable choices: `APP_ENV=production`, `APP_DEBUG=false`; sessions, cache and queue on Redis; `SCOUT_QUEUE=true` so search sync is a retried
job, never fire-and-forget (BUG-009); `LOG_CHANNEL=stderr` so `docker compose logs` shows the application's log; `SESSION_LIFETIME=480`, the historical 8-hour rolling session;
`TRUSTED_PROXIES`, `APP_BIND`, `APP_PORT` for the proxy setup below; `HEALTH_TOKEN` for `/health` details; `MAIL_*`, an operator-supplied SMTP relay for the project contact form (`docs/email/README.md`; the dev/CI stack uses a bundled Mailpit catcher instead, never production).

### Location search (`LOCATIONIQ_KEY`)

Choosing a fixed location for a project (the wizard's and edit screen's "Ortsgebunden") uses the
[LocationIQ](https://locationiq.com) autocomplete API, as the historical product did (German
cities, towns and villages only). LocationIQ has a free tier; create a key and set
`LOCATIONIQ_KEY`. The request is made by the server, so the key is never sent to visitors' browsers.
Without a key the place search shows no suggestions and a project cannot be given a fixed
location — "Ortsunabhängig" projects are unaffected. `LOCATIONIQ_URL` (default the LocationIQ
endpoint) exists only to point at a compatible service; the development/CI Compose stack points it at
its bundled `locationiq-stub`.

### Google login (`GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`)

Google login (docs/authentication/README.md §3) needs an OAuth 2.0 client from the
[Google Cloud Console](https://console.cloud.google.com/apis/credentials) with an authorized redirect
URI of `<APP_URL>/auth/google/callback`. Without both variables set, the "Google" button on the login
screen is hidden and its routes 404 — password/username login is completely unaffected.
`GOOGLE_REDIRECT_URI` only needs setting if the app is reachable at a different URL than `APP_URL`
(e.g. behind a path-rewriting proxy).

The list will grow (not shrink) as newsletter/object-storage land in later slices; every variable keeps a default or an explicit `REQUIRED` note.

## Installation (operator path)

What you need: a Linux host with Docker (with the Compose plugin), `curl` and `openssl`; a domain name pointing at it if the site is public. Nothing else is installed on the host.

```sh
mkdir /opt/nusszopf && cd /opt/nusszopf
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh
sh install.sh https://nusszopf.example.org          # or: sh install.sh https://nusszopf.example.org 0.1.0
```

`install.sh` downloads the release's `docker-compose.yaml` and `.env.production.example`, writes `.env` with freshly generated secrets
(`APP_KEY`, `DB_PASSWORD`, `MEILISEARCH_KEY`, `HEALTH_TOKEN`; mode 600), and refuses to overwrite an existing `.env`. Then:

1. Optionally edit `.env` — `MAIL_*`, `LOCATIONIQ_KEY`, `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`, `APP_BIND=127.0.0.1` when a reverse proxy runs on this host. Everything a first-time operator *must* set is marked `REQUIRED` in the file, and Compose refuses to start with a clear message if one is missing.
2. `docker compose up -d` — the first start pulls the images, waits for PostgreSQL, Redis and Meilisearch, migrates the database and starts everything.
3. `docker compose ps` — every service `healthy` (the queue worker and scheduler need up to a few minutes, they prove themselves with a heartbeat per minute).
4. `docker compose exec php-fpm php artisan nusszopf:health` — the version and every dependency `ok`.
5. Open the app and register a normal account through the ordinary registration screen — **there is
   no separate "first administrator" bootstrap step and no Artisan command for this.** Nusszopf's domain
   archaeology **confirms no admin/staff role exists anywhere in the historical product**
   (`docs/domain/entities.md`, "Entities confirmed absent"; `docs/rewrite/decisions-register.md`,
   "Explicitly not open") — every account is an ordinary equal-privilege user.

Doing it by hand instead of `install.sh`: download `docker-compose.yaml` and `.env.production.example` (renamed `.env`) from the
release, set the `REQUIRED` lines, and generate the key with
`docker compose run --rm --no-deps --entrypoint php php-fpm artisan key:generate --show` (`.env` must contain `NUSSZOPF_VERSION`
and the database/search passwords first, Compose reads them).

## Reverse proxy and TLS

**Decided** (`docs/rewrite/architecture-decisions.md`): no reverse proxy is bundled. The stack publishes one port (`web`, default `8080`);
put whatever terminates TLS for your other services in front of it — Nginx Proxy Manager, Caddy or Traefik — and proxy your domain to
`http://<host>:8080`. For a proxy on the same host set `APP_BIND=127.0.0.1` so only it can reach the port.

The application must be told to believe the proxy about the original scheme and address: `TRUSTED_PROXIES=*` (or a comma-separated list of proxy
addresses) in `.env`, `APP_URL` with `https://`, and `SESSION_SECURE_COOKIE=true` — all preset in `.env.production.example`. Without
`TRUSTED_PROXIES` links and redirects would use `http://`. Caddy needs only `nusszopf.example.org { reverse_proxy 127.0.0.1:8080 }`.

## Persistent storage

Named volumes, one per stateful concern (never one shared "data" volume, so that restoring or wiping one never risks another — this separation is explicit in LCxHolz's backup documentation):

- `postgres-data` — database
- `laravel-storage` — uploaded files, generated files (invoices, exports — exact contents Unknown pending domain archaeology)
- `meilisearch-data` — search index (Nusszopf-original; rebuildable from Postgres via reindexing, so arguably lower backup priority than the database — see `docs/deployment/operations.md`)

No shared assets volume — see [above](#why-a-dedicated-nginx-image-not-a-shared-assets-volume).

## Health checks

Three layers, all verified by the smoke test:

- **Container health** (`docker compose ps`): `php-fpm` answers PHP-FPM's ping; `web` fetches `/up`; `postgres` `pg_isready`; `redis` `redis-cli ping`; `meilisearch` `/health`;
  `queue-worker` and `scheduler` run `php artisan nusszopf:health --only=queue|scheduler`, which passes while the heartbeat (written by a scheduled task every minute; for the queue by a job
  the worker must process) is at most three minutes old. `depends_on: condition: service_healthy` orders the start (databases → `php-fpm` → `web`, `queue-worker`, `scheduler`).
- **`/up`**: Laravel's liveness probe — 200 while the application boots. Touches no dependency.
- **`/health`**: 200 `{"status":"ok"}` or 503 `{"status":"degraded"}`; it starts no session, so it still answers while Redis is down. With `HEALTH_TOKEN` set, a request with
  `Authorization: Bearer <token>` also gets the version and each check (`database`, `redis`, `search`, `scheduler`, `queue`) with its reason. Point an uptime monitor at it.
- **`php artisan nusszopf:health`** — the same from the shell, with the running version; exit code 1 when a check fails. `php artisan about` shows the version too, and `docker image inspect` the `org.opencontainers.image.version` label.

The version is the Git tag: the release workflow passes it as the `NUSSZOPF_VERSION` build argument (there is no hand-maintained version file); an image built from a working copy reports `dev`.

## Backups, upgrades, recovery

See `docs/deployment/operations.md` for the full procedures. Summary of what each reference contributes:

- **Waffle Dashboard**: the only reference actually written as an operator-facing guide — `pg_dump`/`tar` via a cron-scheduled shell script, with a tested restore procedure. This is the baseline UX bar for Nusszopf.
- **LCxHolz**: a materially more mature mechanism (`spatie/laravel-backup`: encrypted archives, tiered retention, health-check monitoring, a restore procedure actually run end-to-end while implementing it) — the engineering-quality bar per `.claude/rules/05-engineering-quality.md`, but tied to MySQL/spatie's dumper in that project; Nusszopf would need the PostgreSQL equivalent.
- **Neither reference covers Meilisearch backup.** Meilisearch supports its own dump mechanism (`meilisearch --dump-dir`, `POST /dumps`); whether Nusszopf backs up the search index or always rebuilds it from Postgres on restore is an open question — see below.

## Open questions

Resolved by the operational track (`docs/rewrite/architecture-decisions.md`): container registry (GHCR, `ghcr.io/lchristmann/nusszopf-*`), reverse proxy (operator-owned), health depth
(own checks, no extra dependency), environment variables (`.env.production.example`), first administrator (none exists).

Still open: whether Meilisearch's index is ever backed up rather than rebuilt (the documented answer is *rebuilt*, `search:reindex`), and the backup scripts themselves (phase P-10).
