# Intentional Changes

Every deliberate difference from historical Nusszopf, per `CLAUDE.md`'s bug-fix protocol (document historical behavior → explain why it's incorrect → document the corrected behavior → add a regression test → record the decision here). Entries not explicitly marked `Approved` below remain `Proposed`, not `Accepted` — not implemented, and not to be implemented until explicitly approved. This is decision *tracking*, not a backlog to silently work through. The four entries marked `Approved` were implemented as part of the first vertical slice (2026-09-18), under the project kickoff's explicit authorization to proceed to implementation once `docs/rewrite/decisions-register.md` reached "READY WITH EXPLICIT DECISIONS" — each was already fully spec'd here beforehand, per that workflow.

---

### Server-side authentication gate (replace client-side flash-then-redirect)

- Status: Approved — implemented in the first vertical slice (`routes/web.php`'s `auth` middleware group).
- Date: 2026-09-18
- Historical behavior: `webapp/src/utils/hoc/withAuth.js` enforces `isAuthRequired` entirely client-side, after the page shell has already mounted — an unauthenticated visitor briefly sees the protected page's chrome before being hard-redirected to `/api/login`. Confirmed in `docs/authentication/README.md` §3.
- Why it is defective/incomplete or why change is required: this is a demonstrable robustness gap (flash-of-protected-content, a real if minor security/UX smell), and Laravel's `auth` middleware makes the correct behavior (redirect before any protected markup is ever sent) the *default*, easier path — not an exotic fix.
- New behavior: route-level `auth` middleware on every historically-`isAuthRequired: true` route (`/user/profile`, `/user/projects`, `/user/project/create`, `/user/project/[id]/edit`, per `docs/authentication/README.md` §3's confirmed list); unauthenticated requests never render any protected content.
- Affected screens: the four listed above.
- Affected domain: none (authorization outcome is identical — only *when* the redirect happens changes).
- Affected workflows: none.
- Migration implications: none (no data affected).
- Tests: a Feature test per protected route asserting a guest request never reaches the view/receives a redirect response, plus a Playwright journey confirming no protected markup appears even momentarily.
- Approval: Approved (2026-09-18). Feature tests in `tests/Feature/Auth/RouteProtectionTest.php`; the Playwright journey is tracked with the rest of the first slice's E2E coverage.

---

### `ProjectAnalytics` counters become server-controlled only

- Status: Proposed
- Date: 2026-09-18
- Historical behavior: `projects_analytics.views`/`contactRequests` are insertable/updatable by **any** caller (`anonymous` or `user`) for **any** `project_id`, with no ownership check — confirmed in `docs/domain/permissions.md`, `docs/domain/entities.md`, `docs/security/README.md`.
- Why it is defective/incomplete or why change is required: this is a genuine, exploitable defect — any visitor can set any project's view/contact counters to an arbitrary value, including for projects they don't own or that are private. Nothing in the product's intent (a simple visitor/engagement counter) requires this to be client-writable at all.
- New behavior: `views`/`contactRequests` are incremented exclusively by server-side application code (e.g. a controller action on project view / contact-button click), never exposed as a directly client-writable field/column through any authorization boundary.
- Affected screens: Project detail (`docs/design/screens.md`).
- Affected domain: `Project`/`ProjectAnalytics` (`docs/domain/entities.md`, `docs/domain/permissions.md`).
- Affected workflows: "view counting" and "contact counting" (`docs/domain/workflows.md`, if a `contactRequests`-incrementing workflow is confirmed by frontend evidence — the backend pass only confirms the column exists, not which frontend action increments it).
- Migration implications: none for existing/seed data; only the write path changes.
- Tests: Feature test asserting an unauthenticated/unauthorized request cannot set an arbitrary counter value; regression test reproducing the historical exploit path to prove it's closed.
- Approval: pending.

---

### `Request` visibility inherits from its parent `Project`

- Status: Proposed
- Date: 2026-09-18
- Historical behavior: `requests.select_permissions.filter` is `{}` for both roles — a request under a **private** project is still selectable if reachable by any query, independent of the parent project's `visibility`. Confirmed in `docs/domain/permissions.md`, `docs/domain/relationships.md`.
- Why it is defective/incomplete or why change is required: this is inconsistent with `Project`'s own, clearly-intentional visibility model (private projects are hidden from everyone but the owner) — there is no plausible product reason for a private project's requests to leak independently of the project itself; it reads as an oversight in the permission rules, not a considered design.
- New behavior: a `Request` is only publicly visible when its parent `Project.visibility === 'public'` (or the caller owns the parent project) — enforced the same way `Project`'s own visibility is enforced (a policy/query scope), not left to whatever query happens to reach it.
- Affected screens: Search results, Project detail (request lists).
- Affected domain: `Request` (`docs/domain/entities.md`, `docs/domain/permissions.md`, `docs/domain/relationships.md`).
- Affected workflows: none change in intent, only enforcement.
- Migration implications: none.
- Tests: Feature test asserting a `Request` under a private project is not returned by any query/search result the request's owner didn't make.
- Approval: pending.

---

### Social-login avatar sync only fills an empty picture, never overwrites

- Status: Proposed
- Date: 2026-09-18
- Historical behavior: `be-nusszopf/auth0/rules/userPicture.js` unconditionally overwrites `users.picture` from the social provider's avatar on **every** social login, with no guard comparing against an existing/manually-set value. Confirmed in `docs/domain/workflows.md` ("Workflow: profile picture replacement").
- Why it is defective/incomplete or why change is required: a user who uploads a custom avatar and later logs in again via Google/Apple would have it silently reverted — there is no plausible product intent behind destroying a user's own choice on every login; this looks like an oversight (the rule likely predates avatar upload existing, or was never revisited after it was added).
- New behavior: the social-avatar sync only sets `picture` when it is currently empty/unset; an existing (including manually-uploaded) picture is never overwritten by a subsequent social login.
- Affected screens: Profile (`docs/design/screens.md`), Avatar component (`docs/design/components.md`).
- Affected domain: `User.picture` (`docs/domain/entities.md`).
- Affected workflows: "Workflow: account creation / sync on first authentication," "Workflow: profile picture replacement" (`docs/domain/workflows.md`).
- Migration implications: none.
- Tests: Feature test — a user with a manually-set picture who logs in via Google keeps their picture; a user with no picture who logs in via Google gets the social picture.
- Approval: pending.

---

### Contact-form email sets `Reply-To` to the visitor's address

- Status: Proposed
- Date: 2026-09-18
- Historical behavior: `webapp/src/pages/api/contact.js` places the visitor's email only in the rendered body copy, never as a `Reply-To` header — replying in a mail client goes to `noreply@nusszopf.org`, not the visitor. Confirmed in `docs/email/README.md` ("Suspected historical issues" #2).
- Why it is defective/incomplete or why change is required: this is a plain usability defect with no conceivable intended purpose — a contact form exists specifically so the recipient can respond to the sender.
- New behavior: the contact-form Mailable sets `replyTo($visitorEmail)`.
- Affected screens: none (email-only change, no UI difference).
- Affected domain: none.
- Affected workflows: contact-form submission (`docs/email/README.md`).
- Migration implications: none.
- Tests: Feature/unit test asserting the outgoing Mailable's `replyTo` matches the submitted email.
- Approval: pending.

---

### Newsletter email copy typo fix ("Bestätigte" → "Bestätige")

- Status: Proposed
- Date: 2026-09-18
- Historical behavior: `newsletter/subscribe.mjml` and `newsletter/unsubscribe.mjml` both open with "Bestätigte deine..." (past tense/participle) where German grammar calls for the imperative "Bestätige deine...". Confirmed in `docs/email/README.md` ("Suspected historical issues" #1).
- Why it is defective/incomplete or why change is required: this is a plain grammatical error (not a stylistic choice — the identical mistake in two independent templates suggests a copy-paste of the same typo), not a deliberate brand-voice decision (the brand voice elsewhere, e.g. "Nusszopfer:in", is playful but grammatically correct).
- New behavior: "Bestätige deine..." (imperative), everywhere else in the templates left byte-for-byte as historically written.
- Affected screens: none (email-only).
- Affected domain: none.
- Affected workflows: newsletter double opt-in / unsubscribe confirmation (`docs/email/README.md`).
- Migration implications: none.
- Tests: a snapshot/content test on the compiled template asserting the corrected string.
- Approval: pending.

---

### `projects.visibility` becomes a real enum/CHECK constraint

- Status: Approved — implemented in the first vertical slice (`database/migrations/2026_09_18_180229_create_projects_table.php`).
- Date: 2026-09-18
- Historical behavior: `projects.visibility` is unconstrained `text` with a default of `'private'`; nothing at the DB layer prevents a third value from ever being written. Confirmed in `docs/domain/invariants.md`.
- Why it is defective/incomplete or why change is required: the only two values ever referenced anywhere in the evidence are `public`/`private` — this is a pure data-integrity hardening with **zero** observable product-behavior change (no third value was ever actually used), squarely inside `CLAUDE.md`'s "more robust where the historical implementation had... missing pieces" allowance.
- New behavior: `visibility` is a Postgres enum (or a `CHECK` constraint) restricted to `public`/`private`; the application-level default remains `private`, matching history.
- Affected screens: none observable.
- Affected domain: `Project.visibility` (`docs/domain/entities.md`, `docs/domain/invariants.md`).
- Affected workflows: "Workflow: publish a project" (`docs/domain/workflows.md`) — enforcement only, not behavior.
- Migration implications: none for existing data (only two values ever existed); the migration adds a constraint, not a data transformation.
- Tests: a migration/schema test asserting a third value is rejected.
- Approval: Approved (2026-09-18). `tests/Feature/Projects/ProjectVisibilityConstraintTest.php` asserts a third value is rejected at the database layer.

---

### Meilisearch index settings become versioned application config

- Status: Approved — implemented in the first vertical slice (`config/scout.php`, applied via `php artisan scout:sync-index-settings`).
- Date: 2026-09-18
- Historical behavior: at least one index (`items`) was configured by hand via a one-off Postman request against the Meilisearch HTTP API, per the historical `docs/meilisearch/prod_setup.md` documentation. Confirmed in `docs/search/README.md`.
- Why it is defective/incomplete or why change is required: this is a pure operational-robustness gap — not reproducible, not code-reviewable, easy to silently drift between environments. No product/observable-behavior change is proposed; only *how* the same settings get applied changes.
- New behavior: index settings (ranking rules, filterable/sortable attributes) live in versioned Laravel config, applied via Scout's index-settings sync tooling on deploy, not a manual API call.
- Affected screens: none.
- Affected domain: none (search *ranking behavior* is preserved exactly: default Meilisearch ranking rules plus a final `desc(updated_at)` tie-break, per `docs/search/README.md`).
- Affected workflows: none.
- Migration implications: none.
- Tests: a CI check that the checked-in index-settings config matches what's actually applied (drift check).
- Approval: Approved (2026-09-18). The CI drift check is tracked with the CI milestone; `tests/Feature/Search/MeilisearchIntegrationTest.php` exercises the applied settings (ranking rules, searchable attributes) against a real Meilisearch instance.

---

### Queue-backed sync jobs with retry/dead-letter (replacing silent-give-up webhooks)

- Status: Approved — implemented in the first vertical slice for search-sync (`SCOUT_QUEUE=true` over the Redis queue connection; `queue-worker` Compose service). Newsletter-list-sync and user-cleanup jobs are out of scope until the Newsletter module and object storage exist.
- Date: 2026-09-18
- Historical behavior: every Hasura event-trigger webhook (search sync, SendGrid list sync, user cleanup) retries at most 3 times, 10 seconds apart, then **silently gives up** with no dead-letter queue or alerting. Confirmed across `docs/domain/entities.md`, `docs/domain/workflows.md`, `docs/search/README.md`.
- Why it is defective/incomplete or why change is required: a silently-lost sync (e.g. a project never gets indexed, or a deleted user's external cleanup never runs) is a real operational risk with no visibility — this is squarely a "more robust where the historical implementation had... missing pieces" case, not a product-behavior change (the *intent* — sync search index, sync mailing list, clean up on delete — is preserved exactly).
- New behavior: the equivalent Laravel queued jobs use the framework's standard retry/backoff, and failed jobs land in `failed_jobs` (visible, retryable, alertable) instead of vanishing.
- Affected screens: none.
- Affected domain: none (same side effects, same triggers — only failure-visibility changes).
- Affected workflows: "Workflow: newsletter lead confirmation," "Workflow: publish a project," "Workflow: account deletion" (`docs/domain/workflows.md`) — failure-handling only.
- Migration implications: none.
- Tests: a Feature test forcing a sync job to fail and asserting it lands in `failed_jobs` rather than disappearing.
- Approval: Approved (2026-09-18) for the search-sync path. A dedicated failed-job test is tracked as follow-up work for this slice (see the first-slice completion report).

---

### `ProjectAnalytics.contactRequests` is not reproduced

- Status: Proposed
- Date: 2026-09-18 (pre-implementation review pass)
- Historical behavior: `projects_analytics.contactRequests` exists in the schema with the same
  bounds/permission shape as `views` (`docs/domain/entities.md`), but has **no increment call site
  anywhere in the frontend** — confirmed by an exhaustive read of both `pages/projects/[id].js`
  (which only increments `views`) and `containers/projects/ContactDialog/ContactDialog.js` (whose
  submit handler only calls `/api/contact`, no GraphQL mutation at all).
- Why it is defective/incomplete or why change is required: this is not a bug to fix so much as a
  historical feature that was never actually built — the column and its permission model exist,
  but no product behavior was ever wired to it. Reproducing it would mean inventing new behavior
  (deciding *when* it increments) with no historical evidence to base that decision on, which
  `CLAUDE.md` explicitly forbids ("Never: invent domain behavior").
- New behavior: Nusszopf 2's `ProjectAnalytics` (or equivalent) model has a `views` counter only,
  server-incremented per BUG-001's fix. No `contactRequests`-equivalent field is implemented.
- Affected screens: none (the field was never surfaced in any UI).
- Affected domain: `ProjectAnalytics` (`docs/domain/entities.md`).
- Affected workflows: none.
- Migration implications: none (a column that never existed in Nusszopf 2's own fresh schema).
- Tests: none needed beyond the absence of a `contactRequests` column/ability in the model/policy
  test suite.
- Approval: pending.

---

## Explicitly deferred (not proposed here, need a product decision first — see `docs/rewrite/open-questions.md` / `docs/rewrite/architecture-decisions.md`)

The following were identified during archaeology as *possible* candidates for change but are deliberately **not** proposed above, because reasonable product intent could explain the historical behavior as-is:

- Native `window.confirm()` for destructive actions (vs. a custom styled dialog) — see open question.
- Login always returning to `/user/projects` regardless of referring page — see open question.
- No email-verification gate before use — historically confirmed absent; treated as product-fidelity default (preserve), not a defect, unless explicitly overridden.
- Apple social login button (present, disabled, never wired) — recommendation is **do not implement**, i.e. drop the dead UI entirely rather than "fix" it into a working feature, since it was never a real historical capability. This will need its own entry once the auth UI is actually built, if the disabled button's mere *presence* is judged worth reproducing pixel-for-pixel (unlikely) vs. simply omitted (likely) — flag for that decision at implementation time.
