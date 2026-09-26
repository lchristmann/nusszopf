# Quality Gates

The goal, per `CLAUDE.md`, is engineering discipline at the LCxHolz level: **CI catches exactly what a developer can catch locally, using the same commands.** No gate should exist only in CI and not be runnable on a developer's machine, and no local convention should exist that CI doesn't also enforce.

Full evidence and rationale for each choice: `docs/references/lcxholz.md`. This file defines the gates themselves; `docs/testing/README.md` defines the testing strategy in more depth.

## The gates

Each row is a job of `.github/workflows/ci.yml`, which also runs as the gate of the release workflow
(`docs/release/release-process.md`).

| Gate (CI job) | Tool | Local command | What it catches |
|---|---|---|---|
| Formatting | Laravel Pint | `composer lint:check` (apply: `composer pint`) | Code style drift, non-PSR-12 formatting |
| Static analysis | Larastan (PHPStan), level 7 | `composer larastan` | Type errors, undefined methods/properties, dead code, unsafe nullability — Laravel-aware |
| Unit and Feature tests | Pest, against real PostgreSQL (and Meilisearch for the `meilisearch` group) | `composer test` | Business rules, HTTP/Livewire behavior, validation, persistence, authorization, jobs, mail, search integration |
| Frontend build | Vite | `npm run build` | The assets compile |
| Browser/E2E tests (one job per engine and device project) | Playwright: Chromium, Firefox, WebKit, emulated phones and tablet | `docker compose -f compose.dev.yaml exec playwright npx playwright test` (or `npm run test:e2e:<project>`) | Real-browser user journeys, accessibility (axe), keyboard, CSP violations, page weight |
| Visual regression | Playwright `toHaveScreenshot` | `docs/testing/visual-regression.md` | Any pixel drift of the 23 screens at phone, tablet and desktop width |
| Production stack | `scripts/smoke-test.sh` | the same | The production images build, install with `install.sh`, start healthy and answer; `install.sh --upgrade` works |
| Browser tests on the production images | `scripts/prod-e2e.sh` | the same | The whole Playwright suite against those images |

Not in CI, on purpose: the drills that need a second Docker daemon or hours (`scripts/upgrade-test.sh`,
`restore-test.sh`, `search-recovery-test.sh`, `queue-scheduler-test.sh`) and the one that sends real mail
(`mail-delivery-test.sh`). They are release steps (`docs/release/release-process.md`, `docs/testing/README.md`).

## Static analysis level

LCxHolz runs Larastan at **level 7** (Confirmed, `phpstan.neon`), and so does Nusszopf (`phpstan.neon`: `app`, `database`, `routes`). Lower it only with a documented, specific reason (recorded in `docs/rewrite/architecture-decisions.md`) — not as a default escape hatch for unresolved errors.

## Coverage

Coverage is **not measured today**: there is no coverage job, report or badge, and no threshold. LCxHolz reports coverage without gating on it (`fail_below_min: false`); Nusszopf's default is the same — observe, never gate on an arbitrary percentage. Adding a report is an optional improvement and the maintainer's call, not a release requirement.

## What CI must mirror exactly

Every gate above is a Composer/npm script or a script under `scripts/` that a developer runs locally and CI runs unmodified. If CI ever needs a flag or environment variable a local run doesn't also document, that is a discipline gap to fix, not a CI-only detail to leave undocumented. (CI's E2E jobs do set some variables — `SEARCH_PAGE_SIZE`, `E2E_*`, the Meilisearch key — and they are documented in `docs/testing/README.md` and `README-DEV.md`.)

## Regression discipline

Every historical bug that is knowingly fixed during the rewrite (per `CLAUDE.md`'s "when correcting historical behavior" rule) must ship with a regression test in the same change, and the decision must be recorded in `docs/rewrite/intentional-changes.md`. A fix without a regression test is incomplete, not merely undertested.

## Database-specific testing

Nusszopf's target database is PostgreSQL specifically (per `CLAUDE.md`), not a generic SQL database. Unlike LCxHolz's SQLite-for-speed shortcut in CI (see `docs/references/lcxholz.md` §5–6 for why that doesn't transfer), the Feature suite runs against real PostgreSQL: CI starts a `postgres:16-alpine` service container for the Pest job, and the development stack has a separate `nusszopf_testing` database (`docker/postgres/init-testing-db.sql`). There is no SQLite or in-memory substitute.

## Search and mail in tests

Meilisearch and outgoing mail are real product integrations (per `CLAUDE.md`), not incidental infrastructure. Feature tests touching search indexing or mail-sending behavior should assert against fakes/mocks at the Laravel level (Scout fake, `Mail::fake()`) for speed and determinism; a smaller number of tests should exercise the real Meilisearch/mail-transport integration to catch configuration drift, mirroring the Feature-heavy/E2E-thin split used for browser tests.
