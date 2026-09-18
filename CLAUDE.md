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

Located under `../historical/`:

- `../historical/web-nusszopf`
- `../historical/be-nusszopf`
- `../historical/emails-nusszopf`

These are the primary sources of truth for product behavior, domain, data model, workflows, permissions, routes, screens, navigation, UI design, responsive behavior, authentication, search, email behavior, and E2E journeys.

### LCxHolz

Located at `../development-reference/lcxholz`.

Reference for developer experience, Laravel conventions, Docker development workflow, Livewire, Tailwind, testing, automation, CI, static analysis, documentation, Claude Code, and onboarding.

Do not copy LCxHolz product/domain behavior.

### Waffle Dashboard

Located at `../foss-reference/waffle-dashboard`.

Reference for FOSS project organization, releases, versioning, changelog, GitHub Releases, CI/CD, Docker image publishing, maintenance, contribution documentation, self-hosting documentation, and upgrade processes.

Do not copy Waffle Dashboard product/domain behavior.

### Laravel Docker examples

Located at `../infrastructure-reference/laravel-docker-examples`.

Technical reference for Docker Compose, development/production separation, Dockerfiles, PHP-FPM, Nginx, PostgreSQL, Redis, production builds, health checks, and multi-stage builds.

Do not blindly copy it. Prefer the simplest architecture that satisfies Nusszopf's requirements.

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

## Visual fidelity rules

The historical Nusszopf design is a hard requirement.

Preserve information architecture, page composition, navigation, typography, colors, spacing, sizing, borders, radii, shadows, icons, imagery, components, forms, dialogs, menus, responsive behavior, interaction states, loading states, empty states, error states, success states, and materially visible animations/transitions.

Do not replace the historical design with a generic Tailwind design.

Tailwind is an implementation tool, not the design source of truth.

## Engineering quality

Use the LCxHolz level of engineering discipline as the target.

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
- copy LCxHolz business logic
- copy Waffle Dashboard business logic
- treat a passing happy-path test as sufficient
