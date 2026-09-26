# Development

This document defines Nusszopf's local development workflow. The Docker/Laravel skeleton now exists (first vertical slice, 2026-09-18) and every command below has been verified against a running Nusszopf codebase — `README-DEV.md` is the copy-pasteable, kept-in-sync version of this same command set.

See `docs/references/lcxholz.md` for the full evidence behind these choices and what was deliberately not copied from LCxHolz.

## Principle: Docker only, no host tooling

No local PHP, Composer, Node.js, PostgreSQL, Redis, or Meilisearch installation is required. Every one of these runs inside a container. The only host requirements are Docker, Docker Compose, and Git.

Claude Code (or any other AI assistant) runs on the host; the Laravel environment it operates on runs inside Docker. PHP/Composer/Artisan/Node/npm commands are always executed through `docker compose exec workspace ...`, never run directly on the host — the same convention LCxHolz uses, adopted for the same reason: one execution environment, consistent for every contributor and every AI session.

## Prerequisites

- Docker
- Docker Compose
- Git

## Getting started (first run)

The copy-pasteable sequence is in `README-DEV.md`, "Getting started (first run)"; this is the order and the reason for
each step:

1. Clone the repository.
2. `cp .env.example .env`, set `UID`/`GID` to match `id -u` / `id -g` (Linux bind-mount permissions), put the
   development master key into `MEILISEARCH_KEY` and generate `APP_KEY` **before** starting the stack. Compose hands
   `.env` to the containers as their environment, so an empty `APP_KEY` or `MEILISEARCH_KEY` there wins over anything
   written to the file later (`README-DEV.md` shows the two `sed` lines).
3. `docker compose -f compose.dev.yaml up -d --build`
4. Give your user the `vendor` and `node_modules` volumes (new named volumes belong to root): `exec -u root workspace chown`.
5. In the `workspace` container (`docker compose -f compose.dev.yaml exec workspace bash`): `composer install`,
   `npm install`, `php artisan storage:link`, `php artisan migrate --seed`, `php artisan search:reindex`, `npm run build`
   (or `npm run dev` for the Vite dev server).
6. Open the application at <http://localhost:8080> and register a normal account through the registration
   screen — **there is no separate admin/first-user bootstrap step**. Nusszopf has no admin/staff role anywhere in
   the historical product (`docs/domain/entities.md`, "Entities confirmed absent"); every account is an ordinary
   equal-privilege user, in development exactly as in production. Mail goes to the Mailpit catcher at
   <http://localhost:8025>.

The compose service names (`web`, `php-fpm`, `workspace`, `queue-worker`, `scheduler`, `postgres`, `redis`,
`meilisearch`, `mailpit`, `locationiq-stub`, `playwright`) and the default `APP_PORT=8080` are the real implementation.

## One canonical command per concern

| Concern | Command (inside `workspace`, unless noted) |
|---|---|
| Enter dev container | `docker compose -f compose.dev.yaml exec workspace bash` |
| Artisan | `php artisan <command>` |
| Composer | `composer <command>` |
| Frontend dev server | `npm run dev` |
| Frontend build | `npm run build` |
| Formatting (apply) | `composer pint` |
| Formatting (check, matches CI) | `composer lint:check` |
| Static analysis | `composer larastan` |
| Unit/Feature tests | `composer test` |
| Browser/E2E tests | `npm run test:e2e` (or `:chromium`/`:firefox`/`:webkit` for a single engine, `:devices` for the emulated phones and tablet; inside the `playwright` container, `docker compose -f compose.dev.yaml exec playwright npx playwright test`) |
| Migrations | `php artisan migrate` |
| Fresh DB + seed | `php artisan migrate:fresh --seed` |
| Queue worker (dev) | already running as the `queue-worker` Compose service (`queue:listen`) |
| Scheduler (dev) | already running as the `scheduler` Compose service (`schedule:work`) |
| Sync search index settings | `php artisan scout:sync-index-settings` |
| Full search reindex (also applies the index settings) | `php artisan search:reindex` |
| Production stack smoke test (builds both images, installs and starts the operator stack, checks it; needs Docker on the host, not the workspace) | `sh scripts/smoke-test.sh` |
| Upgrade test (installs an earlier release, fills it, upgrades it to the working copy, checks nothing was lost; host Docker) | `sh scripts/upgrade-test.sh <previous tag> [--suite]` |
| Backup/restore drill (backs up from cron with the documented script, restores onto an empty Docker host, and rolls back an upgrade; privileged `docker:dind`) | `sh scripts/restore-test.sh [--suite] [--rollback-from <previous tag>]` |
| Search index recovery drill (loses the index four ways, recovers it with the documented blocks, compares every search answer; privileged `docker:dind`) | `sh scripts/search-recovery-test.sh` |
| Queue and scheduler drill (restarts and kills the worker, Redis and the scheduler with work waiting, fails jobs through all their retries, breaks `search:reindex` three ways; privileged `docker:dind`, about an hour) | `sh scripts/queue-scheduler-test.sh` |
| Real mail through the production stack (sends seven real messages; a release step) | `P13_RECIPIENT=you@example.org sh scripts/mail-delivery-test.sh` |
| Logs | `docker compose -f compose.dev.yaml logs -f [service]` |

This table must stay in sync with `composer.json`/`package.json` scripts as they are implemented — a command listed here that no longer exists, or an implemented script missing from here, is a documentation bug.

## Recommended before opening a pull request

Run the same three checks CI runs, in this order (fail fast on the cheapest checks first):

```shell
docker compose -f compose.dev.yaml exec workspace composer lint:check
docker compose -f compose.dev.yaml exec workspace composer larastan
docker compose -f compose.dev.yaml exec workspace composer test
```

See `docs/development/quality.md` for what each gate checks and why, and `docs/testing/README.md` for the full testing strategy.

## Claude Code / AI-assisted development

Claude Code should start every session by reading `CLAUDE.md`, everything under `.claude/rules/`, the relevant `docs/*` topic files, and — for any product/domain/UI question — the historical evidence under `../historical/`. Laravel Boost (target stack per `CLAUDE.md`, "where useful") is **not installed**: there is no `laravel/boost` in `composer.json`, no `.mcp.json` and no `boost.json`. If it is added, configure it the way LCxHolz does: the MCP server proxied through the `workspace` container so it observes the same PHP/Composer state as manual commands, and scoped only to packages Nusszopf actually installs. What is committed today is `CLAUDE.md` and `.claude/rules/`.

## Troubleshooting

`README-DEV.md`, "Troubleshooting", lists the problems that have actually been hit (volume ownership, the Meilisearch key, the late `APP_KEY`, a `.env` change that needs a recreate, the shared rate-limit budget). Add to that table, and only with problems that were really hit: do not invent entries speculatively. Operating problems of an installed stack are in `docs/deployment/operations.md`, "Troubleshooting".
