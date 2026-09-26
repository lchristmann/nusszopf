# Architecture

> **Status: implemented.** This describes the architecture as built (the ten feature slices, `docs/rewrite/README.md`, verified through the finish-line phases P-1…P-13, `docs/release/parity/README.md`). It began as a proposal derived from the archaeology in `docs/domain/`, `docs/design/`, `docs/authentication/`, `docs/search/`, `docs/email/`, and the reference-project findings in `docs/references/`; the decisions it rests on are recorded in `docs/rewrite/architecture-decisions.md` and `docs/rewrite/decisions-register.md`. Read it alongside `docs/architecture/mapping.md` (component-by-component translation from the historical system).

## Target

Nusszopf 2 is **one Git repository, one modular Laravel monolith**, self-hosted via Docker Compose. No microservices, no separate frontend deployment, no GraphQL layer, no SaaS control plane.

Stack (per `CLAUDE.md`): PHP 8.5, Laravel 13, PostgreSQL, Blade, Livewire 4, Tailwind CSS 4, Redis, Meilisearch, Pest, Playwright, Laravel Pint, Larastan, Docker Compose, GitHub Actions, Laravel Boost where useful. Alpine.js/Vue are not architectural requirements; the smallest client-side solution that satisfies documented behavior wins (see `docs/architecture/mapping.md`'s "Frontend / UI" section for where that judgment call actually arises).

## Why a monolith (not a port of the historical service split)

The historical architecture was **four separately-deployed services** — `webapp` (Next.js on Vercel), `auth-login`/`auth-password` (Next.js on Vercel, serving as Auth0 custom-UI pages), and `be-nusszopf` (Hasura GraphQL engine + Auth0 tenant + Meilisearch, on Heroku/DigitalOcean) — communicating over GraphQL and Auth0's OIDC flows. Per the archaeology (`docs/authentication/README.md` §1, `docs/architecture/mapping.md`):

- The `auth-login`/`auth-password` split existed **only** because Auth0 requires separately-hosted custom-UI pages for its Universal Login product. Laravel-native authentication has no equivalent constraint.
- Hasura's GraphQL layer existed to give a client-rendered SPA (Next.js on Vercel) a queryable API without hand-writing REST endpoints. A server-rendered Livewire app has no client that needs a GraphQL API — Eloquent models called directly from server-side Livewire components/controllers replace it with zero loss of the *behavior* Hasura enforced (permissions, relationships — see `docs/domain/permissions.md`), because that behavior becomes Laravel Policies and Eloquent relationships instead.
- None of the four services' separateness was ever a *product* requirement (nothing in `docs/design/`, `docs/journeys/`, or `docs/domain/` depends on them being independently deployable) — it was purely a consequence of the historical technology choices (Next.js + Vercel + Auth0 + Hasura-as-a-service). Collapsing them into one Laravel monolith is therefore an infrastructure simplification, not a product change, consistent with `CLAUDE.md`'s "translate historical infrastructure into the new architecture... do not reproduce obsolete infrastructure merely because it existed historically."

## Module boundaries

Domain boundaries follow the reconstructed domain (`docs/domain/entities.md`), not arbitrary technical layers, per `CLAUDE.md`. "Modular monolith" here means clear internal boundaries (namespaces, directories, tests grouped by area) inside one Laravel app, not separately-versioned packages. The boundaries as built:

- **Projects** — `App\Models\Project`, `ProjectRequest` (not `Request`, which would clash with `Illuminate\Http\Request`; register B7) and `ProjectAnalytics`; `App\Livewire\Projects\*` (wizard, edit, detail, My Projects); `ProjectPolicy`/`ProjectRequestPolicy`. Owns project CRUD, the request lifecycle, visibility/publish behavior, and the view counter, which only the server increments (fixing the historical open-write defect, BUG-001). The authorization ruleset is `docs/security/authorization-matrix.md`'s Project/Request sections, not restated here.
- **Accounts** — `User`, `UserPolicy`, `App\Livewire\Auth\*` (login/registration, password reset), `App\Http\Controllers\Auth\*` (Google, logout, e-mail verification, unblock), `App\Livewire\Profile\Profile`, `App\Support\AccountDeleter` (the cascade) and `AvatarUploader`. The auth surface is described in `docs/authentication/README.md`.
- **Newsletter** — `Lead`, `App\Livewire\Newsletter\*`, `App\Support\Newsletter` and `NewsletterToken`, the two newsletter mails, and the `newsletter:export` and `newsletter:purge-unconfirmed` commands. Deliberately separate from **Accounts**, because the historical `Lead`↔`User` relationship is a soft, email-matched, optional link (`docs/domain/relationships.md`): a person can be a lead without ever registering.
- **Search** — `App\Services\Search\*` (query, index settings, hit objects), `App\Livewire\Search\Search`, `config/scout.php` and the `search:reindex` command. The index is derived data: it is rebuilt from PostgreSQL, never backed up.
- **Content pages** — Home, the operator-supplied legal pages (`LegalPageController`, `App\Support\LegalText`), error pages, `robots.txt` and the sitemap (`App\Http\Controllers\Seo\*`). No domain logic.
- **Platform/shared** — the Blade component library (`docs/design/components.md`), `App\Health\HealthChecker` and `nusszopf:health`, `SecurityHeaders`, `App\Support\Operator` (this instance's identity, from `NUSSZOPF_CONTACT_EMAIL`), the mail layout and `SendQueuedMailUnlessModelGone`. Cross-cutting, used by every other module.

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

Every historical Hasura event-trigger webhook became queued work, dispatched from Eloquent model events (see `docs/architecture/mapping.md`, Data layer row) or directly from the action. As built:

- `Project`/`ProjectRequest` created/updated/deleted → sync to Meilisearch through Scout's `Searchable` trait and `SCOUT_QUEUE=true` (replaces `sync_projects_search`/`sync_requests_search`). A failed sync is retried and then kept in `failed_jobs`, never dropped silently (BUG-009).
- `Lead` → no list sync at all (decision A-6): the `leads` table is the list, and `newsletter:export` hands the confirmed subscribers to an operator's own sender (`docs/deployment/operations.md`, "Newsletter subscribers"). Nothing replaces `sync_leads_sendgrid`.
- `User` deleted → `AccountDeleter` deletes the projects (unindexing them) and the avatar file in the same action; there is no external Auth0 identity left to clean up. Replacing an avatar removes the previous file.
- Every mail (welcome, verification, password reset, blocked account, contact, the two newsletter mails) is a queued Mailable on the same worker and the same retry policy as search indexing. A mail for an account deleted before it is sent is dropped (P12-03).
- Scheduled work (`routes/console.php`): the two heartbeats that `/health` reads, and `newsletter:purge-unconfirmed` daily at 03:30 UTC (decision A-1). Historically there were no scheduled jobs at all (the Hasura cron triggers were empty), so nothing else is scheduled; new periodic work is added only with a decision that needs it.

Redis backs the queue, the cache and the sessions (started with its append-only file so a crash does not lose queued work, P-12) — consistent with the target stack and with `lcxholz` and `waffle-dashboard` (`docs/references/lcxholz.md`, `docs/references/waffle-dashboard.md`). The worker and the scheduler are their own Compose services (`docs/deployment/README.md`).

## Search

Laravel Scout + the Meilisearch driver, replacing the historical "Hasura event trigger → unidentified indexer → hand-configured `items` index" pipeline (`docs/search/README.md`). Scout's model events are the same events used for the queue-based sync above — search indexing is not a separate subsystem from the general "keep derived state in sync with the source of truth" pattern. Index settings (ranking rules, filterable/sortable attributes, the hit cap) are defined in versioned Laravel config (`config/scout.php`) and applied by the container entrypoint and by `search:reindex`, not set up by hand via an API client — this closes a confirmed historical operational gap (`docs/search/README.md`'s note on the manual Postman-driven index setup) without changing observable search *behavior*. Recovery is one command (`docs/deployment/operations.md`, "Search index recovery").

## Email

Laravel Mail (queued Mailables), replacing the historical split between Auth0-triggered emails and SendGrid-triggered emails (`docs/email/README.md`). One mail system, one place templates live (version-controlled in the Laravel app, not partly in a SendGrid dashboard), which removes the confirmed historical "dashboard templates may have drifted from the committed `.mjml` source" risk as a side effect. Every mail is HTML-only, by decision (P13-03).

**Transport.** Nusszopf uses Laravel's own mail abstraction and nothing on top of it: the operator picks a mailer with `MAIL_MAILER`, and there is no Nusszopf-specific provider interface, driver or adapter to maintain. **Resend is the recommended provider** — it is the one verified against a real mailbox on the production stack (P-13, `docs/release/parity/P-13-email-delivery.md`), and its whole integration is one Composer dependency (`resend/resend-php`) plus `RESEND_API_KEY`. A plain SMTP relay is supported through the same abstraction, but has not been tested against a real relay with TLS. Nothing prevents another Laravel mailer, but none is documented. Operator setup: `docs/deployment/README.md`, "Sending mail".

## Deployment

See `docs/deployment/README.md` for the full self-hosting architecture (Compose file split, services, health checks, backups, upgrades) — that document already reflects the reference-project findings (`docs/references/laravel-docker-examples.md`, `docs/references/lcxholz.md`, `docs/references/waffle-dashboard.md`) and is not duplicated here. This document's only architectural claim relevant to deployment is: **the whole application is one deployable unit** (the `php-fpm` application image, which also runs the queue worker and the scheduler, and the `web` nginx image; `postgres`, `redis`, `meilisearch` as supporting services) — there is no second "auth service" or "search indexer service" to deploy separately, unlike the historical four-service topology.

## Decisions behind this shape

Every module boundary, naming choice (`ProjectRequest`), and infrastructure choice (local-disk avatars in v1, the mail transport, GHCR, the TipTap editor, the health checks) is recorded with its context in `docs/rewrite/architecture-decisions.md` and indexed in `docs/rewrite/decisions-register.md`. What is still open there is deliberately small: who writes changelog entries (register C1) and the breached-password check (register C2).
