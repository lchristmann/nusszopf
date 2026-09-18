# Security

Evidence base for this pass: `../historical/be-nusszopf/hasura/metadata/tables.yaml`, `../historical/be-nusszopf/hasura/docker-compose.yml`, `../historical/be-nusszopf/auth0/rules/*.js`, `../historical/be-nusszopf/meilisearch/*`. This covers backend/infrastructure-visible security properties only; frontend-side concerns (CSRF on forms, XSS/escaping in rendered `descriptionTemplate` content, session/cookie handling) require the frontend archaeology pass and are marked Unknown here rather than guessed. Per project rules, historical vulnerabilities documented below are explicitly **not** product requirements — they are input to `docs/rewrite/open-questions.md`, to be fixed deliberately and documented as intentional changes, not silently carried forward and not silently "fixed" without recording the decision.

## Authentication

- Confirmed: identity is entirely delegated to Auth0. Hasura never sees a password; it only ever sees a JWT whose `https://hasura.io/jwt/claims` namespace was populated by the `hasuraIdToken` Auth0 rule with `x-hasura-user-id`, `x-hasura-default-role: user`, `x-hasura-allowed-roles: [user, anonymous]`.
- Confirmed: `HASURA_GRAPHQL_JWT_SECRET` is configured via environment variable (`hasura/docker-compose.yml`); actual value/algorithm is not present in this repository (correctly not committed).
- The rewrite replaces Auth0 with Laravel-native authentication (explicit instruction in `CLAUDE.md`) — this role model (exactly two roles: guest/anonymous, authenticated user; no staff role in the product) is the behavior to reproduce, not the Auth0-specific mechanics.

## Authorization

- Confirmed: authorization is enforced entirely at the Hasura permission layer (row/column filters keyed on `X-Hasura-User-Id`), not in any custom backend code — there are no Hasura Actions and no remote schema, so there is no custom authorization logic to inspect beyond the declarative permission rules in `tables.yaml`. Full detail in `docs/domain/permissions.md`.
- **Suspected vulnerability, flagged not reproduced:** `projects_analytics` (`views`, `contactRequests`) permits insert/update by both `anonymous` and `user` roles with no ownership filter and no check at all — any caller can overwrite any project's counters to an arbitrary value (bounded 0–1,000,000). See `docs/domain/permissions.md` and `docs/rewrite/open-questions.md`. The rewrite should implement view/contact counters as a server-controlled side effect (e.g. incremented by a controller action, never by direct client-writable columns/fields).
- **Suspected inconsistency, flagged not reproduced:** `requests` select permission has no visibility filter tying it back to its parent project's `visibility`, unlike `projects` itself. A private project's requests may be readable independent of the project's own privacy setting. See `docs/domain/permissions.md`.
- Confirmed: the admin secret (`HASURA_GRAPHQL_ADMIN_SECRET`) is the only privileged access path and is used exclusively by trusted, non-browser code (Auth0 rules running server-side, event-trigger webhook handlers) — never exposed to a browser session. The rewrite's equivalent (an internal-only escape hatch, if any is even needed) should preserve this "never reachable from an authenticated end-user session" property.

## Secrets

- Confirmed: `EVENT_SECRET` is a static shared secret sent as a `secret` header on every event-trigger webhook call (leads/search/users triggers) — a simple pre-shared-key scheme, not HMAC-signed payloads. Adequate for a single trusted backend-to-backend hop, but worth upgrading in the rewrite (e.g. HMAC signature of the payload) as a robustness improvement, not a product behavior change.
- Confirmed: `hasuraSecret`/newsletter JWT secret literals in `auth0/rules/syncWithHasura.js` and `userPicture.js` are placeholder strings (`<hasura-<env>-secret>`, `<my-email-secret>`) in this repository, i.e. the actual production secrets were injected at deploy time in the Auth0 dashboard, not committed — no secret leakage in this repository.
- `hasura/config.yaml`'s committed `admin_secret: Si0rxaWj2asd83asdUhkl3` is a **local-development-only** value (matches the doc examples throughout `docs/hasura/*.md`), not a real production secret — Confirmed by cross-referencing the identical placeholder value across multiple docs files.

