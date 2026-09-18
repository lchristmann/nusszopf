# Quality Gates

The goal, per `CLAUDE.md`, is engineering discipline at the LCxHolz level: **CI catches exactly what a developer can catch locally, using the same commands.** No gate should exist only in CI and not be runnable on a developer's machine, and no local convention should exist that CI doesn't also enforce.

Full evidence and rationale for each choice: `docs/references/lcxholz.md`. This file defines the gates themselves; `docs/testing/README.md` defines the testing strategy in more depth.

## The gates

| Gate | Tool | Local command | What it catches |
|---|---|---|---|
| Formatting | Laravel Pint | `composer lint:check` (apply: `composer pint`) | Code style drift, non-PSR-12 formatting |
| Static analysis | Larastan (PHPStan) | `composer larastan` | Type errors, undefined methods/properties, dead code, unsafe nullability — Laravel-aware |
| Unit tests | Pest | `composer test` (unit portion) | Isolated business-rule/logic correctness |
| Feature tests | Pest | `composer test` (feature portion) | HTTP/Livewire behavior, validation, persistence, authorization, jobs, mail, search integration |
| Browser/E2E tests | Playwright | `npm run test:e2e` | Real-browser user journeys against the running app |
| Docker build | Docker Buildx | `docker compose -f compose.prod.yaml build` | Production image actually builds from a clean checkout |

## Static analysis level

LCxHolz runs Larastan at **level 7** (Confirmed, `phpstan.neon`). Nusszopf should start at the same level and only lower it with a documented, specific reason (recorded in `docs/rewrite/architecture-decisions.md`) — not as a default escape hatch for unresolved errors.

## Coverage

Coverage is measured and reported (Cobertura + HTML, uploaded to a coverage service, surfaced as a badge) but is **not** a hard merge-blocking threshold in the LCxHolz reference (`fail_below_min: false`). Nusszopf should follow the same default — observe coverage, don't gate on an arbitrary percentage — unless a specific reason to change this is documented.

## What CI must mirror exactly

Every gate above must be expressible as a single Composer/npm script that a developer runs locally and CI runs unmodified (`composer lint:check`, `composer larastan`, `composer test`, `npm run test:e2e`). If CI ever needs a flag or environment variable a local run doesn't also document, that is a discipline gap to fix, not a CI-only detail to leave undocumented.

## Regression discipline

Every historical bug that is knowingly fixed during the rewrite (per `CLAUDE.md`'s "when correcting historical behavior" rule) must ship with a regression test in the same change, and the decision must be recorded in `docs/rewrite/intentional-changes.md`. A fix without a regression test is incomplete, not merely undertested.

## Database-specific testing

Nusszopf's target database is PostgreSQL specifically (per `CLAUDE.md`), not a generic SQL database. Unlike LCxHolz's SQLite-for-speed shortcut in CI (see `docs/references/lcxholz.md` §5–6 for why that doesn't transfer), Nusszopf's Feature test suite must run against real PostgreSQL wherever Postgres-specific behavior (JSON operators, full-text search, constraints, extensions) is exercised. The exact CI database strategy (a disposable Postgres service container vs. some other approach) is an open architecture decision — see `docs/rewrite/architecture-decisions.md` — and must not silently default to an in-memory SQLite substitute.

## Search and mail in tests

Meilisearch and outgoing mail are real product integrations (per `CLAUDE.md`), not incidental infrastructure. Feature tests touching search indexing or mail-sending behavior should assert against fakes/mocks at the Laravel level (Scout fake, `Mail::fake()`) for speed and determinism; a smaller number of tests should exercise the real Meilisearch/mail-transport integration to catch configuration drift, mirroring the Feature-heavy/E2E-thin split used for browser tests.
