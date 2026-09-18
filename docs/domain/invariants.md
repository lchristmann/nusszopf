# Invariants

Evidence base: `../historical/be-nusszopf/hasura/migrations/*` (DDL constraints) and `../historical/be-nusszopf/hasura/metadata/tables.yaml` (permission-enforced rules, which are also invariants in a system where the database is reached only through Hasura). Grouped by category, each marked Confirmed/Inferred/Unknown.

## Uniqueness

- `leads.email` is unique (Confirmed, `UNIQUE` constraint added in `hasura/migrations/1596263436768_alter_public_leads/up.sql`). Two leads can never share an email.
- `users.email` is unique (Confirmed, `1603373593111_create_table_public_user/up.sql`).
- `users.id` is unique/primary key (Confirmed) — the Auth0 `user_id`, so no two Auth0 identities collide.
- `projects_analytics.project_id` is unique/primary key (Confirmed) — enforces the `Project`↔`ProjectAnalytics` 1:1 cardinality at the DB level, not just by convention.
- No uniqueness constraint exists on `projects.title`, `requests.title`, or any other user-entered text field (Confirmed absence) — duplicate titles are allowed.

## Ownership

- Every `Project` has exactly one owning `User` (`user_id NOT NULL`, FK) — Confirmed, enforced by DB constraint, not just by convention.
- Every `Request` has exactly one owning `Project` (`project_id NOT NULL`, FK) — Confirmed.
- Ownership of `Project`/`Request` is immutable post-creation: neither `update_permissions` list allows changing `user_id`/`project_id` — Confirmed (permission-level invariant, not DB-level; an admin-secret call could still change it, but no ordinary role can).
- A `Lead` is not owned by any `User` at the schema level (no FK) — Confirmed; the `Lead`↔`User` link is a soft, email-based association only (see `docs/domain/relationships.md`).

## Required relationships

- A `Project` must reference an existing `User` (FK, `RESTRICT` originally then `CASCADE`; either way, orphaned `user_id` values are impossible).
- A `Request` must reference an existing `Project` (FK, cascade).
- A `ProjectAnalytics` row must reference an existing `Project` (FK, cascade) — but the reverse is not required: a `Project` can exist with zero `ProjectAnalytics` rows (no trigger/default creates one automatically — Confirmed absence of such a trigger in the metadata).
- A `Request` does not require any prior `ProjectAnalytics` state; the two are unrelated.

## Lifecycle / state constraints

- `leads.hasConfirmed` starts `false` by default and is expected to transition to `true` exactly once via the confirmation workflow (Inferred — no DB constraint prevents flipping it back to `false`, or flipping it repeatedly; the "one-way" semantic is a workflow convention, not an enforced invariant).
- `projects.visibility` defaults to `'private'` and is stored as free text with **no CHECK constraint restricting it to `{'private','public'}`** — Confirmed absence of enforcement. The only two values ever referenced anywhere in the evidence are `private` and `public` (from the `select_permissions` filter), so treat those as the Confirmed value set for product purposes, but note explicitly that nothing in the historical backend prevented a third value from being written, and the rewrite should decide whether to introduce a proper enum/CHECK constraint as a bug fix (see `docs/rewrite/open-questions.md`) rather than silently reproducing an unconstrained text column.
- `requests.category` is similarly unconstrained free text with an Unknown value set (no CHECK constraint, no enum, no categories table) — do not invent a category list; it must come from frontend evidence.
- `projects.views` (the now-removed column) was bounded `<= 10000`; its replacement, `projects_analytics.views`, is bounded `0 <= views <= 1,000,000`, and `projects_analytics.contactRequests` is bounded `0 <= contactRequests <= 1,000,000` — Confirmed CHECK constraints. These are sanity ceilings/floors only, not business-meaningful thresholds as far as any evidence shows.
- `projects.views` had a smaller ceiling (10,000) than its successor `projects_analytics.views` (1,000,000) — Confirmed from comparing the two migrations; Inferred reason: the counter was expected to be used much more heavily once true site-wide analytics replaced a rough per-project view count, or the original 10,000 ceiling was simply found to be too low in practice. Either way, do not treat 10,000 as a live constraint — only `projects_analytics`'s bounds are current.

## Permission-enforced invariants (only true because Hasura enforces them — see `docs/domain/permissions.md` for full detail)

- A caller can only ever act as `anonymous` or `user` — never a third role — Confirmed, no third role is defined anywhere.
- A `user` can only mutate rows they own (by direct `user_id`/`project_id`→owner match), except for `ProjectAnalytics`, which has **no such invariant** (see below) — this asymmetry is itself worth stating explicitly as a documented gap, not a hidden inconsistency.
- `users.email` can never be read through the `users` table by any role — the only path to read your own email is the `users_private` view, filtered to your own `id`.

## Side-effect invariants

- Deleting a `User` implies (via DB cascade) deleting all of their `Project`, `Request`, and `ProjectAnalytics` rows — Confirmed, atomic, DB-enforced. Any rewrite must preserve this cascade (or make an explicit, documented, approved product decision to change it — see `docs/rewrite/architecture-decisions.md`).
- Deleting a `Project` implies deleting all of its `Request` and its `ProjectAnalytics` row — Confirmed, same cascade guarantee.
- No invariant ties `Lead` deletion to anything else (no cascades reference `leads`).

## Suspected violated/missing invariants (do not silently reproduce; recorded in `docs/rewrite/open-questions.md`)

- `ProjectAnalytics.views`/`contactRequests` have **no invariant at all** tying writes to actual page views/contact actions, and no ownership restriction — any value within the numeric bound can be written by anyone. This is functionally "not really an invariant-protected counter" despite looking like one.
- `requests` visibility is not tied to the parent `projects.visibility` invariant that governs `Project` itself (see `docs/domain/permissions.md`) — a private project's requests are not actually private by the same rule.
- Nothing prevents a `Project` from existing with zero `Request` rows, and nothing prevents a `Request` from being queried without ever loading its parent `Project`'s visibility — both are permitted states/queries, not bugs by themselves, but relevant context for the `requests` visibility gap above.
