# Laravel Docker Examples — Reference Findings

Source: the public repository [`rw4lll/laravel-docker-examples`](https://github.com/rw4lll/laravel-docker-examples), read at the revision recorded in `docs/rewrite/source-map.md`. Nusszopf's Docker setup began from this base.

This repository is a **Docker implementation reference only**. It contributes no Nusszopf product/domain behavior. Everything below is either **Confirmed** (directly read from the repository) or explicitly marked **Proposal** (this document's own recommendation for Nusszopf, not yet approved).

## 1. Repository structure (Confirmed)

```
docker/
  common/php-fpm/Dockerfile          # multi-stage: builder → production → development
  common/php-fpm/conf.d/20-status-path.conf
  development/nginx/nginx.conf
  development/php-fpm/entrypoint.sh
  development/workspace/Dockerfile   # CLI/Node sidecar for local dev
  production/nginx/Dockerfile        # multi-stage: builds Vite assets, then nginx:alpine
  production/nginx/nginx.conf
  production/php-fpm/entrypoint.sh
compose.dev.yaml
compose.prod.yaml
.env.example
```

Two Compose files, not three: `compose.dev.yaml` for local development, `compose.prod.yaml` for building/running the production stack. There is no separate "published image, pull-only" compose file in this repository — see §5 for why Nusszopf still needs one.

## 2. Development environment (Confirmed)

- `web` (nginx:latest, stock image) + custom `nginx.conf` bind-mounted read-only. Proxies `.php` to `php-fpm:9000`.
- `php-fpm`: built from `docker/common/php-fpm/Dockerfile`, target `development`. Runs as a UID/GID-mapped user (`${UID:-1000}:${GID:-1000}`) so files written from inside the container are owned by the host developer. Application code is bind-mounted (`./:/var/www`), not baked into the image — this is what makes it "development."
- `workspace`: a sidecar CLI container (Composer, Node via NVM, Xdebug) used for `composer install`, `npm run dev`, Artisan commands, tests. Nothing in `web`/`php-fpm` needs Node.
- `postgres:16` and `redis:alpine`, both with no healthchecks in the dev file (only `service_started` dependency conditions).
- Xdebug is wired via build ARGs (`XDEBUG_ENABLED`, `XDEBUG_MODE`, `XDEBUG_HOST`, `XDEBUG_IDE_KEY`) baked into both `php-fpm` and `workspace` images at the `development` build target.
- Dev entrypoint (`docker/development/php-fpm/entrypoint.sh`) only clears caches (`config:clear`, `route:clear`, `view:clear`) before handing off to the container's `CMD` — it deliberately does **not** run migrations, unlike production.

## 3. Production environment (Confirmed)

- `php-fpm/Dockerfile` (`docker/common/php-fpm/Dockerfile`) is a 3-stage build:
  1. `builder` (`php:8.5-fpm`): installs PHP extensions (`pdo_pgsql`, `pdo_mysql`, `intl`, `zip`, `bcmath`, `soap`, `redis` via PECL), copies the full app, runs `composer install --no-dev --optimize-autoloader`.
  2. `production` (`php:8.5-fpm`): installs only runtime libs, copies PHP extensions/php.ini from `builder`, switches to `php.ini-production`, copies the app + vendor from `builder`, `chown`s to `www-data`, runs as `www-data`, entrypoint `docker/production/php-fpm/entrypoint.sh`.
  3. `development` (`FROM production`): adds Xdebug and a UID/GID-mapped `www` user on top of the production image, so dev and prod share every layer below that point.
- `nginx/Dockerfile` (`docker/production/nginx/Dockerfile`) is a **separate** 2-stage build: a `debian` stage installs Node/npm and runs `npm install && npm run build`, then `nginx:alpine` copies only `public/` (including the built `public/build/` Vite output) from that stage. `php-fpm`'s own image never runs `npm run build` — it has no Node at all.
- Health checks (Confirmed, `compose.prod.yaml`): `php-fpm` via `php-fpm-healthcheck` (downloaded from `renatomefi/php-fpm-healthcheck`, requires `libfcgi-bin`/`procps`), `postgres` via `pg_isready`, `redis` via `redis-cli ping`. `depends_on: condition: service_healthy` gates startup order (`web` waits on `php-fpm`, `php-fpm` waits on `postgres`).
- Production entrypoint (`docker/production/php-fpm/entrypoint.sh`): seeds `/var/www/storage` from a `storage-init` snapshot **only if the mounted volume is empty**, then unconditionally runs `php artisan migrate --force`, `config:cache`, `route:cache` on every boot.
- Persistence: three named volumes — `postgres-data-production`, `laravel-storage-production` (mounted `:ro` into `web`, read-write into `php-fpm`), `laravel-public-assets`.
- No reverse-proxy/TLS story at all — the README explicitly says HTTPS "is recommended" but out of scope; `web` binds host port 80 directly.
- No queue worker or scheduler service in `compose.prod.yaml` — `QUEUE_CONNECTION=redis` is configured in `.env.example` but nothing runs `queue:work` or `schedule:work` in production. Confirmed gap, not a Nusszopf-specific finding.

## 4. A genuine defect in this reference: the shared asset volume (Confirmed, via git history)

`compose.prod.yaml` mounts a **named** volume `laravel-public-assets` at `/var/www/public/build` in both `web` (`:ro`) and `php-fpm` (read-write), with the comment "ensure the manifest.json and hashed files match between Nginx and PHP-FPM." Commit `793dafd` ("fix assets hash mismatch for production build") added exactly this — and nothing else.

The problem: Docker only seeds a brand-new named volume from an image's directory the *first* time that volume is created. On every redeploy after that, the volume keeps whatever was in it from the previous image, silently shadowing the new image's freshly built `public/build/` — and neither image here ever writes into that path at runtime to refresh it (`php-fpm`'s image never builds assets at all; `nginx`'s Dockerfile builds them into its own image layer, not into the shared volume). The mount as written will serve stale (or, on the very first `up`, empty) assets on every deploy after the first. This is a defect in the reference project itself, not a Nusszopf historical bug — noted here so Nusszopf's own design (§6) does not reproduce it.

**Nusszopf's fix.** Avoid the shared-volume mechanism entirely. Asset building happens **once**, inside the `php-fpm` image's own multi-stage build, and the nginx image is built `FROM` that exact application image and simply copies `public/` out of it. Because both images come from the same source tree at the same version tag, their `public/build/manifest.json` is byte-identical by construction: there is no volume, no runtime re-sync step and no possibility of drift.

## 5. What Nusszopf does differently

| Aspect | laravel-docker-examples | Nusszopf (as built) |
|---|---|---|
| Compose files | `compose.dev.yaml` and `compose.prod.yaml`, builds locally | A `compose.dev.yaml`/`compose.prod.yaml` pair for contributors, **and** a standalone, `image:`-only `docker-compose.yaml` for operators who never clone the repository |
| Asset/manifest consistency | Shared volume, unpopulated on redeploy (defect, §4) | Nginx image built `FROM` the matching php-fpm image, no volume needed; verified by the smoke test |
| Queue/scheduler | Not present | Dedicated `queue-worker` and `scheduler` services, same image as php-fpm, different `command:` (search indexing, mail) |
| Health checks | `php-fpm-healthcheck`, `pg_isready`, `redis-cli ping` | The same three, plus Meilisearch's `/health`, and heartbeat checks for the queue worker and the scheduler |
| TLS/reverse proxy | Out of scope, host port 80 exposed directly | Operator-owned, not bundled (decided); one published port, documented for Caddy, Traefik and Nginx Proxy Manager |
| Backups | None | A cron-run shell script for the database, the storage volume and the installation directory, with a drilled restore; the search index is rebuilt, never backed up (`docs/handbuch/backup.md`) |
| Registry | N/A (not published) | GHCR, `ghcr.io/lchristmann/nusszopf-*`, version tag plus `latest` (`docs/release/docker-images.md`) |

## 6. Recommendation for Nusszopf (adopted; this is what was built)

The simplest architecture that satisfies Nusszopf's actual requirements (PHP 8.5, Laravel 13, PostgreSQL, Redis, Meilisearch, Blade/Livewire — no separate SPA build pipeline beyond Vite):

1. **Single application image**, built multi-stage like `docker/common/php-fpm/Dockerfile` here, but with the Node/`npm run build` step folded into its own `builder` stage, producing one image that contains both the compiled PHP app and the compiled Vite assets under `public/build/`.
2. **A thin nginx image built `FROM` that exact application image tag**, copying only `public/` out of it — never a shared named volume for assets. This structurally eliminates the defect in §4 rather than working around it.
3. **Dedicated `queue-worker` and `scheduler` services** using the same application image with a different `command:` — Nusszopf has background work (search indexing, mail) that `compose.prod.yaml` here has no equivalent for.
4. **Health checks** for every service that can be checked (`php-fpm-healthcheck`, `pg_isready`, `redis-cli ping`, plus Meilisearch's `/health` endpoint), gating `depends_on: condition: service_healthy` throughout.
5. **Two audiences, two Compose files**: a `compose.dev.yaml`/`compose.prod.yaml` pair for people building/testing the images themselves, and a standalone, curl-able `docker-compose.yaml` at the repository root for operators who only ever pull published images — see `docs/handbuch/installation.md`.

Decided since: GHCR as the registry, no Meilisearch backup (the index is rebuilt from PostgreSQL), and an operator-owned reverse proxy with Nginx Proxy Manager, Caddy and Traefik named, and Caddy given a one-line example (`docs/rewrite/decisions-register.md`).
