# Historical → Nusszopf 2 Mapping

Concrete mapping, derived from the archaeology in `docs/domain/`, `docs/authentication/`, `docs/search/`, `docs/email/`, `docs/design/`, and `docs/references/`. Each row preserves **observable behavior**; the historical **mechanism** is discarded unless noted. Status `Confirmed` means the historical behavior being preserved is directly evidenced; `Proposal` means the Nusszopf 2 side is this document's own recommendation and requires approval (`docs/rewrite/architecture-decisions.md`).

## Data layer

| Historical | Nusszopf 2 | Notes |
|---|---|---|
| `hasura/migrations` (raw SQL DDL) | Laravel migrations | 1:1 schema translation, see entity table below. |
| Hasura GraphQL engine (query/mutation execution) | Eloquent models + Laravel policies/gates | No GraphQL layer — Blade/Livewire talk to Eloquent directly server-side; no public API surface is evidenced as a product requirement (nothing in `docs/design/` or `docs/journeys/` demonstrates a third-party API consumer). |
| Hasura `select_permissions`/`update_permissions`/`insert_permissions`/`delete_permissions` (`docs/domain/permissions.md`) | Laravel Policies (`ProjectPolicy`, `RequestPolicy`, `UserPolicy`, `LeadPolicy`) + query scopes for row-level filtering (e.g. a `visible()` scope mirroring the `visibility = public OR owned-by-me` filter) | Preserve the exact allow/deny matrix per `docs/domain/permissions.md`, **except** the two flagged defects (`ProjectAnalytics` open write, `Request` visibility gap) — those get fixed, not ported, per `docs/rewrite/intentional-changes.md`. |
| `users_private` view (email-hiding pattern) | Just don't `select` email in the base `User` model's default array/Blade exposure; expose it only via an explicit "my account" query/policy | The view existed only to work around Hasura's column-level permission model; Eloquent doesn't need an equivalent artifact, only the equivalent **behavior** (email never leaks to other users). |
| Hasura event triggers (`sync_projects_search`, `sync_requests_search`, `sync_leads_sendgrid`, `clean_up_deleted_user`, `clean_up_users_digitalocean`) | Laravel model observers / events + queued jobs | Each historical webhook becomes an Eloquent model event (`created`/`updated`/`deleted`) dispatching a queued job. Preserve the *retry* semantics (historical: 3 retries, 10s apart, 60s timeout, silent give-up) at minimum — Laravel's queue retry/backoff is a strict improvement (dead-letter via `failed_jobs` instead of silent loss) and should be adopted; record as an intentional improvement, not silently assumed. |
| DB-level `ON DELETE CASCADE` (user→project→request/analytics) | Laravel migration foreign keys with `->cascadeOnDelete()` | Preserve exactly — this is a real product requirement (`docs/domain/relationships.md`), not incidental. |
| Hasura Actions / remote schemas / cron triggers / SQL functions | **None** — all were empty in the historical backend | Confirmed nothing to translate here; do not invent custom Actions-equivalent infrastructure that never existed. |

### Entities

