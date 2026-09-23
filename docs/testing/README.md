# Testing Strategy

Nusszopf's testing pyramid follows the shape confirmed in the LCxHolz reference (`docs/references/lcxholz.md` §6): **Feature-test-heavy, unit tests where isolated logic warrants it, and a small, targeted set of browser/E2E tests for the journeys that matter.** Business rules belong in Feature tests, not duplicated at length in E2E specs. This document defines the strategy; `docs/development/quality.md` defines the gates that enforce it; `docs/journeys/README.md` is the shared source of truth both Pest and Playwright specs should cite.

## Unit tests

Isolated, sufficiently complex logic: domain invariants, state-transition rules, value objects, authorization-decision logic that doesn't need the full HTTP/Livewire stack to exercise. Not the bulk of the suite — most business logic is better tested as a Feature test that also exercises persistence and authorization together.

## Feature tests

The bulk of coverage. Per `CLAUDE.md`'s Definition of Done, every feature's Feature tests must cover: HTTP/Livewire behavior, validation, persistence, authorization/permissions, relevant jobs and mail, and search integration where applicable — not just the happy path. `CLAUDE.md` explicitly forbids treating a passing happy-path test as sufficient; error states, empty states, permission boundaries, and edge cases identified during archaeology must each have their own test.

Every row in `docs/security/authorization-matrix.md` needs both an allow-case and a deny-case Feature test against the corresponding Policy method — a matrix row with only one of the two is incomplete coverage, not merely undertested. Every `Fix`-classified entry in `docs/rewrite/bugs.md` needs the specific regression test named in its entry, in the same change that implements the fix.

## Browser (Playwright) tests

A small number of important user journeys through the real UI, per role/actor, translated from the historical E2E suite (`../historical/web-nusszopf/projects/e2e`) and validated against `docs/journeys/README.md`. Adopt LCxHolz's Playwright conventions:

- **Page Object Model** under `tests/E2E/pages/`, specs under `tests/E2E/specs/<actor>/`.
- **One fixture/env-constants file** (`tests/E2E/support/env.ts` equivalent) declaring every seeded ID/slug/credential specs import, so seed-data drift is caught in one place instead of scattered magic strings.
- **`data-testid` attributes** on interactive elements Playwright needs to target reliably, matched onto the historical UI structure (`docs/design/components.md`) rather than invented ad hoc.
- A `global-setup` step that resets and seeds the database once per run, plus one-time sign-in per role with persisted `storageState`, so individual specs don't each repeat login.
- Run against every browser engine Playwright supports in CI as separate parallel jobs (mirroring LCxHolz's per-engine matrix), not one job looping serially over engines.

### Second-slice conventions

- Radios and checkboxes are visually hidden native inputs inside a `<label>`, as historically; specs
  click the visible label (`ProjectWizardPage.pick`), never `check({ force: true })` on the hidden
  input (its hit-target is not where the glyph is).
- After a Livewire round trip that swaps a view (the edit screen's view selector), specs wait for the
  server-rendered view's marker (`ProjectEditPage.expectView`), not just the select's value.
- The stack contains a `locationiq-stub` service (dev/CI only) answering the LocationIQ autocomplete
  endpoint with a fixed result; the place search is therefore exercised end to end without a key or
  network. Feature tests use `Http::fake()` against `LocationSearch` instead.
- The search specs (`tests/E2E/specs/visitor/search.spec.ts`) need the stack to run with a small page size so the last page can
  be reached: put `SEARCH_PAGE_SIZE=5` in `.env` (the app re-reads it on the next request) and pass `E2E_SEARCH_PAGE_SIZE=5` to
  Playwright; without the variable the "load more" spec is skipped. The index-recovery spec additionally needs
  `E2E_MEILISEARCH_URL` and `E2E_MEILISEARCH_KEY` (to wipe the index) and `E2E_REINDEX_COMMAND` (a shell command that runs
  `php artisan search:reindex` in the stack, e.g. `docker compose -f compose.dev.yaml exec -T php-fpm php artisan search:reindex`);
  it is skipped without them. CI sets all of these. The specs use words made unique per run because the development index
  outlives every test.
- `tests/Feature/Search/ProjectSearchSyncTest.php` mixes fast document-shape tests with a few
  `@group meilisearch` tests against the real engine (edit re-indexing, publish/hide, delete).

### Ninth-slice conventions

- `tests/E2E/specs/visitor/newsletter.spec.ts` needs `E2E_MAILPIT_URL`, like the password-reset spec. The public
  newsletter forms share the historical budget of 10 requests per 15 minutes per IP, and every engine runs from the same
  address: the specs spend two of it per engine, so a full three-engine run fits once per 15 minutes. Running it again
  sooner, clear the counter first: `docker compose -f compose.dev.yaml exec php-fpm php artisan cache:clear`.
- Mailed links are followed by their path only (`mailedPath()`), because `APP_URL` need not be the origin Playwright reaches.

## Production stack

`scripts/smoke-test.sh` (run from the host, needs Docker, curl and openssl; CI job "Production stack") builds the two production images from the working copy, installs into a fresh temporary directory exactly
as an operator would (`install.sh` against the repository's own `docker-compose.yaml` and `.env.production.example`), starts the stack, waits for every healthcheck, and checks: the release is baked into the images,
`/up`, `/search`, `/login` and a built stylesheet are served, migrations ran and the caches are warm, an unknown page is a plain 404, `/health` turns 200 and only the token reveals details, and `search:reindex` runs.
`SMOKE_KEEP=1` leaves the stack running for manual drills (`docs/deployment/operations.md`). The Playwright suite has not yet been run against these images (phase P-7).

## Visual parity

Because visual fidelity is a hard requirement (`CLAUDE.md`, `.claude/rules/02-visual-fidelity.md`), Nusszopf needs a way to verify screens actually match the historical design, not just that they render without error. Where practical, use Playwright screenshot comparison against reference captures of the historical `web-nusszopf` UI for key screens/states. This is an open tooling decision (exact screenshot-diff mechanism, baseline management, acceptable pixel/threshold tolerance) to record in `docs/rewrite/architecture-decisions.md` once made — do not invent a mechanism silently.

## Regression coverage

Every historical bug knowingly fixed during the rewrite ships with a regression test in the same change (see `docs/development/quality.md`). The regression test should assert the *corrected* behavior and, where practical, be named or commented so a future contributor can see which historical defect it guards against.

## Search and mail testing

- Search: fake Scout/Meilisearch in most Feature tests for speed; a smaller number of tests (`@group meilisearch`, `tests/Helpers/search.php` for the shared helpers) exercise real Meilisearch indexing/query behavior to catch configuration and ranking drift — including `applyIndexSettings()`, which applies the checked-in settings to the test index first (the filter needs `req_type` filterable). See `docs/search/README.md` for the search semantics being verified.
- Mail: use Laravel's mail fake for asserting triggers/recipients/content in Feature tests; verify actual rendering (subject, links, branding) against the historical templates (`../historical/emails-nusszopf`) separately — see `docs/email/README.md`. The dev/CI stack runs a Mailpit catcher (`compose.dev.yaml`, `MAIL_HOST=mailpit`/`MAIL_PORT=1025`) so Playwright specs can assert a mail actually arrived through its JSON API (`http://mailpit:8025/api/v1/...` inside the stack, `E2E_MAILPIT_URL` — the host-published port — for Playwright itself, which runs outside the stack); never used in production (`docs/rewrite/sixth-slice.md`).

### Isolation from the development stack

Inside the Compose containers the development `.env` is the process environment, and Laravel reads
`$_SERVER` first, so phpunit's `<env>` values used to be silently ignored: the Pest suite ran against the
*development* database (re-migrating it), Redis queue and search index, and its queued jobs starved the
queue worker for the browser suite that followed. `phpunit.xml` now forces its values (all but the database host, port and credentials, which CI points at its
service containers through the process environment) and `tests/bootstrap.php` copies them over `$_SERVER`, so the suite always uses `nusszopf_testing`, the `sync`
queue and the `null` Scout driver; the `meilisearch`-group tests still switch to the real engine explicitly.
The `meilisearch`-group tests do use the real engine, but with `SCOUT_PREFIX=testing_` — an index of their own
(`testing_items`, created on first write, without the ranking settings) — so they cannot touch development search
data. Documents of earlier runs stay in it, so those tests use per-run search words and assert on their own ids.
`tests/Feature/TestEnvironmentIsolationTest.php` fails if the suite ever points at the development database,
queue, cache, session or index again.

## Test data

Tests should use deterministic factories/seeders covering the domain entities established in `docs/domain/entities.md`. Fixture identifiers used by Playwright specs must be declared in one place (see "Browser tests" above), never inlined per spec.

## Test organization (target shape)

```text
tests/
├── Feature/       — HTTP/Livewire/business-logic tests, organized by domain area
├── Unit/          — isolated logic tests
└── E2E/
    ├── specs/<actor>/   — Playwright specs, grouped by actor (mirrors docs/journeys/)
    ├── pages/           — Page Object Models
    └── support/         — fixture/env constants, shared helpers
```

This mirrors the LCxHolz test-organization convention (`docs/references/lcxholz.md` §6) and should be adjusted only if Nusszopf's actual actor set (established during authentication/permissions archaeology) differs from a simple guest/customer/admin split.

## CI

Every gate in this document must be runnable locally with the exact command CI uses (see `docs/development/quality.md`, "What CI must mirror exactly"). The CI pipeline shape itself — parallel lint/static-analysis/test jobs, a browser-engine matrix, and an image-publish job gated on all of the above passing *and* on the event being a push rather than a pull request — is documented in `docs/references/lcxholz.md` §5 and should be adopted with Nusszopf's actual database (PostgreSQL, not LCxHolz's SQLite shortcut — see `docs/development/quality.md`, "Database-specific testing").