## CORS

- Confirmed: production Hasura is configured with `HASURA_GRAPHQL_CORS_DOMAIN: "*"` in the **local dev** compose file — Unknown whether production used a wildcard too (not in this repository's committed config for the Heroku deployment; would need Heroku environment variable evidence). Do not assume `"*"` was used in production without further evidence.
- Confirmed: the Meilisearch CORS proxy (`meilisearch/nginx-cors-proxy.conf`) explicitly answers every request (not just `OPTIONS` preflights) with `Access-Control-Allow-Origin: *` and `Access-Control-Allow-Credentials: true` — this is a real, committed production/staging configuration (also referenced from `meilisearch/digitalocean/nginx.{stage,prod}.conf`), not a dev-only artifact. Combining a wildcard `Allow-Origin` with `Allow-Credentials: true` is a known-risky CORS pattern (browsers actually reject the *combination* when credentials mode is `include`, so the practical exposure depends on how the frontend called this API — Unknown without frontend evidence — but the server-side configuration itself is worth flagging as a pattern not to copy uncritically into the rewrite).

## Data exposure

- Confirmed, and a deliberate positive pattern worth preserving: `users.email` is never selectable through the base `users` table by any role; it is only reachable via the `users_private` view, filtered to the owner. This is good practice and should be reproduced as an equivalent access-control property in the rewrite (e.g. an authorization policy that never serializes another user's email).
- Confirmed: `users.name`/`users.picture` are publicly readable by `anonymous` with no rate limit or additional restriction — a deliberate public-profile design, not a leak, but worth naming explicitly as an intended public surface.
- Unknown: whether Meilisearch's public HTTP API (behind the CORS proxy, reachable with a key that a browser must possess to query it) used a search-only restricted API key vs. the full master key in the browser — this materially affects whether a leaked frontend key could write/delete index data. Needs frontend network evidence.

## Rate limiting

- Confirmed absence: no rate limiting is configured anywhere in this repository (no Hasura rate-limit config, no nginx `limit_req`, no Auth0 rule implementing throttling). Hasura open-source (the version pinned here, `v1.3.3`) does not include rate limiting; if it existed at all, it would have been an Auth0-tenant-level or Heroku-add-on-level control, not visible here. This is Unknown, not Confirmed-absent at the whole-system level, but Confirmed-absent within this repository's evidence.

## Docker/runtime security

- Confirmed: the Hasura Dockerfile explicitly drops root (`RUN adduser -D nzuser` / `USER nzuser`) before running the engine — a good practice worth preserving in the rewrite's own Docker images.
- Confirmed: `HASURA_GRAPHQL_DEV_MODE: "true"` and `HASURA_GRAPHQL_ENABLE_CONSOLE: "true"` are set in the **local dev** compose file only (`hasura/docker-compose.yml`); no evidence in this repository shows whether production disabled these (the Heroku env vars are not committed) — flagged as Unknown, must not be assumed either way, and the rewrite must independently ensure its own production configuration disables debug/console surfaces regardless of what historical production did.

## Summary of items requiring a decision (see `docs/rewrite/open-questions.md`)

1. `projects_analytics` open-write permission — near-certain bug, needs an explicit "will not reproduce, will implement as X instead" decision.
2. `requests` visibility not inherited from parent `projects.visibility` — needs a decision on whether to fix (Confirmed inconsistency, likely bug) or whether some now-lost frontend behavior made it safe in practice (Unknown).
3. Meilisearch CORS `Allow-Origin: *` + `Allow-Credentials: true` pattern, and whether a restricted search-only Meilisearch key was used — needs frontend evidence before deciding the rewrite's Meilisearch API-exposure model.
4. Production Hasura CORS domain and dev-mode/console settings — Unknown, needs no historical reproduction (self-hosting production config should default to secure regardless of what historical Heroku env vars were).