| Historical table | Nusszopf 2 model | Notes |
|---|---|---|
| `public.users` (+ `users_private` view) | `App\Models\User` | `id` is historically the raw Auth0 subject string (`auth0\|...`); Nusszopf 2's `id` is a normal auto-incrementing/UUID Laravel primary key instead, since there is no more external IdP subject to mirror — this is a **necessary** translation, not an optional one, and should be recorded once as an intentional change (identifier scheme change, no observable product impact since the id is never shown to users per `docs/domain/entities.md`). |
| `public.projects` | `App\Models\Project` | Preserve `title`, `goal`, `description`/`descriptionTemplate` (→ a single rich-text column using whatever editor replaces Slate, see `docs/design/components.md` `RichTextEditor` note), `location` (jsonb → cast array/value object), `period` (jsonb → cast), `team`/`teamTemplate`, `motto`, `visibility`, `contact`, `user_id`, timestamps. Add a proper `visibility` enum/CHECK per the intentional fix below. |
| `public.requests` | `App\Models\Request` (or `ProjectRequest` to avoid clashing with `Illuminate\Http\Request` — **naming decision needed**, see `docs/rewrite/architecture-decisions.md`) | Preserve `title`, `description`/`descriptionTemplate`, `category` (free text historically — do not invent an enum without frontend confirmation of the real category value set), `project_id`, timestamps. |
| `public.projects_analytics` | `App\Models\ProjectAnalytics` (or fields directly on `Project` — **decision needed**, it was a 1:1 extension table historically for migration-ordering reasons that don't apply to a fresh schema) | `views`/`contactRequests` become server-incremented only (controller/action increments them; never client-writable), fixing the open-write defect. |
| `public.leads` | `App\Models\Lead` | **Implemented (slice 9):** `name`, `email` (unique), `created_at` kept; `hasConfirmed` → `confirmed_at`; `privacy` → the consent record (`requested_at`, `source`, `consent_version`; decision A-1). No `user_id` FK (email-matched only), as historically. No `LeadPolicy`: nothing lets a user name another lead (`docs/security/authorization-matrix.md`). |

## Authentication

| Historical | Nusszopf 2 | Notes |
|---|---|---|
| Auth0 tenant (Universal Login, database connection, Google/Apple social, Attack Protection, breached-password detection) | Laravel's built-in authentication (`Auth::attempt`, `Illuminate\Auth\Passwords` for reset) + Laravel Socialite (Google only — Apple was never actually wired up historically, see `docs/authentication/README.md` §3) | Full mapping table already exists in `docs/authentication/README.md` §7 — this row is a pointer, not a duplicate. |
| `auth-login` / `auth-password` (separately deployed Next.js apps, required only because Auth0 needs hosted custom-UI pages) | Ordinary Laravel routes/controllers + Blade/Livewire views inside the single monolith | The three-app split was pure Auth0 plumbing; collapsing it to one app is not a product change. |
| `hasuraIdToken` Auth0 rule (JWT claims minting two roles: `user`, `anonymous`) | Laravel's own auth guard (authenticated vs. guest) feeding Policies | No GraphQL role claim system exists to replace — Policies read `Auth::user()` directly. |
| `syncWithHasura` Auth0 rule (JIT-provision `users` row on first login) | Ordinary `User::create()` inside the registration controller | No more "provision on first login" gap — the row exists the moment registration succeeds. |
| `userPicture` Auth0 rule (copy social avatar on every login) | A `Socialite` callback step: if the user has no `picture` set (**not** unconditional overwrite — this closes the historical "silently overwrites a manually uploaded picture" gap, see `docs/domain/workflows.md` and `docs/rewrite/open-questions.md`) | **Behavior change from historical**, requires approval and recording in `docs/rewrite/intentional-changes.md` once accepted — do not silently decide this. |
| Auth0 Attack Protection (IP-based brute-force lockout) | Laravel's `throttle` middleware on auth routes | Exact thresholds Unknown historically (Auth0 tenant config, not in either repo) — a fresh, documented decision, not a port. |
| Auth0 breached-password detection | Optional: a breached-password check package, or omit | Not evidenced as safe to treat as in-scope for a first slice — flag as a decision. |

## Search

| Historical | Nusszopf 2 | Notes |
|---|---|---|
| Hasura event trigger webhook → (unidentified indexer service) → Meilisearch `items` index (`docs/search/README.md`) | Laravel Scout with the Meilisearch driver, driven by Eloquent model observers (the same observers that replace the event-trigger webhooks above) | Preserve: single combined searchable surface across `Project` and `Request` content if that's confirmed to be how `items` worked (Inferred, not Confirmed — see `docs/search/README.md`); ranking rule appending `desc(updated_at)` as a tie-break; visibility filtering (private projects/requests must not appear in public search results, which needs an explicit query-time filter given the confirmed `Request`-visibility permission gap). |
| Manual Meilisearch index setup via Postman (`docs/search/README.md`) | Scout's `artisan scout:sync-index-settings` (or an equivalent explicit, versioned settings file) | This is a **process improvement**, not a behavior change — index configuration becomes reproducible/versioned instead of a one-off manual step, closing a real historical operational gap. |

## Email

| Historical | Nusszopf 2 | Notes |
|---|---|---|
| MJML templates compiled ahead of time, some hosted as static assets on SendGrid's CDN, some as SendGrid dynamic templates edited via their dashboard (`docs/email/README.md`) | Laravel Mailables + Blade Markdown/MJML-equivalent templates, version-controlled in the Laravel app | Preserve exact German copy verbatim (per `docs/email/README.md`'s "Verbatim" markers) and the visual structure (logo, color bands, footer) documented there — this is a hard visual-fidelity requirement, not just a content one. |
| Auth0-triggered emails (welcome, change-password, blocked-account, password-breach-alert) | Laravel Notifications fired from the auth controllers/events that replace the corresponding Auth0 rule/feature (registration, password reset, rate-limit lockout, optional breach check) | One-to-one behavioral mapping once the corresponding auth mechanic above is decided. |
| SendGrid-triggered emails (contact, newsletter subscribe/welcome/unsubscribe, support) | Laravel Notifications/Mailables fired from the equivalent Laravel routes/controllers | Preserve the confirmed bug fixes already flagged in `docs/email/README.md` (missing `Reply-To` on contact form, "Bestätigte"→"Bestätige" typo) as intentional corrections once approved, not silent carries. |
| SendGrid as the transactional mail provider | Laravel's own mail abstraction: **Resend recommended** (verified in P-13), SMTP supported — **self-hosting decision**, not a product one | Per `.claude/rules/06-self-hosting.md`, operators need a documented way to configure outbound mail; nothing is hard-coupled to a vendor and Nusszopf has no provider abstraction of its own (`docs/rewrite/decisions-register.md`, "Mail provider"). |

