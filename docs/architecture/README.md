# Architecture

> **Status: Proposal.** This is the target architecture derived from the archaeology in `docs/domain/`, `docs/design/`, `docs/authentication/`, `docs/search/`, `docs/email/`, and the reference-project findings in `docs/references/`. Everything here should be read alongside `docs/architecture/mapping.md` (component-by-component translation) and `docs/rewrite/architecture-decisions.md` (the specific decisions this document surfaces but does not itself resolve). Nothing here has been implemented.

## Target

Nusszopf 2 is **one Git repository, one modular Laravel monolith**, self-hosted via Docker Compose. No microservices, no separate frontend deployment, no GraphQL layer, no SaaS control plane.

Stack (per `CLAUDE.md`): PHP 8.5, Laravel 13, PostgreSQL, Blade, Livewire 4, Tailwind CSS 4, Redis, Meilisearch, Pest, Playwright, Laravel Pint, Larastan, Docker Compose, GitHub Actions, Laravel Boost where useful. Alpine.js/Vue are not architectural requirements; the smallest client-side solution that satisfies documented behavior wins (see `docs/architecture/mapping.md`'s "Frontend / UI" section for where that judgment call actually arises).

## Why a monolith (not a port of the historical service split)

The historical architecture was **four separately-deployed services** — `webapp` (Next.js on Vercel), `auth-login`/`auth-password` (Next.js on Vercel, serving as Auth0 custom-UI pages), and `be-nusszopf` (Hasura GraphQL engine + Auth0 tenant + Meilisearch, on Heroku/DigitalOcean) — communicating over GraphQL and Auth0's OIDC flows. Per the archaeology (`docs/authentication/README.md` §1, `docs/architecture/mapping.md`):

- The `auth-login`/`auth-password` split existed **only** because Auth0 requires separately-hosted custom-UI pages for its Universal Login product. Laravel-native authentication has no equivalent constraint.
- Hasura's GraphQL layer existed to give a client-rendered SPA (Next.js on Vercel) a queryable API without hand-writing REST endpoints. A server-rendered Livewire app has no client that needs a GraphQL API — Eloquent models called directly from server-side Livewire components/controllers replace it with zero loss of the *behavior* Hasura enforced (permissions, relationships — see `docs/domain/permissions.md`), because that behavior becomes Laravel Policies and Eloquent relationships instead.
- None of the four services' separateness was ever a *product* requirement (nothing in `docs/design/`, `docs/journeys/`, or `docs/domain/` depends on them being independently deployable) — it was purely a consequence of the historical technology choices (Next.js + Vercel + Auth0 + Hasura-as-a-service). Collapsing them into one Laravel monolith is therefore an infrastructure simplification, not a product change, consistent with `CLAUDE.md`'s "translate historical infrastructure into the new architecture... do not reproduce obsolete infrastructure merely because it existed historically."

## Module boundaries (proposal, needs approval — see `docs/rewrite/architecture-decisions.md`)

Domain boundaries follow the reconstructed domain (`docs/domain/entities.md`), not arbitrary technical layers, per `CLAUDE.md`. Proposed top-level modules inside the single Laravel app:

- **Projects** — `Project`, `Request` (see naming note in `docs/architecture/mapping.md`), `ProjectAnalytics`. Owns project CRUD, the request lifecycle, visibility/publish behavior, view/contact-request counting (server-controlled, fixing the historical open-write defect — `docs/rewrite/bugs.md` BUG-001), and the Meilisearch integration for both entities. The full authorization ruleset for this module is `docs/security/authorization-matrix.md`'s Project/Request sections, not restated here.
- **Accounts** — `User`, authentication (registration/login/logout/password reset), profile (avatar, name), account deletion (with its cascade). Owns the auth surface described in `docs/authentication/README.md`.
- **Newsletter** — `Lead`, double opt-in confirm/unsubscribe, the SendGrid-list-sync equivalent (or its replacement — see `docs/rewrite/architecture-decisions.md`). Deliberately kept separate from **Accounts** because the historical `Lead`↔`User` relationship is a soft, email-matched, optional link, not a hard dependency (`docs/domain/relationships.md`) — a person can historically be a lead without ever registering (the contact/newsletter form is public).
- **Content pages** — legal notice, legal policy, privacy, home/landing. Static/CMS-driven, no domain logic; kept as its own thin module (or just plain routes/views with no dedicated module) so the four domain-bearing modules above stay free of unrelated static-content concerns.
- **Platform/shared** — SEO helpers, the shared Blade component library (`docs/design/components.md`'s atoms/molecules/organisms/templates translated to Blade components), notification/toast plumbing, the loading-indicator behavior, error pages. Cross-cutting, used by every other module.

This is a **module-boundary proposal**, not a package-per-module micro-architecture — "modular monolith" here means clear internal boundaries (namespaces, directories, tests grouped by module) inside one Laravel app, not separately-versioned/installable packages, unless a concrete need for that emerges later.

## Request flow (typical read: viewing a project)

```
Browser
  │  GET /projects/{id}
  ▼
Laravel route → Livewire full-page component (Projects module)
  │
  ├─ ProjectPolicy::view()  ─────────────► authorization (docs/domain/permissions.md)
  ├─ Project::with(['requests','user'])   ─► Eloquent (PostgreSQL)
  ├─ increments view counter server-side  ─► fixes historical open-write defect
  └─ renders Blade components             ─► docs/design/components.md tokens/markup
  ▼
Server-rendered HTML (+ Livewire wire:* attributes for the request-contact dialog, etc.)
```

There is no client-side GraphQL fetch, no separate API round-trip — this is the direct behavioral replacement for the historical `apolloClient` query against Hasura from `getServerSideProps`/client hooks (`docs/design/screens.md`, Project detail section).

## Request flow (typical write: publishing a project)

```
Browser (Livewire component, Settings tab)
  │  wire:click="publish"
  ▼
Livewire component method (Projects module)
  │
  ├─ ProjectPolicy::update()              ─► must own the project
  ├─ $project->update(['visibility' => 'public'])
  │     └─ Eloquent model event 'updated' ─► queued job: sync to Meilisearch (Scout)
  ▼
Re-rendered Livewire component (no full page reload)
```

This directly replaces the historical `update_projects` Hasura mutation + `sync_projects_search` event-trigger webhook pair (`docs/domain/workflows.md`, "Workflow: publish a project") with one Eloquent update + one queued Scout sync — same observable behavior (edit and reindex happen together), same retry-on-failure property (Laravel's queue retries, an improvement over the historical silent-give-up-after-3-tries webhook).

## Background work (queue)

Every historical Hasura event-trigger webhook becomes a queued job dispatched from an Eloquent model observer (see `docs/architecture/mapping.md`, Data layer row). Concretely:

- `Project`/`Request` created/updated/deleted → sync to Meilisearch (replaces `sync_projects_search`/`sync_requests_search`).
- `Lead.hasConfirmed` updated / `Lead` deleted → sync to whatever mailing-list mechanism replaces SendGrid list sync (replaces `sync_leads_sendgrid`) — **contingent on the mail-provider decision** in `docs/rewrite/architecture-decisions.md`.
- `User` deleted → cleanup of externally-stored assets (avatar file), if object storage is in scope for the first slice (see mapping doc) — replaces `clean_up_deleted_user`. There is no more external Auth0 identity to clean up, since Auth0 is gone.
- `User.picture` updated → cleanup of the previous avatar file — replaces `clean_up_users_digitalocean`.
- Scheduled: none. Confirmed historically there were no cron triggers/scheduled functions in `be-nusszopf` at all — no scheduled job is a historical requirement. (Nusszopf 2 may still introduce scheduled maintenance jobs — e.g. failed-job cleanup — as ordinary engineering practice, not as a product-behavior port.)

Redis backs the queue (and cache, and Livewire's broadcasting if used) — consistent with the target stack and with both `lcxholz` and `waffle-dashboard`'s reference use of Redis for the same purpose (`docs/references/lcxholz.md`, `docs/references/waffle-dashboard.md`).

## Search

Laravel Scout + the Meilisearch driver, replacing the historical "Hasura event trigger → unidentified indexer → hand-configured `items` index" pipeline (`docs/search/README.md`). Scout's model observers are the same observers used for the queue-based sync above — search indexing is not a separate subsystem from the general "keep derived state in sync with the source of truth" pattern used everywhere else in this architecture. Index settings (ranking rules, filterable/sortable attributes) are defined in versioned Laravel config, not set up by hand via an API client — this closes a confirmed historical operational gap (`docs/search/README.md`'s note on the manual Postman-driven index setup) without changing observable search *behavior*.

## Email

Laravel Mail (Mailables) + Notifications, replacing the historical split between Auth0-triggered emails and SendGrid-triggered emails (`docs/email/README.md`). One mail system, one place templates live (version-controlled in the Laravel app, not partly in a SendGrid dashboard), fixing the confirmed historical "dashboard templates may have drifted from the committed `.mjml` source" risk (`docs/email/README.md`'s "Suspected historical issues" #4) as a side effect of the architecture change itself.

## Deployment

See `docs/deployment/README.md` for the full self-hosting architecture (Compose file split, services, health checks, backups, upgrades) — that document already reflects the reference-project findings (`docs/references/laravel-docker-examples.md`, `docs/references/lcxholz.md`, `docs/references/waffle-dashboard.md`) and is not duplicated here. This document's only architectural claim relevant to deployment is: **the whole application is one deployable unit** (`php-fpm` + `nginx` images, `postgres`, `redis`, `meilisearch` as supporting services) — there is no second "auth service" or "search indexer service" to deploy separately, unlike the historical four-service topology.

## What this document does not settle

Every module boundary, naming choice (e.g. `Request` vs. `ProjectRequest`), and infrastructure choice (object storage in v1 or not, mail provider default, registry choice, rich-text editor replacement) flagged with "needs approval" above is tracked, with full context, in `docs/rewrite/architecture-decisions.md`. This document proposes a coherent shape; it does not itself constitute approval of any individual decision within that shape.
