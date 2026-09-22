# Workflows

Evidence base: `../historical/be-nusszopf/hasura/metadata/tables.yaml` (`event_triggers` blocks), `../historical/be-nusszopf/auth0/rules/*.js`, `../historical/be-nusszopf/hasura/docker-compose.yml` (webhook env vars). Backend evidence only establishes *what fires and when*; the actual receiving webhook implementations (Next.js API routes, most likely in `web-nusszopf`) are outside this repository and are marked Unknown/Inferred pending the frontend archaeology pass. No Hasura Actions, cron triggers, or SQL functions exist (`actions.yaml`, `cron_triggers.yaml`, `functions.yaml` are all empty) — every workflow below is driven either by an Auth0 rule or by a Hasura event trigger webhook.

## Workflow: account creation / sync on first authentication

- Actors: an end user authenticating through Auth0 (any connection: username/password, Google, Apple, etc.).
- Preconditions: user has completed an Auth0 login/signup. `user.app_metadata.synced_with_hasura` is falsy.
- Steps (Confirmed, from `auth0/rules/syncWithHasura.js`):
  1. Auth0 rule pipeline runs `syncWithHasura` on every login.
  2. If `app_metadata.synced_with_hasura` is already true, do nothing further (idempotency guard).
  3. Otherwise, normalize the Auth0 `user_id` into `auth0|...`-style form if it doesn't already contain a pipe, derive a display name from `username → name → nickname → email-local-part` (first available), and `POST` a GraphQL mutation directly to Hasura (using the **admin secret**, not a user token) inserting into `users` with `on_conflict: { constraint: user_pkey, update_columns: [] }` — an insert-or-ignore that never overwrites an existing row.
  4. On success, mark `app_metadata.synced_with_hasura = true` on the Auth0 user (persisted via `auth0.users.updateAppMetadata`).
  5. If `user_metadata.newsletter === "true"` (an opt-in checkbox presumably shown at signup), `POST` to the web app's `/api/newsletter` endpoint with `{ token, action: "auth0SyncHasura" }`, where `token` is a JWT signed with a separate email secret and contains `{ id, name, email }`. This is Inferred to create/confirm a `Lead` row (see `docs/domain/entities.md`), because `anonymous`/`user` Hasura roles cannot insert into `leads` directly, so a privileged backend route is required — the exact behavior of that route is Unknown from this repository.
  6. Regardless of outcome (success or fetch failure — note: `.catch(() => cb(null, user, context))` swallows all errors), the login callback always proceeds (`cb(null, user, context)`); a Hasura or newsletter-sync failure never blocks login. This is a deliberate fail-open design — login must never be blocked by this side effect.
