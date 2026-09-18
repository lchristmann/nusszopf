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

## Visual parity

Because visual fidelity is a hard requirement (`CLAUDE.md`, `.claude/rules/02-visual-fidelity.md`), Nusszopf needs a way to verify screens actually match the historical design, not just that they render without error. Where practical, use Playwright screenshot comparison against reference captures of the historical `web-nusszopf` UI for key screens/states. This is an open tooling decision (exact screenshot-diff mechanism, baseline management, acceptable pixel/threshold tolerance) to record in `docs/rewrite/architecture-decisions.md` once made — do not invent a mechanism silently.

## Regression coverage

Every historical bug knowingly fixed during the rewrite ships with a regression test in the same change (see `docs/development/quality.md`). The regression test should assert the *corrected* behavior and, where practical, be named or commented so a future contributor can see which historical defect it guards against.

## Search and mail testing

- Search: fake Scout/Meilisearch in most Feature tests for speed; a smaller number of tests exercise real Meilisearch indexing/query behavior to catch configuration and ranking drift. See `docs/search/README.md` for the search semantics being verified.
- Mail: use Laravel's mail fake for asserting triggers/recipients/content in Feature tests; verify actual rendering (subject, links, branding) against the historical templates (`../historical/emails-nusszopf`) separately — see `docs/email/README.md`.

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
