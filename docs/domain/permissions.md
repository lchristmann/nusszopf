# Permissions

Evidence base: `../historical/be-nusszopf/hasura/metadata/tables.yaml` (the authoritative, enforced permission rules — Hasura permissions are evaluated on every request, unlike frontend checks, which are advisory only), `../historical/be-nusszopf/auth0/rules/hasuraIdToken.js` (how the role/identity claims are minted). Backend/Hasura permission evidence is treated as ground truth per the evidence-priority rules; frontend authorization UI (what's merely hidden vs. what's actually enforced) is a separate, not-yet-done cross-reference.

## Role model

- Confirmed: exactly two GraphQL roles exist in the entire historical system: `anonymous` (the default/unauthenticated role, `HASURA_GRAPHQL_UNAUTHORIZED_ROLE: "anonymous"` in `hasura/docker-compose.yml`) and `user` (any authenticated Auth0 account, minted into the JWT claims by `hasuraIdToken.js`: `"x-hasura-default-role": "user", "x-hasura-allowed-roles": ["user", "anonymous"]`).
- Confirmed: there is **no `admin`/staff role in the application's authorization model**. The only elevated access is the Hasura `HASURA_GRAPHQL_ADMIN_SECRET`, used directly by trusted server-side code (Auth0 rules, event-trigger webhooks) — never exposed to any authenticated end user, and not a role a person can be granted. If the historical product had any staff-only capability (e.g. hiding a project, banning a user), it did not go through Hasura's role system and is Unknown/likely nonexistent.
- Every authenticated user has exactly one role (`user`); there are no per-user role variations, feature flags, or tiers visible in the backend.

## Per-resource rules

### `Lead`

| Action | anonymous | user |
|---|---|---|
| insert | denied | allowed, only where the linked `users` row (by matching email) has `id = X-Hasura-User-Id` |
| select | denied | allowed, only own linked rows |
| update | denied | allowed (columns: `created_at`, `hasConfirmed` only), only own linked rows |
| delete | denied | allowed, only own linked rows |

- Denied behavior: Hasura returns a GraphQL validation/permission error for any disallowed field/role combination; there is no partial/degraded response.
- Confirmed: `anonymous` has zero access to `leads`. Public newsletter signup, if it exists in the historical product, cannot be implemented as a direct anonymous Hasura mutation — it must go through a privileged backend endpoint. See `docs/domain/entities.md` (`Lead` → "Open cross-reference").

### `User`

| Action | anonymous | user |
|---|---|---|
| insert | denied | allowed (column: `picture` only), only own row (`id = X-Hasura-User-Id`) |
| select | allowed, columns `name`, `picture` only, any row, `limit 50` | allowed, columns `name`, `picture` only, any row, `limit 50` |
| update | denied | allowed (column: `picture` only), only own row |
| delete | denied | allowed, only own row |

- Confirmed: `email` is never selectable through the `users` table by any role, including the owner. The owner must query the `users_private` view instead (`select_permissions` role `user`, filter `id = X-Hasura-User-Id`, columns `id, email`).
- Confirmed: no role can ever change `name` through Hasura directly (not present in any `update_permissions.columns` list) — `name` is effectively set once, at account-sync time, by the Auth0 rule using the Hasura admin secret, and never editable by the user through the public API. Whether the historical frontend nonetheless offered a "change name" UI (which would have had to call a privileged backend route, not Hasura directly) is Unknown and must be checked against the frontend.
- Confirmed: any authenticated **or anonymous** caller can read any user's `name`/`picture` — there is no privacy control on these two fields.
- Account deletion side effect: deleting a `User` row cascades (DB-level `ON DELETE CASCADE`) to delete all of that user's `Project` rows (and transitively their `Request`/`ProjectAnalytics` rows) — see `docs/domain/relationships.md`. This is a real, enforced authorization/data consequence, not just a UI warning.

### `Project`

