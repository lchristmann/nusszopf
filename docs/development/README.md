# Development

This document defines Nusszopf's local development workflow. It is written to be **directly actionable once the Docker/Laravel skeleton exists** — every command below should become copy-pasteable in `README-DEV.md` without redesign. Until the skeleton exists, the commands are the *intended* canonical form (per `docs/references/lcxholz.md`), not yet verified against a running Nusszopf codebase.

See `docs/references/lcxholz.md` for the full evidence behind these choices and what was deliberately not copied from LCxHolz.

## Principle: Docker only, no host tooling

No local PHP, Composer, Node.js, PostgreSQL, Redis, or Meilisearch installation is required. Every one of these runs inside a container. The only host requirements are Docker, Docker Compose, and Git.

Claude Code (or any other AI assistant) runs on the host; the Laravel environment it operates on runs inside Docker. PHP/Composer/Artisan/Node/npm commands are always executed through `docker compose exec workspace ...`, never run directly on the host — the same convention LCxHolz uses, adopted for the same reason: one execution environment, consistent for every contributor and every AI session.

## Prerequisites

- Docker
- Docker Compose
- Git

## Getting started (first run)

A strictly ordered sequence, mirroring the LCxHolz onboarding shape:

1. Clone the repository.
2. `cp .env.example .env` and set `UID`/`GID` to match `id -u` / `id -g` (Linux bind-mount permissions).
3. `docker compose -f compose.dev.yaml up -d --build`
4. Enter the workspace container: `docker compose -f compose.dev.yaml exec workspace bash`
5. `composer install && php artisan key:generate`
6. `npm install && npm run dev`
7. `php artisan migrate && php artisan db:seed`
8. `php artisan storage:link`
9. Open the application and register a normal account through the registration screen — **there is
   no separate admin/first-user bootstrap step**. Nusszopf has no admin/staff role anywhere in the
   historical product (`docs/domain/entities.md`, "Entities confirmed absent"); every account is an
   ordinary equal-privilege user, in development exactly as in production. (Corrected during the
   pre-implementation specification review, 2026-09-18 — this step previously assumed an
   LCxHolz-style admin-bootstrap command that does not apply to Nusszopf's domain.)
10. Open the application at the documented local URL.

The exact compose service names and ports are placeholders until the Docker architecture (`docs/architecture/README.md`) is finalized. This section must be updated to match the real implementation before it is trusted as onboarding documentation.

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
| Browser/E2E tests | `npm run test:e2e` (or `:chromium`/`:firefox`/`:webkit` for a single engine) |
| Migrations | `php artisan migrate` |
| Fresh DB + seed | `php artisan migrate:fresh --seed` |
| Queue worker (dev) | `php artisan queue:listen` |
| Scheduler (dev) | `php artisan schedule:work` |
| Search reindex | `php artisan meilisearch:import` (or Laravel Scout's equivalent — command name to confirm once search integration is implemented) |
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

Claude Code should start every session by reading `CLAUDE.md`, everything under `.claude/rules/`, the relevant `docs/*` topic files, and — for any product/domain/UI question — the historical evidence under `../historical/`. Laravel Boost (target stack per `CLAUDE.md`) should be configured the same way LCxHolz configures it: committed `.claude/`, `.mcp.json`, `boost.json`, with the MCP server proxied through the `workspace` container so it observes the same PHP/Composer state as manual commands, and scoped only to packages Nusszopf actually installs.

## Troubleshooting

To be filled in as real failure modes are discovered during implementation (file permission mismatches on Linux, Vite manifest errors, stale caches). Do not invent troubleshooting entries speculatively — only document problems that have actually been hit.
