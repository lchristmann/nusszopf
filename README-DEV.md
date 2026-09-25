# Nusszopf — Development Guide

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Blade](https://img.shields.io/badge/Blade-Template-F05340?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-Cache_%26_Queue-FF4438?logo=redis&logoColor=white)
![Meilisearch](https://img.shields.io/badge/Meilisearch-1.11-FF5CAA?logo=meilisearch&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-5-8A4182?logo=php&logoColor=white)
![Playwright](https://img.shields.io/badge/Playwright-E2E_Testing-2EAD33?logo=playwright&logoColor=white)
![Larastan](https://img.shields.io/badge/Larastan-Level_7-4F5B93?logo=php&logoColor=white)
![Laravel Pint](https://img.shields.io/badge/Laravel_Pint-Code_Style-FF2D20?logo=laravel&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-CI/CD-2088FF?logo=githubactions&logoColor=white)

## Requirements

- Docker
- Docker Compose
- Git

No local PHP, Composer, Node, PostgreSQL, Redis, or Meilisearch installation is required. Every one of
these runs inside a container; PHP/Composer/Artisan/Node/npm commands always run via
`docker compose -f compose.dev.yaml exec workspace ...`.

## Getting started (first run)

```shell
git clone <repo> && cd nusszopf
cp .env.example .env               # edit UID/GID below if not 1000:1000
docker compose -f compose.dev.yaml up -d --build
docker compose -f compose.dev.yaml exec workspace composer install
docker compose -f compose.dev.yaml exec workspace npm install
docker compose -f compose.dev.yaml exec workspace php artisan key:generate
docker compose -f compose.dev.yaml exec workspace php artisan migrate --seed
docker compose -f compose.dev.yaml exec workspace php artisan scout:sync-index-settings
docker compose -f compose.dev.yaml exec workspace npm run build
```

Open <http://localhost:8080> (or your `APP_PORT`) and register a normal account through the
registration screen — there is no separate admin/first-user bootstrap step. Nusszopf has no
admin/staff role anywhere in the historical product; every account is an ordinary equal-privilege
user, in development exactly as in production.

If your host UID/GID aren't `1000:1000`, set `UID`/`GID` in `.env` to match `id -u`/`id -g` before the
first `up`, so files the containers create are owned by you, not root.

## Common commands

| Concern | Command |
|---|---|
| Enter dev container | `docker compose -f compose.dev.yaml exec workspace bash` |
| Artisan | `docker compose -f compose.dev.yaml exec workspace php artisan <command>` |
| Composer | `docker compose -f compose.dev.yaml exec workspace composer <command>` |
| Frontend dev server | `docker compose -f compose.dev.yaml exec workspace npm run dev` |
| Frontend build | `docker compose -f compose.dev.yaml exec workspace npm run build` |
| Formatting (apply) | `composer pint` |
| Formatting (check, matches CI) | `composer lint:check` |
| Static analysis | `composer larastan` |
| Unit/Feature tests | `composer test` |
| Browser/E2E tests | `docker compose -f compose.dev.yaml exec playwright npx playwright test` (or `npm run test:e2e:chromium`/`:firefox`/`:webkit` for one engine, `npm run test:e2e:devices` for the emulated phones and tablet) |
| Production images: operator checks | `sh scripts/smoke-test.sh` (builds the images, installs, starts, checks) |
| Production images: whole browser suite | `sh scripts/prod-e2e.sh` (the same stack plus Mailpit and the LocationIQ stub; `PROD_E2E_KEEP=1` keeps it running) |
| Upgrade from an earlier release, on populated data | `sh scripts/upgrade-test.sh <previous tag> [--suite]` (a release step, not CI; `docs/testing/README.md`) |
| Migrations | `php artisan migrate` |
| Fresh DB + seed | `php artisan migrate:fresh --seed` |
| Sync search index settings | `php artisan scout:sync-index-settings` |
| Rebuild the search index (settings + all documents) | `php artisan search:reindex` |
| Queue worker (dev) | already running as the `queue-worker` Compose service |
| Scheduler (dev) | already running as the `scheduler` Compose service |
| Logs | `docker compose -f compose.dev.yaml logs -f [service]` |

Composer/Artisan commands in the table run inside `workspace` — prefix with
`docker compose -f compose.dev.yaml exec workspace` if not already inside the container shell.

## Services (`compose.dev.yaml`)

`web` (nginx) · `php-fpm` · `workspace` (contributor shell) · `queue-worker` · `scheduler` ·
`postgres` · `redis` · `meilisearch` · `locationiq-stub` (dev/CI stand-in for the project-location autocomplete API; set `LOCATIONIQ_KEY`/`LOCATIONIQ_URL` in `.env` to use the real one) · `playwright` (Debian-based, dev/CI-only — the app's own
Alpine-based images can't run Playwright's bundled browsers reliably).

## Testing

Run the complete local quality suite before opening a pull request, in this order:

```shell
docker compose -f compose.dev.yaml exec workspace composer lint:check
docker compose -f compose.dev.yaml exec workspace composer larastan
docker compose -f compose.dev.yaml exec workspace composer test
docker compose -f compose.dev.yaml exec playwright npx playwright test
```

See `docs/testing/README.md` for the full testing strategy and `docs/development/quality.md` for
what each gate checks.

## Claude Code

Claude Code should start by reading `CLAUDE.md`, `.claude/rules/*`, relevant `docs/*`, and historical
evidence under `../historical/`.

Reference projects:

- `../development-reference/lcxholz`
- `../foss-reference/waffle-dashboard`
- `../infrastructure-reference/laravel-docker-examples`

## Development principle

Nusszopf's product behavior comes from historical Nusszopf.

Its developer experience should be familiar to LCxHolz.

Its FOSS lifecycle should learn from Waffle Dashboard.

Its Docker foundation should learn from the official Laravel Docker examples.
