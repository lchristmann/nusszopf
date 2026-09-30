# Nusszopf 2 — Claude Code Instructions

## Mission

Nusszopf 2 is a revival and faithful reimplementation of the historical Nusszopf application.

The goal is to preserve the historical product while replacing obsolete implementation and infrastructure.

This is **not a redesign**.

The target is:

- functionally equivalent to the historical Nusszopf wherever the historical behavior is correct
- visually equivalent to the historical Nusszopf
- more robust where the historical implementation had bugs, incomplete flows, or missing pieces
- meticulously engineered and tested
- self-hostable FOSS
- easy to install, operate, upgrade, back up, and maintain

## Source-of-truth model

Different references have different responsibilities.

### Historical Nusszopf repositories

The archived repositories of the original Nusszopf (GitHub organization `Nusszopf`). Clone them next to this repository, under `../historical/`, when you need the original code (`docs/handbuch/konventionen.md`, "Wo die Wahrheit liegt"):

- `../historical/web-nusszopf`
- `../historical/be-nusszopf`
- `../historical/emails-nusszopf`

These are the primary sources of truth for product behavior, domain, data model, workflows, permissions, routes, screens, navigation, UI design, responsive behavior, authentication, search, email behavior, and E2E journeys.

### Laravel Docker examples

Public repository `rw4lll/laravel-docker-examples`, the base Nusszopf's Docker setup began from; findings in `docs/references/laravel-docker-examples.md`.

Technical reference for Docker Compose, development/production separation, Dockerfiles, PHP-FPM, Nginx, PostgreSQL, Redis, production builds, health checks, and multi-stage builds.

Do not blindly copy it. Prefer the simplest architecture that satisfies Nusszopf's requirements.

## Specification documents

The specification produced by the archaeology and Golden Master phases lives under `docs/`. Before implementing anything, read (in this order):

1. `docs/rewrite/decisions-register.md` — what's already decided vs. what still needs a human call. Do not re-litigate an "already decided" item; do not silently resolve a "requires human decision" item yourself.
2. `docs/rewrite/bugs.md` — the historical-defect index. If the area you're touching has an entry here, its classification (Fix/Preserve/Unknown/Replace) governs what you build — see the bug-classification workflow below.
3. The relevant `docs/domain/`, `docs/design/` (including `docs/design/screen-specs.md` for the per-screen checklist), `docs/authentication/`, `docs/search/`, `docs/email/`, `docs/security/` (including `docs/security/authorization-matrix.md`) topic file for the area you're touching.
4. `docs/rewrite/open-questions.md` — if what you need is listed here as Unknown, do not invent an answer; either do the recommended investigation against `../historical/` yourself and update the entry, or escalate to the user.

If implementation surfaces a fact that contradicts a "Confirmed" claim in any of these documents, stop and reconcile the document against the historical source before proceeding — do not silently code around the discrepancy.

## Bug classification workflow

Every suspected historical defect, before it is fixed (or preserved, or flagged as needing a decision), must be classified in `docs/rewrite/bugs.md` using the existing ID scheme (`BUG-NNN`). A classification is one of:

- **Fix** — demonstrably defective; the corrected behavior, evidence, and required regression test are recorded in `docs/rewrite/intentional-changes.md` before the fix is implemented, not after.
- **Preserve** — the historical behavior stands, including behavior that looks surprising but has no evidence of being unintended.
- **Unknown** — cannot be classified without a human product decision; recorded in `docs/rewrite/open-questions.md`, and implementation must not proceed on a guess.
- **Replace** — obsolete infrastructure only; product behavior is unchanged (see `docs/architecture/mapping.md`).

Do not fix a suspected bug that has no entry in `docs/rewrite/bugs.md`/`docs/rewrite/intentional-changes.md` yet — add the entry first (as Proposed, per that document's format), then implement.

## Product fidelity rules

Preserve historical behavior unless it is:

1. demonstrably a bug,
2. demonstrably incomplete,
3. demonstrably broken because a dependency/service disappeared, or
4. an explicitly approved product change.

A bug fix is not permission to redesign the surrounding product.

When correcting historical behavior:

- document the historical behavior
- explain why it is considered incorrect
- document the corrected behavior
- add a regression test
- record the decision in `docs/rewrite/intentional-changes.md`
- add or update the corresponding entry in `docs/rewrite/bugs.md`

## Visual fidelity rules

The historical Nusszopf design is a hard requirement.

Preserve information architecture, page composition, navigation, typography, colors, spacing, sizing, borders, radii, shadows, icons, imagery, components, forms, dialogs, menus, responsive behavior, interaction states, loading states, empty states, error states, success states, and materially visible animations/transitions.

Do not replace the historical design with a generic Tailwind design.

Tailwind is an implementation tool, not the design source of truth.

## Engineering quality

Hold the code to a high standard of engineering discipline.

Expect automated tests, browser/E2E tests, static analysis, formatting, CI, reproducible Docker environments, documented workflows, regression tests, clear architecture boundaries, safe migrations, reliable background jobs, and maintainable code.

Do not add complexity merely because another project has it.

## Self-hosting

Nusszopf must be practical for a person who has never seen the source code.

A fresh operator should be able to obtain a release, configure it, start it, initialize it, configure mail/search where applicable, back it up, upgrade it, and recover from failure using documented procedures.

Prefer a small number of well-documented commands. Avoid requiring Laravel knowledge for normal operation.

## No SaaS

This is FOSS and self-hostable. Do not introduce tenants, subscriptions, billing, metering, SaaS administration, or mandatory hosted services unless historical evidence proves the concept is part of the product.

## Technology target

- PHP 8.5
- Laravel 13
- PostgreSQL
- Blade
- Livewire 4
- Tailwind CSS 4
- Redis
- Meilisearch
- Pest
- Playwright
- Laravel Pint
- Larastan
- Docker Compose
- GitHub Actions
- Laravel Boost where useful

Do not add Alpine.js or Vue as explicit architectural requirements. Prefer Laravel, Blade, Livewire and the smallest client-side solution that satisfies the documented behavior.

## Evidence discipline

Use these statuses:

- **Confirmed** — directly demonstrated by historical code, configuration, tests, or observable behavior
- **Inferred** — strongly supported but not directly demonstrated
- **Unknown** — not established

Never silently turn an inference into a requirement. When historical sources disagree, document the disagreement.

## Before implementation

Read the relevant specification and historical evidence first. Do not implement a feature based only on a name or high-level description.

## Definition of done

A feature is complete only when historical behavior is understood, domain rules and authorization are implemented, validation is correct, all relevant UI states exist, visual and responsive behavior matches, relevant search/mail behavior matches, automated tests exist, important journeys have browser coverage, regressions are covered, and documentation is current.

## Never

- redesign Nusszopf
- simplify workflows without evidence
- remove historical concepts because they seem old
- invent domain behavior
- invent UI
- introduce SaaS concepts
- copy historical infrastructure blindly
- treat a passing happy-path test as sufficient
- resolve a documented Unknown (`docs/rewrite/open-questions.md`) by guessing instead of investigating or escalating
- fix a suspected historical bug that has no entry in `docs/rewrite/bugs.md`/`docs/rewrite/intentional-changes.md`
- leave `docs/` stale after a behavior or architecture change — update the relevant topic file in the same change, not as follow-up work