## Frontend / UI

| Historical | Nusszopf 2 | Notes |
|---|---|---|
| Next.js pages (`webapp/src/pages/**`) | Laravel routes → Livewire full-page components (or plain Blade views for static pages: legal notice, legal policy, privacy) | Route-for-route mapping already in `docs/design/screens.md`'s route inventory table. |
| `ui-library` (Storybook atoms/molecules/organisms/templates) | Blade components (`resources/views/components/`) + Tailwind CSS 4 `@theme`/`@layer components` reproducing the exact tokens in `docs/design/visual-language.md` | The atomic-design boundaries (atoms/molecules/organisms/templates) map reasonably directly onto a Blade component directory structure — preserve the boundary, not necessarily the exact folder names. |
| `reakit` (Menu, Dialog primitives), `@reach/dialog`, `@reach/tabs`, `@reach/combobox` | Livewire component state + Alpine-free CSS/HTML (native `<dialog>`, `<details>`, or minimal Livewire-driven show/hide) where the interaction is simple; a small amount of client-side JS only where a documented interaction genuinely requires it (per `CLAUDE.md`'s "smallest client-side solution" instruction) | Do not adopt Alpine.js or Vue as an architectural requirement; evaluate case by case against `docs/design/components.md`/`states.md`. |
| `slate`/`slate-react` rich-text editor | An open decision — needs a modern rich-text approach for Livewire (e.g. a Livewire-compatible WYSIWYG, or a deliberately simpler Markdown-based editor if that's an approved product simplification) | The historical Slate integration had a known upstream bug (`docs/design/screens.md`, project-creation section) — this is a case where "more robust where the historical implementation had bugs" directly applies; needs an explicit decision, not a silent pick. |
| `react-masonry-css` (Masonry grid) | CSS `columns`/grid, or a small Livewire/Alpine-free JS helper if column-balancing genuinely needs it | Likely achievable with pure CSS multi-column layout at the documented breakpoints; confirm during implementation rather than assuming a JS dependency is needed. |
| Next.js client-side route-change loading bar (350ms-debounced "rainbow" bar) | Livewire's built-in loading states / a small custom listener on `livewire:navigate` events, reproducing the exact debounce and gradient animation from `docs/design/visual-language.md` | A genuinely nice historical UX detail worth preserving exactly, not just "add a spinner." |
| `next-seo` (SEO tags, OG images, canonical URLs) | Laravel Blade `<head>` partial + a small SEO helper (no package strictly required) | Preserve: German `de_DE` locale, canonical URL construction, OG image fallback, `noindex` on all non-production environments (confirmed behavior in `Page.js`: `noindex={process.env.ENV !== 'production' ? true : noindex}`). |

## Infrastructure

| Historical | Nusszopf 2 | Notes |
|---|---|---|
| Vercel (webapp + auth apps hosting) | Docker Compose self-hosting (per `docs/handbuch/installation.md`) | Complete replacement — Vercel's serverless model has no self-hosted equivalent needed; this is infrastructure, not product. |
| Heroku (`be-nusszopf/heroku.yml`, Hasura hosting) | Same Docker Compose stack, `php-fpm`/`postgres`/`redis`/`meilisearch` services | See `docs/handbuch/installation.md`. |
| DigitalOcean Spaces (profile pictures, Meilisearch droplet) | S3-compatible object storage via Laravel's filesystem abstraction (self-hosted MinIO or any S3-compatible provider, operator's choice) — **decision needed**: is object storage required for a first vertical slice, or can avatars start as local disk storage with S3 as a documented upgrade path? | Flag in `docs/rewrite/architecture-decisions.md`. |
| Standalone nginx CORS proxy in front of Meilisearch (`meilisearch/nginx-cors-proxy.conf`) | Not needed — Meilisearch is called server-side from Laravel (via Scout), never directly from the browser, so there's no CORS concern to proxy around | A simplification enabled by the architecture change (server-rendered Livewire vs. a client-side SPA calling Meilisearch's HTTP API directly), not a feature loss. |

## Confirmed absent — do not invent equivalents for

Per `docs/domain/entities.md`/`workflows.md`: no admin/staff role, no moderation workflow, no messaging/notification entity, no payment/donation entity, no multi-tenant/SaaS concept anywhere in the historical evidence. None of these should appear anywhere in the mapping above, and none should be introduced in Nusszopf 2 without new, explicit, approved evidence.
