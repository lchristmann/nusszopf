# Relationships

Evidence base: `../historical/be-nusszopf/hasura/metadata/tables.yaml` (`object_relationships`/`array_relationships` blocks) and `../historical/be-nusszopf/hasura/migrations/*` (foreign key DDL). All statuses below are Confirmed unless noted. Full field-level detail for each entity lives in `docs/domain/entities.md`; this file is the relationship map only.

## `Lead` ←→ `User`

- Cardinality: 0/1 : 0/1, by matching `email` — **not a real foreign key**, a Hasura "manual relationship" (`manual_configuration`, `column_mapping: { email: email }`).
- Direction: `leads.user` (object) and (implicitly, not separately declared) usable in reverse for `users.lead` (object), same mapping.
- Ownership/deletion: no FK, so no cascade behavior at the DB level. Deleting a `User` does **not** delete the matching `Lead`, and vice versa — they are only linked at query time.
- Authorization implication: the `leads` insert permission's `check` (`user: { id: { _eq: X-Hasura-User-Id } }`) reaches through this manual relationship, which means a `Lead` insert is only permitted while the caller's `X-Hasura-User-Id` matches a `users` row whose `email` equals the `leads.email` being inserted — i.e. a lead can only be self-inserted by an already-registered user with that exact email, through Hasura directly. This corroborates the `entities.md` finding that public/anonymous newsletter signups do **not** go through this permission and must go through a separate backend route with elevated privileges.
- Evidence: `hasura/metadata/tables.yaml` (leads → object_relationships: user).

## `User` ←→ `users_private` (view)

- Cardinality: 1:1, by `id`. Manual relationship, `column_mapping: { id: id }`.
- Purpose: purely a permission-modeling seam to expose `email` back to its owner without widening the base table's select permission. See `docs/domain/permissions.md`.
- Evidence: `hasura/metadata/tables.yaml` (users → object_relationships: private; users_private table block).

## `User` → `Project` (owner)

- Cardinality: 1 : many. Real foreign key: `projects.user_id → users.id`.
- Ownership: `Project` is owned by exactly one `User`; required (`user_id NOT NULL`).
- Deletion behavior: `ON DELETE CASCADE`, `ON UPDATE CASCADE` (confirmed changed to CASCADE in `hasura/migrations/1606046495095_refactor_cascade_deletes/up.sql`; originally `RESTRICT`/`RESTRICT` at creation). **Deleting a user account deletes all of their projects.** This is a significant, deliberate historical behavior (not accidental — it was an explicit follow-up migration) and should be treated as a real product requirement for account deletion in the rewrite unless explicitly overridden.
- Authorization implication: every `Project` mutation permission is gated on `user_id = X-Hasura-User-Id`; ownership is the sole authorization primitive for projects (no sharing/co-ownership exists).
- UI implication: a logged-in user's own project list is `Project` filtered by `user_id`; the public list is `Project` filtered by `visibility = public`. Own private projects are visible only to the owner via the `_or` filter in `select_permissions`.
- Evidence: `hasura/migrations/1606040421666_feature__projects/up.sql`, `1606046495095_refactor_cascade_deletes/up.sql`; `hasura/metadata/tables.yaml` (projects → object_relationships: user; array_relationships on users → projects, inferred from the FK though not separately declared as an explicit array relationship in the users table block — the FK exists regardless).

## `Project` ←→ `ProjectAnalytics`

- Cardinality: 1:1. Manual relationship on the `Project` side (`analytics`, `column_mapping: { id: project_id }`); real FK on the `ProjectAnalytics` side (`projects_analytics.project_id → projects.id`).
- Deletion behavior: `ON DELETE CASCADE`, `ON UPDATE CASCADE` — deleting a project deletes its analytics row.
- Required: a `ProjectAnalytics` row is not created automatically by any trigger/default visible in the schema; it must be created by an insert (permitted for both `anonymous` and `user`, `check: {}`). This means a freshly-created `Project` may have **no** corresponding `ProjectAnalytics` row until something inserts one — Inferred that the frontend inserts a zeroed row on project-detail first view, or that a webhook does so; Unknown mechanism from backend evidence alone.
- Evidence: `hasura/migrations/1615727014588_feature_visitor_counter/up.sql`; `hasura/metadata/tables.yaml` (projects → object_relationships: analytics; projects_analytics table block).

## `Project` → `Request`

- Cardinality: 1 : many. Real foreign key: `requests.project_id → projects.id`.
- Ownership: a `Request` always belongs to exactly one `Project` (`project_id NOT NULL`); it has no direct owning `User` — ownership for authorization purposes is always resolved by joining through `Project.user_id`.
- Deletion behavior: `ON DELETE CASCADE`, `ON UPDATE CASCADE` (set in the same `1606046495095_refactor_cascade_deletes` migration as the user→project cascade). Deleting a project deletes all of its requests.
- Authorization implication (Confirmed gap, see `docs/domain/permissions.md` and `docs/rewrite/open-questions.md`): unlike `Project`, `Request`'s `select_permissions` does **not** filter by the parent project's `visibility`. A request under a private project is still selectable by `anonymous`/`user` roles if its `id` (or any query returning it, e.g. an unfiltered `requests` list query) is reachable. Whether the historical frontend ever exposed such a query is a separate, still-open question for the frontend archaeology pass.
- Evidence: `hasura/migrations/1606040421666_feature__projects/up.sql` (initial FK, originally to `users`, corrected to `projects` within the same file), `1606046495095_refactor_cascade_deletes/up.sql`; `hasura/metadata/tables.yaml` (requests → object_relationships: project).

## Relationships confirmed **not** to exist

- No `User` ↔ `User` relationship (no following/friending).
- No `Request` ↔ `User` direct relationship (must always traverse `Request → Project → User`).
- No many-to-many relationships anywhere in the schema — every relationship here is either 1:1, 1:many, or the special email-matching manual relationship between `Lead` and `User`.
- No relationship exists between `Lead` and `Project`/`Request` — newsletter leads are entirely independent of the project/request domain.