- Separately, in every login (via `hasuraIdToken.js`, unconditional, not just first login): the ID token and access token both get a `https://hasura.io/jwt/claims` namespace claim set to `{ "x-hasura-default-role": "user", "x-hasura-allowed-roles": ["user", "anonymous"], "x-hasura-user-id": user.user_id, username: user.username }`. This is what makes every Hasura request from an authenticated session carry the `user` role.
- Separately, if the identity is a social login (`user_id` contains `google` or `apple`) (`auth0/rules/userPicture.js`): sync the Auth0 profile picture URL into `users.picture` via a direct admin-secret mutation (`update_users_by_pk`), every login (unconditional — this is not gated by a "synced" flag, so it runs on every social login, potentially overwriting a manually-uploaded picture each time — flagged in `docs/rewrite/open-questions.md`).
- Failure/recovery: any Hasura/network error in `syncWithHasura` or `userPicture` is caught and swallowed; the callback still succeeds. There is no retry — if the sync silently fails, the user is logged in without a `users` row (or without the newsletter lead created), and `synced_with_hasura` is never set, so the sync is **retried on the next login** (since the guard checks the flag, not the actual row's existence) — this makes the sync self-healing over subsequent logins, without an explicit "verify row exists" check.

## Workflow: newsletter lead creation, confirmation (double opt-in), and cleanup

- Actors: a lead (identified by email), the `web-nusszopf` `/api/newsletter` route, external SendGrid list.
- **Confirmed, fully resolved** (`webapp/src/pages/api/newsletter.js`, `src/utils/functions/newsletter.function.js`): a single Next.js API route, `POST /api/newsletter`, action-dispatches to five handlers, all backed by privileged (admin-secret) `addLead`/`updateLead`/`getLead`/`deleteLead` calls. Two independent creation paths exist with **different confirmation guarantees** — see `docs/domain/entities.md`'s `Lead` entry for full detail:
  1. **Public newsletter-signup form** (Home `NewsletterSection` only): submitting creates an **unconfirmed** `Lead` (`hasConfirmed = false`) and sends the `subscribe.mjml` confirmation email; clicking its link (`/newsletter/subscribe/[token]`) verifies a 7-day JWT and flips `hasConfirmed = true`. True double opt-in. Re-submitting an email that already has a `Lead` row **fails with HTTP 500** rather than resending/no-op'ing.
  **Correction (2026-09-21)**: the Profile page is not part of path 1. `profile.js` `handleSubscribe` calls `addLead` with the account email and then `updateLead` (`hasConfirmed: true`) directly from the browser, with no confirmation email — a third path with the same lack of confirmation as path 2.
  2. **Signup-time "newsletter" checkbox** (Auth0 `syncWithHasura` rule, only when `user_metadata.newsletter === "true"`): creates the `Lead` **and immediately marks it confirmed in the same request**, with **no confirmation email sent**. This is a confirmed asymmetry with path 1 for the same "I want the newsletter" user intent — flagged as a candidate historical defect for the consolidated bugs document, not a formal intentional-change proposal yet.
  3. **Unsubscribe**: the unsubscribe form (`/newsletter/unsubscribe/lead`, no token) sends an unsubscribe-confirmation email if a matching `Lead` exists (404 otherwise); clicking that email's link (`/newsletter/unsubscribe/[token]`) verifies a JWT and **hard-deletes** the `Lead` row.
- Side effect (Confirmed at the trigger level, receiving-webhook implementation still outside both repositories): the `sync_leads_sendgrid` event trigger fires on `update` of `hasConfirmed` and on `delete`, `POST`ing to `LEADS_TRIGGER_URL` — Inferred purpose: add the confirmed email to a SendGrid marketing list on confirm, remove it on delete (i.e. deletion doubles as the SendGrid-side unsubscribe mechanism).
- Failure/recovery: `retry_conf: { num_retries: 3, interval_sec: 10, timeout_sec: 60 }` on the trigger — Hasura retries the webhook up to 3 times, 10s apart, with a 60s timeout, then gives up silently (no dead-letter queue or alerting visible in this repository). Separately, the `/api/newsletter` route itself applies rate-limiting middleware (`runMiddleware(req, res, rateLimiter)`) — note this is an app-level control `docs/security/README.md`'s current "no rate limiting is configured anywhere" claim did not account for (that claim was scoped to the backend-only pass); reconcile there.

## Workflow: publish a project (visibility toggle)

- Actors: the project owner (`user` role).
- Preconditions: the caller owns the project (`projects.user_id = X-Hasura-User-Id`).
- Steps: an ordinary `update_projects` mutation setting `visibility` from `'private'` to `'public'` (or back) — there is no dedicated "publish" mutation/action; it is not distinguished from any other field edit at the data layer.
- Side effects: the `sync_projects_search` event trigger fires (visibility is in its watched update-column list) and calls `SEARCH_TRIGGER_URL` — Confirmed this is how newly-public projects get indexed and newly-private projects get removed from the public search index (or at least re-synced so search-side filtering can apply).
- Validation: none beyond the DB `NOT NULL`; nothing prevents setting `visibility` to a value other than `'private'`/`'public'` at the database layer (see `docs/domain/invariants.md`).
- Failure/recovery: same retry policy as the leads trigger (3 retries, 10s interval, 60s timeout) on the search webhook.

## Workflow: create a project and post requests under it

- Actors: an authenticated `user`.
- Preconditions: user is logged in (has a `users` row, from the sync workflow above).
- Steps: `insert_projects` with `user_id` forced to the caller's id by the permission `check` (cannot be spoofed to another user) → optionally `insert_requests` one or more times with `project_id` pointing at the newly-created project, permitted only while the caller still owns that project.
- Side effects: `sync_projects_search` fires on the project insert; `sync_requests_search` fires on each request insert; both reindex into Meilisearch regardless of the project's `visibility` at that moment (the trigger fires unconditionally on insert — filtering by visibility, if it happens at all, must happen on the search-indexing side, not in Hasura). This is an important detail for the search archaeology: private projects' requests may still be sent to the indexer, relying on the indexer or a query-time filter to keep them out of public search results — see `docs/search/README.md`.
- Failure/recovery: no visible transactionality across the two inserts — a client could create a project and fail to create requests, leaving a valid project with zero requests (apparently a normal, allowed state; nothing in the schema requires a project to have at least one request).

## Workflow: contact a project

- Actors: any visitor (anonymous or `user`) reaching a public project's detail page or one of its request dialogs; the project owner as recipient.
- Preconditions: the project is visible to the caller (`Project::scopeVisible`); read-only otherwise, no authentication required to contact.
- Steps: the visitor clicks "Kontaktieren" on the project header or on a request's dialog. If `project.contact` is the owner's own e-mail address ("Persönlich"), this is a plain `mailto:` link with no app involvement (Confirmed, `ContactDialog.js`/`[id].js` `handleContact`). If `project.contact` is the Nusszopf sentinel value ("Über Nusszopf"), a form dialog opens instead, asking for the visitor's own e-mail address and a message; submitting it (rate-limited, validated) queues a mailable to the project owner's private e-mail address, `Reply-To` set to the visitor's address, with the message escaped in the rendered mail. The dialog optionally carries the specific request that was open when "Kontaktieren" was clicked, and the mail's subject line names it ("`<project title>` / `<request title>`") — Confirmed, `contact.mjml`.
- Side effects: one queued mail send; no persisted record of the contact attempt (historically there was no `ProjectAnalytics.contactRequests` increment either — see BUG-017's resolution).
- Validation: visitor e-mail required and a valid address (max 100 characters); message required (max 2000 characters) — Confirmed, `contact-dialog.data.js`'s Yup schema. Historically these were **not** re-validated server-side (BUG-010) — Nusszopf 2 validates server-side too, not just in the dialog's own client-side schema.
- Failure/recovery: queued (`ShouldQueue`), the worker's standard retry/backoff; a permanently-failed send lands in `failed_jobs` (BUG-009's fix) rather than silently vanishing — the historical Node handler had no queue at all and relied on SendGrid's own delivery retries.
- Never exposes the owner's actual e-mail address to the visitor on this path — the whole point of "Über Nusszopf" (`docs/security/authorization-matrix.md`, "Contact actions").

## Workflow: account deletion

- Actors: the account owner (`user` role) — no separate admin-initiated deletion path exists (no admin role).
- Preconditions: caller owns the `users` row being deleted (`id = X-Hasura-User-Id`).
- Steps: `delete_users` mutation on one's own row → DB cascades (`ON DELETE CASCADE`) delete all of that user's `projects`, which in turn cascade-delete their `requests` and `projects_analytics` rows. This is a single atomic DB transaction (Hasura wraps each GraphQL mutation transactionally), so there is no partial-deletion failure mode at the DB level.
- Side effects: `clean_up_deleted_user` event trigger fires (`delete`, `columns: '*'`) → `USERS_TRIGGER_URL` webhook — Inferred purpose: also delete the corresponding Auth0 identity (management API call) and/or the user's stored profile picture asset, so the account is fully removed outside Postgres too. Not confirmed from this repository; the receiving implementation is external.
- Failure/recovery: the DB-level cascade delete happens regardless of whether the webhook succeeds — i.e. the Postgres row and all owned content are gone even if the external cleanup (Auth0 identity, stored picture) subsequently fails after retries. This is a real risk of orphaned external state (an Auth0 identity or a stored file surviving after the Nusszopf-side account is gone) and should be considered when designing the rewrite's account-deletion flow. Recorded as an open question.
- **Resolved for Nusszopf 2 (slice 8, `docs/rewrite/open-questions.md`):** there is no Auth0 identity to leak (no external auth provider). `App\Support\AccountDeleter` deletes every owned `Project` one at a time through Eloquent — not a raw DB cascade — specifically because only an Eloquent delete fires the model events that de-index the project and its requests from search; the avatar file and the `users` row are removed last, inside one transaction, so a failure anywhere before that point leaves the account fully intact and safe to retry instead of partially deleted.

## Workflow: profile picture replacement

- Actors: the account owner, or the `userPicture` Auth0 rule (social logins).
- Steps: `update_users` mutation on `picture` (manual upload — Unknown exact mechanism, likely an upload to object storage followed by a URL update) or automatic overwrite on every social login.
- Side effects: `clean_up_users_digitalocean` event trigger fires on `update` of `picture` → `USERS_TRIGGER_URL` — Inferred purpose: delete the now-orphaned previous picture file from DigitalOcean Spaces storage (per the be-nusszopf README's "DigitalOcean: Meilisearch environment" hosting note, and the trigger's name).
- **Confirmed for Nusszopf 2 (slice 8):** manual upload is `App\Support\AvatarUploader`, a Livewire temporary upload on the local `public` disk (register B4), not a presigned S3 POST. It decodes, center-crops and re-encodes every upload server-side (BUG-031, `docs/rewrite/bugs.md`) rather than trusting the client's crop — a real gap the historical upload endpoint had. The previous file is deleted the same way the historical webhook did, but synchronously and only after the new file is written successfully.
- Open question: because `userPicture.js` unconditionally overwrites `picture` on every social login (no guard comparing old vs. new value), a user who manually uploaded a custom picture and then logs in again via Google/Apple would have it silently overwritten back to their social-provider avatar, also firing an extra, possibly unnecessary storage-cleanup webhook each time. Recorded in `docs/rewrite/open-questions.md`.

## Workflows confirmed absent

- No moderation/approval workflow for projects or requests (nothing goes through a pending/review state before becoming visible).
- No email-verification-before-login gating visible in this repository (Auth0's own email verification may exist as an Auth0 tenant setting — Unknown, needs Auth0 tenant configuration evidence or frontend evidence, not present here).
- No payment/donation completion workflow anywhere in the backend.