| Action | anonymous | user |
|---|---|---|
| insert | denied | allowed, only with `user_id = X-Hasura-User-Id` |
| select | allowed, only `visibility = public`, `limit 50` | allowed, `visibility = public` OR `user_id = X-Hasura-User-Id`, `limit 50` |
| update | denied | allowed, only own row (`user_id = X-Hasura-User-Id`); allowed columns include `visibility` |
| delete | denied | allowed, only own row |

- Confirmed: publishing/unpublishing a project (`visibility` private↔public) is not a distinct authorization action — it is just another column an owner can update through the same general update permission as editing content. There is no separate "publish" workflow enforced at the data layer.
- Confirmed: the `select_permissions.limit: 50` on both roles caps any single query's page size to 50 rows at the Hasura layer — relevant for pagination semantics in the rewrite (see `docs/domain/invariants.md`).
- Confirmed: an owner cannot reassign `user_id` (excluded from `update_permissions.columns`) — project ownership is immutable once created.

### `Request`

| Action | anonymous | user |
|---|---|---|
| insert | denied | allowed, only where the parent `project.user_id = X-Hasura-User-Id` |
| select | allowed, **no filter**, `limit 250` | allowed, **no filter**, `limit 250` |
| update | denied | allowed (columns: `category`, `description`, `descriptionTemplate`, `title`), only where parent `project.user_id = X-Hasura-User-Id` |
| delete | denied | allowed, only where parent `project.user_id = X-Hasura-User-Id` |

- **Suspected historical gap** (document, do not silently reproduce or silently fix — record as an open question): `select_permissions.filter` is `{}` for both roles, meaning requests belonging to a **private** project are still selectable if a query can reach them (e.g. by id, or by any listing query that doesn't itself restrict to public projects). This is inconsistent with `Project`'s own visibility model, where private projects are hidden from everyone but the owner. Recorded in `docs/rewrite/open-questions.md`.
- Confirmed: an owner may edit `Request` content but the allowed-columns list excludes `project_id` — a request cannot be moved between projects.

### `ProjectAnalytics`

| Action | anonymous | user |
|---|---|---|
| insert | allowed, **no check**, any `project_id` | allowed, **no check**, any `project_id` |
| select | allowed, **no filter**, `limit 50` | allowed, **no filter**, `limit 50` |
| update | allowed (columns: `views`, `contactRequests`), **no filter, no check** | allowed (columns: `views`, `contactRequests`), **no filter, no check** |
| delete | denied | denied |

- **Suspected historical bug — flagged, not reproduced as-is.** There is no ownership condition anywhere on this table's permissions. Any caller, authenticated or not, can set any project's `views` or `contactRequests` to an arbitrary numeric value (bounded only by the `CHECK (>= 0 AND <= 1000000)` constraint) for any `project_id`, including projects they do not own and including projects that are private. There is no increment-only semantic enforced by Hasura permissions (Hasura does support `_inc`-restricted updates via column presets in later versions, but nothing here uses that). This is recorded as a defect candidate in `docs/rewrite/open-questions.md` and `docs/security/README.md`; the rewrite should not expose an equivalent open-write counter and should instead implement view/contact-request counting as a server-side side effect the client cannot directly manipulate.

## Cross-cutting notes

- All permission enforcement observed is row/column-level SQL-style filtering evaluated per request; there is no time-based, rate-limited, or workflow-state-based authorization anywhere in this backend (e.g. no "can only edit within 24 hours" type rule).
- No permission in the entire system references any column other than `id`/`user_id`/`project_id` for ownership checks — ownership is always a direct foreign-key match to `X-Hasura-User-Id`, never a role or group.
- Backend enforcement vs. frontend checks: this file only reflects backend (Hasura) enforcement, which is authoritative. Any frontend-only restriction discovered in a later pass that is **not** mirrored here should be treated as UI convenience only, not real authorization, and explicitly flagged as such if it appears to be relied upon as security.
