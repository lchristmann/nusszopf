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

- Status: Approved (implemented in the fifth slice, 2026-09-22)
- Date: 2026-09-18
- Historical behavior: `projects_analytics.views`/`contactRequests` are insertable/updatable by **any** caller (`anonymous` or `user`) for **any** `project_id`, with no ownership check — confirmed in `docs/domain/permissions.md`, `docs/domain/entities.md`, `docs/security/README.md`. The view counter's row-creation/increment mechanism is a client-side `localStorage['nusszopf_viewed_projects']` dedupe (`pages/projects/[id].js`), excluding the owner; `contactRequests` has no confirmed call site (BUG-017, resolved dead, not reproduced).
- Why it is defective/incomplete or why change is required: this is a genuine, exploitable defect — any visitor can set any project's view counter to an arbitrary value, including for projects they don't own or that are private. Nothing in the product's intent (a simple visitor/engagement counter) requires this to be client-writable at all.
- New behavior: `views` is incremented exclusively by `App\Livewire\Projects\ProjectDetail::recordView()`, never exposed as a directly client-writable field/column through any authorization boundary. The dedupe mechanism (register B-4) is a signed cookie (`nz_viewed_projects`), not `localStorage` — the increment now happens server-side, so there is no client script left to read `localStorage` from — with the same "not spoof-proof" property the historical mechanism had (clearing cookies re-counts). The owner's own views are still excluded.
- Affected screens: Project detail (`docs/design/screens.md`) — the `VisitorCounter` digit display and the "Projekt melden" report link, both previously scaffolded out, now render.
- Affected domain: `Project`/`ProjectAnalytics` (`docs/domain/entities.md`, `docs/domain/permissions.md`); `ProjectAnalytics` is its own table (register B8), keyed by `project_id`, `views` only.
- Affected workflows: "view counting" (`docs/domain/workflows.md`). No `contactRequests`-incrementing workflow was ever confirmed; it is not reproduced (BUG-017's own entry covers that decision).
- Migration implications: `database/migrations/2026_09_22_090000_create_project_analytics_table.php` (new table, no existing data affected).
- Tests: `tests/Feature/Projects/ProjectAnalyticsTest.php` — first-visit creates the row at 1, further visits increment, cookie dedupe (same browser, different project), owner never counted, an authenticated stranger is counted, no route exposes a client-writable counter (the closed exploit path), cascade delete; `tests/Feature/Projects/ProjectDetailContentTest.php` — digit rendering and the `+9999` cap; `tests/E2E/specs/visitor/project-detail.spec.ts` — the counter increments once per guest browser, never for the owner.
- Approval: Approved (the maintainer's approval of the master roadmap, 2026-09-21, which schedules it as slice 5).

---

### `Request` visibility inherits from its parent `Project`

- Status: Approved (implemented in the third slice, 2026-09-21)
- Date: 2026-09-18
- Historical behavior: `requests.select_permissions.filter` is `{}` for both roles — a request under a **private** project is still selectable if reachable by any query, independent of the parent project's `visibility`. Confirmed in `docs/domain/permissions.md`, `docs/domain/relationships.md`.
- Why it is defective/incomplete or why change is required: this is inconsistent with `Project`'s own, clearly-intentional visibility model (private projects are hidden from everyone but the owner) — there is no plausible product reason for a private project's requests to leak independently of the project itself; it reads as an oversight in the permission rules, not a considered design.
- New behavior: a `Request` is only publicly visible when its parent `Project.visibility === 'public'` (or the caller owns the parent project) — enforced the same way `Project`'s own visibility is enforced (a policy/query scope), not left to whatever query happens to reach it.
- Affected screens: Search results, Project detail (request lists).
- Affected domain: `Request` (`docs/domain/entities.md`, `docs/domain/permissions.md`, `docs/domain/relationships.md`).
- Affected workflows: none change in intent, only enforcement.
- Migration implications: none.
- Tests: `tests/Feature/Projects/ProjectRequestAuthorizationTest.php` (an allow and a deny case per `authorization-matrix.md` "Request" row; the `visible()` scope agrees with the policy for a guest, a stranger and the owner, and follows the project turning private/public), `ProjectRequestDetailTest.php` ("404s a private project with requests…", "does not show the requests of a project once it turns private"), `ProjectRequestEditTest.php` (a request of another project is never reached through the dialog, its id being client-supplied), `tests/Feature/Search/ProjectRequestSearchTest.php` (never indexed for a private project; a stale document of a private project never reaches the search page); `tests/E2E/specs/user/project-requests.spec.ts` (a visitor gets a 404 for the private project and never sees its request).
- Approval: Approved (the maintainer's approval of the master roadmap, 2026-09-21, which schedules it as slice 3).

---

### Social-login avatar sync only fills an empty picture, never overwrites

- Status: Approved — implemented in the seventh slice (2026-09-22)
- Date: 2026-09-18
- Historical behavior: `be-nusszopf/auth0/rules/userPicture.js` unconditionally overwrites `users.picture` from the social provider's avatar on **every** social login, with no guard comparing against an existing/manually-set value. Confirmed in `docs/domain/workflows.md` ("Workflow: profile picture replacement").
- Why it is defective/incomplete or why change is required: a user who uploads a custom avatar and later logs in again via Google/Apple would have it silently reverted — there is no plausible product intent behind destroying a user's own choice on every login; this looks like an oversight (the rule likely predates avatar upload existing, or was never revisited after it was added).
- New behavior: the social-avatar sync only sets `picture` when it is currently empty/unset; an existing (including manually-uploaded) picture is never overwritten by a subsequent social login.
- Affected screens: Profile (`docs/design/screens.md`), Avatar component (`docs/design/components.md`).
- Affected domain: `User.picture` (`docs/domain/entities.md`).
- Affected workflows: "Workflow: account creation / sync on first authentication," "Workflow: profile picture replacement" (`docs/domain/workflows.md`).
- Migration implications: none.
- Tests: Feature test — a user with a manually-set picture who logs in via Google keeps their picture; a user with no picture who logs in via Google gets the social picture.
- Approval: Approved (the maintainer's approval of the master roadmap, 2026-09-21, which schedules it as slice 7). `tests/Feature/Auth/GoogleLoginTest.php`.

---

### E-mail verification, gating only the personal contact and the future newsletter subscription (BUG-030)

- Status: Approved — implemented in the seventh slice (2026-09-22)
- Date: 2026-09-22
- Historical behavior: no `email_verified` concept existed anywhere in historical Nusszopf — no
  template, no Auth0 rule, no gate on login/registration. Confirmed in `docs/authentication/README.md`
  §2, §8.
- Why it changes: this is not a defect fix — the maintainer's decision (A-3,
  `docs/rewrite/decisions-register.md`, 2026-09-21) is a deliberate product change: an unverified
  address is too weak a basis for two specific, higher-trust actions (publishing it as a project's
  public contact; the future newsletter subscription, slice 9), even though nothing about
  login/registration itself was ever broken.
- New behavior: `users.email_verified_at` (nullable timestamp). Registration and every newly-created
  Google account send `App\Mail\VerifyEmailMail` (new copy — no historical template to mirror; a
  signed link, 7-day expiry, that only marks the address verified, never signs the visitor in). Login
  and registration remain completely ungated by verification, exactly as historically. The project
  wizard's and edit screen's settings step reject choosing "Persönlich" (the owner's own e-mail as
  public contact) while `hasVerifiedEmail()` is false, with a resend-verification action next to the
  error. Google login (see the "Social-login avatar sync" entry above and the Google-login decision
  below) links an existing account by e-mail, or creates a new one, only when Google itself asserts
  the address is verified — and a Google-authenticated account is always marked verified immediately,
  since there is nothing further to confirm.
- Affected screens: project creation wizard step 4 ("Einstellungen"), project edit "Einstellungen".
- Affected domain: `User.email_verified_at` (new column); `Project.contact` (no schema change, new
  save-time rule).
- Affected workflows: registration, Google login, "Workflow: set project contact" (the "Persönlich"
  branch only).
- Migration implications: `database/migrations/2026_09_22_120000_add_auth_completion_columns_to_users_table.php`
  adds the column, defaulting every existing row to unverified (there are none yet — A-2, no data
  import).
- Tests: `tests/Feature/Auth/EmailVerificationTest.php` (mail sent, non-gating of login, signed-link
  verify/expiry/tamper, resend); `tests/Feature/Projects/ProjectEditTest.php` and `ProjectWizardTest.php`
  ("Persönlich" gating, allow/deny); `tests/Feature/Auth/GoogleLoginTest.php` (link/create only when
  Google asserts verified, always pre-verified on creation).
- Approval: Approved (the maintainer's decision A-3, ratified by the master roadmap's slice-7 scope).

---

### Google login (Socialite), password reset, welcome mail and login-lockout notice — new functionality, not bug fixes

- Status: Approved — implemented in the seventh slice (2026-09-22)
- Date: 2026-09-22
- This entry exists only to record that these were built to already-specified historical behavior
  (`docs/authentication/README.md` §3, §4, §6; `docs/email/README.md` items 1–3) and the roadmap's own
  adopted defaults (register B-6, B-7, B-12), not as a bug-fix-protocol item — no historical defect is
  being corrected here, so there is nothing to classify as Fix/Preserve/Replace in `bugs.md` beyond
  what already exists. Recorded per `CLAUDE.md`'s "leave no docs stale" rule, alongside three
  deliberate implementation choices worth naming:
  - **Login lockout is per-account, not per-IP** (register B-7's "send the notice only to the account
    owner when lockout is per-account" — Auth0 itself blocked the IP; Nusszopf 2 keeps the existing
    first-slice per-IP rate limit *and* adds a second, per-account limiter, because only the latter
    lets the app identify a real account to notify). `App\Mail\BlockedAccountMail` drops the
    historical `{{ user.city }}`/`{{ user.country }}` clauses — no geo-IP lookup service is a
    dependency this self-hosted app should require (same category as the ui-avatars.com and
    SendGrid-CDN-logo replacements) — the source IP is kept.
  - **The "Das bin ich!" unblock link actually clears the specific IP/account lock** (a signed URL,
    not a decorative link to a page that does nothing) — the closest faithful equivalent to Auth0's
    real unblock action without inventing a manual-unlock admin surface that doesn't exist historically.
  - **A password-visibility (eye/eye-off) toggle was added to every password field**, including the
    two that already existed from the first slice (login, register) — `docs/design/components.md`
    lists `InputGroup` as "Listed, not Read" (usage only, not measured), so this is a reasonable,
    documented reproduction of the historical `InputGroup.RightElement` pattern present on every
    historical password field (`LoginForm.js`, `SignUpForm.js`, `PasswordForm.js`), not a pixel-exact
    trace.
- Tests: `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Auth/GoogleLoginTest.php`,
  `tests/Feature/Auth/LoginLockoutTest.php`, `tests/Feature/Auth/EmailVerificationTest.php` (welcome
  mail assertion).
- Approval: Approved (the maintainer's approval of the master roadmap, 2026-09-21, which schedules
  all of this as slice 7).

---

### Contact-form email sets `Reply-To` to the visitor's address

- Status: Approved — implemented in the sixth slice (2026-09-22)
- Date: 2026-09-18
- Historical behavior: `webapp/src/pages/api/contact.js` places the visitor's email only in the rendered body copy, never as a `Reply-To` header — replying in a mail client goes to `noreply@nusszopf.org`, not the visitor. Confirmed in `docs/email/README.md` ("Suspected historical issues" #2).
- Why it is defective/incomplete or why change is required: this is a plain usability defect with no conceivable intended purpose — a contact form exists specifically so the recipient can respond to the sender.
- New behavior: the contact-form Mailable sets `replyTo($visitorEmail)`.
- Affected screens: none (email-only change, no UI difference).
- Affected domain: none.
- Affected workflows: contact-form submission (`docs/email/README.md`).
- Migration implications: none.
- Tests: Feature/unit test asserting the outgoing Mailable's `replyTo` matches the submitted email.
- Approval: Approved (the maintainer's approval of the master roadmap, 2026-09-21, which schedules it as slice 6). `tests/Feature/Mail/ContactMailTest.php`.

---

### Newsletter email copy typo fix ("Bestätigte" → "Bestätige")

- Status: Approved — implemented in slice 9 (2026-09-23), `resources/views/mail/newsletter-subscribe.blade.php` / `newsletter-unsubscribe.blade.php`
- Date: 2026-09-18
- Historical behavior: `newsletter/subscribe.mjml` and `newsletter/unsubscribe.mjml` both open with "Bestätigte deine..." (past tense/participle) where German grammar calls for the imperative "Bestätige deine...". Confirmed in `docs/email/README.md` ("Suspected historical issues" #1).
- Why it is defective/incomplete or why change is required: this is a plain grammatical error (not a stylistic choice — the identical mistake in two independent templates suggests a copy-paste of the same typo), not a deliberate brand-voice decision (the brand voice elsewhere, e.g. "Nusszopfer:in", is playful but grammatically correct).
- New behavior: "Bestätige deine..." (imperative), everywhere else in the templates left byte-for-byte as historically written.
- Affected screens: none (email-only).
- Affected domain: none.
- Affected workflows: newsletter double opt-in / unsubscribe confirmation (`docs/email/README.md`).
- Migration implications: none.
- Tests: a snapshot/content test on the compiled template asserting the corrected string.
- Approval: Approved in principle (the maintainer's approval of the master roadmap, 2026-09-21, which schedules the newsletter templates as slice 9) — implementation deferred to that slice, since building the newsletter subscribe/unsubscribe mailables now would pull slice 9 forward.

---

### Contact-form fields get server-side validation, and their output is escaped

- Status: Approved — implemented in the sixth slice (2026-09-22)
- Date: 2026-09-22
- Historical behavior: `webapp/src/pages/api/contact.js` passes `req.body.email`, `title`, `request`, `msg` straight into the SendGrid dynamic-template payload with only IP rate-limiting (10 requests/15 min, `express-rate-limit`) in front of it — no format validation, no length limits, and no confirmed HTML-escaping (SendGrid's dynamic-template escaping behavior is itself unconfirmed from this evidence). Confirmed in `docs/email/README.md` ("Suspected historical issues" #3) and `docs/rewrite/bugs.md` BUG-010.
- Why it is defective/incomplete or why change is required: malformed or oversized input reaches an outgoing email unchecked, and the escaping behavior of the historical pipeline cannot be confirmed as safe — this is a real, not merely theoretical, gap the rewrite must close rather than silently inherit.
- New behavior: the contact form (`App\Livewire\Projects\ProjectDetail::submitContact()`) validates the visitor's e-mail (required, valid format, historical 100-character cap from `ContactDialog.js`'s `maxLength={100}`) and message (required, historical 2000-character cap) with Livewire's own `$this->validate()` — the idiomatic equivalent of a Form Request in a Livewire component, not a separate HTTP-routed endpoint as historically (`api/contact.js` was a Next.js API route; here the same screen's Livewire component both renders the dialog and handles its submission, so there is no separate route to attach a Form Request to) — before the Mailable is ever built. The historical validation copy is reproduced verbatim (`docs/email/README.md`, `contact-dialog.data.js`: "Gib eine valide E-Mail-Adresse ein" / "Gib eine E-Mail-Adresse ein" / "Maximal 2000 Zeichen" / "Bitte schreibe eine Nachricht"). Output safety: the mail Blade view uses `{{ }}` (not `{!! !!}`) for every visitor-supplied and project field, so Blade's default HTML-escaping applies to the rendered e-mail exactly as it does to any other view.
- Affected screens: Project detail's "Kontaktieren" dialog (only screen this touches).
- Affected domain: none (no persisted model — the message is sent, not stored).
- Affected workflows: "Workflow: contact a project" (`docs/domain/workflows.md`).
- Migration implications: none.
- Tests: `tests/Feature/Projects/ContactFormTest.php` — rejects a missing/malformed email and an empty/over-length message with the historical copy, no mail sent (`Mail::fake()`) on a validation failure; `tests/Feature/Mail/ContactMailTest.php` — a message containing HTML/script-like text renders escaped in the outgoing mail, not raw.
- Approval: Approved (the maintainer's approval of the master roadmap, 2026-09-21, which schedules it as slice 6, and `docs/rewrite/bugs.md` BUG-010's own note that this needed an entry "before implementation" — this entry is that prerequisite).

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
- Reconciled in P-7 (P7-01): "on deploy" had not been implemented. The production entrypoint (`docker/php/entrypoint.sh`) now runs
  `scout:sync-index-settings` on every `php-fpm` start and only warns if Meilisearch is unreachable. `scripts/smoke-test.sh` checks
  that a freshly started production stack has the filterable `req_type`, the raised hit cap and the `updated_at:desc` rule.

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
- P-12 addition (2026-09-26, `docs/release/parity/P-12-queue-scheduler.md`): "visible" and "not lost" now also hold for a Redis crash (the queue is kept in Redis's append-only file, so at most about a second of queued work can be lost, instead of everything since the last snapshot) and for a worker that fails every job (`nusszopf:health` and `/health` report `failed_jobs`). Same intent, no product behavior changes. Tests: `tests/Feature/HealthTest.php`, `scripts/queue-scheduler-test.sh` (step 4).

---

### `ProjectAnalytics.contactRequests` is not reproduced

- Status: Approved (implemented in the fifth slice, 2026-09-22 — `ProjectAnalytics` has no `contactRequests` column at all)
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

### Project edit screen denies non-owners with a 404, not a 403

- Status: Approved — implemented in the first-slice verification pass (2026-09-19).
- Date: 2026-09-19
- Historical behavior: the historical edit screen's data-fetch is visibility-scoped (the same
  `GET_PROJECT` query the public detail page uses), not ownership-scoped — see `docs/rewrite/bugs.md`
  BUG-021 for full evidence. A private project's non-owner gets Hasura's `null` and a client-side
  `router.push('/404')`; a public project's non-owner gets the (already-public) data rendered, with
  the save mutation separately denied by ownership-scoped `update_permissions` if they ever tried it.
- Why it is defective/incomplete or why change is required: reproducing the historical
  read-then-fail-to-save leniency for public projects has no product value and was never a
  deliberate capability — it is a side effect of query reuse between two screens, not a considered
  feature. `docs/security/authorization-matrix.md` and `docs/design/screen-specs.md` already specify
  this screen as owner-only; the only correction needed is the *status code* used to enforce that:
  a `403` (Laravel's `Gate::authorize()` default) reveals "this project exists and isn't yours"
  through a different channel than every other unauthorized-access path in the app, which
  consistently 404s instead (`ProjectDetail`, per the resolved `/projects/{id}` SSR open question).
- New behavior: `ProjectForm::mount()` denies a non-owner editing an existing project with a hard
  `404` (`abort_if(Gate::denies(...), 404)`), not a `403` — bringing it in line with `ProjectDetail`
  and with the historical redirect's actual target (`/404`).
- Affected screens: Project edit (`/user/project/{id}/edit`).
- Affected domain: none (the underlying authorization rule — owner only — is unchanged; only the
  HTTP status code for a denial changes).
- Affected workflows: none.
- Migration implications: none.
- Tests: `tests/Feature/Projects/ProjectEditTest.php`, "404s a non-owner, public or private, never a 403"
  (originally `ProjectFormTest.php`, "denies a non-owner from editing another users project", split into the
  wizard and edit tests in the second slice) — asserts `404` instead of `403`.
- Approval: Approved (2026-09-19). Implemented in this verification pass; see `docs/rewrite/bugs.md`
  BUG-021.

---

### Period validation ignores a flexible period's stale dates (BUG-022)

- Status: Approved (implemented in the second slice, 2026-09-19)
- Date: 2026-09-19
- Historical behavior: with "Flexibel" chosen, previously typed start/end dates that are in the wrong order still fail validation ("Enddatum vor Startdatum"), blocking the step/save (`PeriodField.js`).
- Why it is defective/incomplete or why change is required: a flexible period has no dates — they are discarded when saved — so validating them blocks the visitor for an invisible, disabled value.
- New behavior: while the period is flexible, none of the period rules run. A fixed period keeps the historical order and copy: required, `dd.mm.yyyy` format, then "Enddatum vor Startdatum".
- Affected screens: Project creation wizard step 1; project edit "Beschreibung".
- Affected domain: `Project.period`.
- Affected workflows: create, edit.
- Migration implications: none.
- Tests: `tests/Feature/Projects/ProjectWizardTest.php` "does not apply period rules while the period is flexible, not even the ordering rule (BUG-022)"; `tests/Feature/Projects/ProjectEditTest.php` "does not let a stale end date block the save of a flexible period (BUG-022)"; `tests/E2E/specs/user/project-wizard.spec.ts` "validates the period fields with the historical copy".
- Approval: Approved (second-slice implementation task).

---

### Stored period dates display as the stored calendar date (BUG-023)

- Status: Approved (implemented in the second slice, 2026-09-19)
- Date: 2026-09-19
- Historical behavior: dates are stored as ISO-8601 date-times at the author's local midnight and displayed with `toLocaleDateString('de-DE')` in the viewer's time zone, shifting the shown day for viewers west of the author.
- Why it is defective/incomplete or why change is required: the value an author chose is a calendar date; showing a different day to some viewers is wrong.
- New behavior: the stored format is unchanged (ISO-8601 date-time at midnight, `App\Support\ProjectDate::toStored`); the detail and edit screens render the calendar date contained in the stored string as `j.n.Y` (the shape `de-DE` produced), independent of any time zone.
- Affected screens: Project detail; project edit.
- Affected domain: `Project.period`.
- Affected workflows: create, edit, view.
- Migration implications: none — existing historical data uses the same format.
- Tests: `tests/Feature/Support/ProjectDateTest.php` "displays the stored calendar date as j.n.Y regardless of the offset or viewer time zone (BUG-023)"; `tests/Feature/Projects/ProjectDetailContentTest.php` "shows the period exactly as stored, whatever the offset it was stored with (BUG-023)".
- Approval: Approved (second-slice implementation task).

---

### Rich-text list buttons are labelled correctly (BUG-024)

- Status: Approved (implemented in the second slice, 2026-09-19)
- Date: 2026-09-19
- Historical behavior: the bullet-list button is announced "Liste geordnet" and the numbered-list button "Liste ungeordnet" (swapped labels).
- Why it is defective/incomplete or why change is required: assistive technology announces the opposite of what the control does.
- New behavior: bullet list = "Liste ungeordnet", numbered list = "Liste geordnet". The other four labels ("Schrift dick", "Schrift kursiv", "Schrift unterstrich", "Verlinkung") are unchanged.
- Affected screens: Project creation wizard steps 1 and 2; project edit "Beschreibung".
- Affected domain: none.
- Tests: `tests/E2E/specs/user/project-wizard.spec.ts` "offers exactly the six-tool toolbar…" and the journey spec, which address the buttons by these names.
- Approval: Approved (second-slice implementation task).

---

### Two copy typos corrected (BUG-025)

- Status: Approved (implemented in the second slice, 2026-09-19)
- Date: 2026-09-19
- Historical behavior: "Peronen" (visibility info text), "gepeichert" (edit save-error toast).
- New behavior: "Personen", "gespeichert".
- Affected screens: Project creation wizard step 4 / project edit "Einstellungen"; project edit save-error toast.
- Tests: `tests/Feature/Projects/ProjectEditTest.php` "rejects a tampered visibility value instead of hitting the database constraint" (asserts the toast copy); the visibility info text is exercised by the wizard step-4 tests.
- Approval: Approved (second-slice implementation task); precedent BUG-006.

---

### A whitespace-only title or goal is rejected (BUG-026)

- Status: Approved (implemented in the second slice, 2026-09-19)
- Date: 2026-09-19
- Historical behavior: `string().required()` accepts `"   "`.
- New behavior: a title or goal consisting only of whitespace fails with the historical "Gib einen Titel ein" / "Gib ein Ziel ein"; the same holds for a request's title (`RequestForm/TitleField.js` has the identical schema).
- Affected screens: Project creation wizard step 1; project edit "Beschreibung".
- Tests: `tests/Feature/Projects/ProjectWizardTest.php` "rejects a whitespace-only title or goal (BUG-026)"; for the request title (third slice) `tests/Feature/Projects/ProjectRequestWizardTest.php` "validates a title of more than 40 characters, and a whitespace-only one is empty".
- Approval: Approved (second-slice implementation task).

---

### A project and its requests are created together (BUG-027)

- Status: Approved (implemented in the third slice, 2026-09-21)
- Date: 2026-09-21
- Historical behavior: the creation wizard inserts the project and then, in a second call, its requests; a failure of the second call reports "Sorry, das Projekt konnte nicht erstellt werden." although the project was created.
- Why it is defective/incomplete or why change is required: the message and the stored state disagree, and repeating the action duplicates the project.
- New behavior: the project and the requests created with it are written in one database transaction; if any write fails nothing is created and the same historical error toast is shown. Success shows the historical "Projekt wurde erstellt." (there is no separate request toast in the wizard).
- Affected screens: Project creation wizard (last step).
- Affected domain: `Project`, `ProjectRequest`.
- Affected workflows: create project.
- Migration implications: none.
- Tests: `tests/Feature/Projects/ProjectRequestWizardTest.php` "creates neither the project nor any request when a write fails midway (BUG-027)" and "creates nothing when a request in the form was tampered into an invalid one (BUG-027)".
- Approval: Approved (third-slice implementation, within the roadmap's authorization to fix classified defects).

---

### Request `category` becomes a constrained column; request dates use the application's time zone

- Status: Approved (implemented in the third slice, 2026-09-21; recorded here in the third-slice closure pass)
- Date: 2026-09-21
- Historical behavior: `requests.category` is free text (only the form limits it to five values); a request's "Erstellt am" is `toLocaleDateString('de-DE')` in the viewer's browser time zone.
- Why it changes: the first is pure data-integrity hardening in the same spirit as BUG-007 (`visibility`); the value set is unchanged. The second is an architecture replacement: pages are rendered on the server, which does not know the viewer's zone, so the date is formatted `j.n.Y` (the shape `de-DE` produced) in the application's time zone.
- New behavior: a `CHECK` on `companions | rooms | materials | financials | others`; dates in the application time zone.
- Affected screens: request cards and dialogs (wizard, edit screen, detail).
- Migration implications: none for new installs; historical rows with another category value would violate the constraint (none are known to exist — the form offered five values).
- Tests: `tests/Feature/Projects/ProjectRequestModelTest.php` "rejects a category outside the five real ones at the database layer", `ProjectRequestDetailTest.php` (dates on cards and in the dialog).
- Approval: Approved (roadmap-authorized Replace/hardening; see `third-slice.md`, decisions 6 and 7).

---

### Author avatar fallback no longer calls ui-avatars.com

- Status: Approved (implemented in the second slice; recorded here 2026-09-21 as a reconciliation)
- Date: 2026-09-21
- Historical behavior: a user without a picture gets an image from the third-party service ui-avatars.com.
- Why it changes: an external service the self-hosted app should not depend on (and which would receive a
  visitor's browser request for every avatar); category "dependency dropped" in the fidelity rules.
- New behavior: an initial-on-grey circle rendered by the app in the same colors, until the avatar slice
  (slice 8) adds uploaded pictures.
- Affected screens: project detail (author), My Projects.
- Tests: `tests/Feature/Projects/ProjectDetailContentTest.php` (author).
- Approval: Approved (second-slice implementation; ratified by the maintainer's approval of the roadmap, 2026-09-21).

---

### Search hits are escaped, only the highlight is markup (BUG-029)

- Status: Approved — implemented in the fourth slice; ratified by the maintainer as an intentional Fix on 2026-09-22
- Date: 2026-09-21
- Historical behavior: hit cards rendered Meilisearch's highlighted strings as raw HTML (`dangerouslySetInnerHTML`
  in `HitCard.js` and `HitRequestCard.js`), so markup in a project's or request's own text executed for every visitor.
- Why it is defective: stored cross-site scripting on the public search screen; nothing in the product relies on
  authors' markup being interpreted (the highlight is the only intended markup).
- New behavior: the query asks Meilisearch to mark matches with two private-use characters; the text is HTML-escaped and
  exactly those markers become `<em>…</em>`. The visible result is identical to the historical one for ordinary text.
  The summary line is cut at 90 visible characters like lodash `truncate`, without counting the markers and without
  cutting a tag in half.
- Affected screens: search (hit cards, nested request cards).
- Migration implications: none.
- Tests: `tests/Feature/Search/SearchHighlightTest.php`, `ProjectSearchTest.php` ("escapes the stored text and highlights only
  what the engine marked"), `SearchEngineTest.php` ("highlights the matches and escapes everything else").
- Approval: Approved (maintainer, 2026-09-22).

---

### Search shows only what anyone may see, and re-checks every document (search-completion slice)

- Status: Approved (roadmap-authorized hardening, "private/hidden never returned, stale index documents filtered"); recorded 2026-09-21
- Historical behavior: the index held only public projects (write-time gate); the page showed whatever the index returned.
- New behavior: in addition, every project and request of a result is looked up again with `Project::visible(null)` /
  `ProjectRequest::visible(null)` — the *guest's* view, for owners too — and a document whose row is gone, is private, or whose
  request now belongs to another project is dropped. A signed-in owner does not see their own private project through search
  (it is not in the index, and a stale document must not change that).
- Affected screens: search. Tests: `ProjectSearchTest.php` (stale project, private-for-owner, stale request), `SearchEngineTest.php`.

---

### Recovery, uncapped paging and a page-size setting (BUG-008 completed)

- Status: Approved (roadmap-authorized: "documented reindex/recovery command and an idempotency test"); recorded 2026-09-21
- Historical behavior: no reindex operation existed; Meilisearch v0.19 reported every hit (`nbHits`) without a cap.
- New behavior: `php artisan search:reindex` applies the versioned index settings, drops every document and imports all
  public projects and requests (idempotent); `pagination.maxTotalHits` is raised to 100000 because current Meilisearch caps
  reachable hits at 1000, which would silently end "Mehr laden"; `SEARCH_PAGE_SIZE` (default 50, the historical value) is a
  setting only so the browser suite can reach the last page without creating dozens of projects.
- Tests: `ReindexSearchTest.php` (empty index → equals live-synced, idempotent, stale documents removed, missing index,
  failure reported), `SearchEngineTest.php` (settings, paging boundary), `search.spec.ts` (recovery).

---

### Avatar uploads are re-validated and re-encoded server-side (BUG-031)

- Status: Approved (roadmap-authorized, "server validation/re-encode ≤1 MB" — `docs/rewrite/master-roadmap.md`, "Slice 8"); recorded 2026-09-23
- Date: 2026-09-23
- Historical behavior: `pages/api/upload.js` issues an S3 presigned POST constrained only by a ≤1 MB `content-length-range`
  condition; nothing server-side decodes or re-validates the uploaded bytes — the round 150×150 crop/compress is entirely
  client-side (`AvatarDialog.js`, `react-easy-crop` + `compressorjs`).
- Why it is defective/incomplete or why change is required: a request built by hand rather than through the real crop
  dialog can upload any ≤1 MB file with a `.jpeg`-shaped key, of any actual content, format or dimensions — a real,
  demonstrable server-side trust gap (`.claude/rules/05-engineering-quality.md`: do not trust the client crop).
- New behavior: `App\Support\AvatarUploader` decodes every upload with GD, rejects anything that does not decode as a
  raster image, center-crops it to a square, caps it at 150×150, and re-encodes it as a fresh JPEG (quality 60, the historical
  compressorjs setting) before it is ever stored
  or served — independent of what the client claimed. The historical outcome (a small, square, JPEG avatar) is unchanged;
  only the trust boundary moves server-side. (Reconciled in P-6, PERF-02: the implementation had stored 512×512 at quality 85,
  which contradicted "unchanged"; it now stores the historical 150×150 at 60. `docs/release/parity/P-06-performance.md`.)
- Affected screens: Profile (avatar dialog).
- Affected domain: `User.picture`.
- Affected workflows: "profile picture replacement" (`docs/domain/workflows.md`).
- Migration implications: none.
- Tests: `tests/Feature/Profile/AvatarUploadTest.php` — a non-image file with a spoofed `image/jpeg` content-type is
  rejected; an oversized/non-square source image is still stored bounded and square.
- Approval: Approved (roadmap-authorized; see `docs/rewrite/master-roadmap.md`, "Slice 8", "Human approval: no").

---

### Avatar storage moves to the local disk, with a version counter instead of a parsed filename

- Status: Approved (register B4 "local disk in v1", B8 "storage layout and serving path"); recorded 2026-09-23
- Date: 2026-09-23
- Historical behavior: avatars are stored in DigitalOcean Spaces (S3-compatible object storage) under a key that encodes its
  own version, `{userId}|nz_v{n}.jpeg` — the *next* version is recovered by parsing that string back out of the previous
  `picture` value on every upload (`pages/api/upload.js`'s `createFilename`).
- Why it changes: register decision B4 (no object storage in v1) already settled *where* avatars live; **B8** is settled
  here — `laravel-storage`'s local `public` disk (`docs/deployment/README.md`, "Avatar storage and serving"). A dedicated
  `users.avatar_version` counter, rather than parsing the counter back out of a stored string, is the same category as
  BUG-007's `visibility` CHECK constraint: pure robustness hardening with no product-visible difference (old versions are
  still replaced, not accumulated, and the served URL still changes on every upload).
- New behavior: `avatars/{user}-v{n}.jpg` on the `public` disk; `docker/php/Dockerfile` bakes a `public/storage` symlink
  into both production images, `web` mounts `laravel-storage` read-only so nginx serves `/storage/...` directly (the real
  `compose.prod.yaml` gap the roadmap flagged, §6 "Architecture audit"); the previous file is deleted only after the new
  one is written successfully.
- Affected screens: Profile, Project detail/My Projects (author avatar).
- Affected domain: `User.picture`, `User.avatar_version` (new column).
- Affected workflows: "profile picture replacement" (`docs/domain/workflows.md`).
- Migration implications: `database/migrations/2026_09_23_090000_add_avatar_version_to_users_table.php` (new column, no
  existing data affected — no historical rows to import, register A-2).
- Tests: `tests/Feature/Profile/AvatarUploadTest.php` (version increments, old file removed after a successful replace,
  a failed upload does not delete the existing file).
- Approval: Approved (roadmap-authorized; register B4/B8).

---

### Account deletion avoids the historical orphaned-external-state risk

- Status: Approved (roadmap-authorized, "in a way that cannot orphan external state" — `docs/rewrite/master-roadmap.md`,
  "Slice 8"); recorded 2026-09-23
- Date: 2026-09-23
- Historical behavior: the `users` row is deleted immediately (a Hasura mutation, cascading `projects`/`requests`/
  `projects_analytics` at the DB level); an async webhook (`clean_up_deleted_user`) is relied on to delete the Auth0
  identity and the stored avatar file afterwards, with only 3 retries/10 s/60 s timeout and no dead-letter queue. Once that
  webhook gives up, the row that would have driven a retry is already gone, so a failure there is silent and permanent.
  Full detail: `docs/rewrite/open-questions.md`, "Account deletion and orphaned external state" (resolved there).
- Why it is defective/incomplete or why change is required: this is a real, demonstrated risk shape (a webhook whose
  failure is both silent and unrecoverable), not a product behavior worth reproducing — nothing about the product's intent
  (delete the account and everything it owns) requires the *cleanup mechanism itself* to be fire-and-forget.
- New behavior: `App\Support\AccountDeleter` deletes each owned `Project` one at a time through Eloquent (`Project::delete()`,
  not a raw DB cascade), which fires the same `deleting`/`deleted` model events `App\Models\Project::booted()` already uses
  to de-index the project and its requests — a raw `ON DELETE CASCADE` raises no model events and would silently orphan
  those search documents. The avatar file and the `users` row are removed last, inside one transaction, so a failure before
  that point leaves the account fully intact and safe to retry instead of partially deleted. There is no Auth0 identity to
  clean up (no external auth provider, `docs/authentication/README.md`).
- Affected screens: Profile (delete-account subsection).
- Affected domain: `User`, `Project`, `ProjectRequest`, `ProjectAnalytics`.
- Affected workflows: "account deletion" (`docs/domain/workflows.md`).
- Migration implications: none (cascade FKs already exist, `docs/rewrite/fifth-slice.md`).
- Tests: `tests/Feature/Profile/DeleteAccountTest.php` — cascade completeness (projects, requests, analytics), search
  documents removed, avatar file removed, `UserPolicy` self-only.
- Approval: Approved (roadmap-authorized; see `docs/rewrite/master-roadmap.md`, "Slice 8").

---

### Profile page's "Kontakt speichern" vCard link is not reproduced

- Status: **Superseded in slice 10** (2026-09-23): the vCard is generated from this instance's own addresses
  (`/contact/nusszopf-vcard.vcf`, `App\Support\Operator`) and linked again from Profile, Home and the mail footer
  (`docs/rewrite/tenth-slice.md`, decision 4; `tests/Feature/Support/OperatorIdentityTest.php`). Originally:
  Approved (extends the sixth-slice mail-footer decision, `resources/views/components/mail/layout.blade.php`);
  recorded 2026-09-23
- Date: 2026-09-23
- Historical behavior: one of Profile's two `InfoCard`s links to a static `nusszopf-vcard.vcf` download — a vCard
  hardcoding the *original* nusszopf.org's own name, organization and contact addresses, so a visitor can save "Nusszopf"
  as a phone contact.
- Why it changes: the sixth slice already established this exact reasoning for the same vCard, reached from the
  transactional-mail footer: publishing the original operator's own identity as a downloadable contact card from every
  self-hosted instance would misattribute a stranger's operator identity — the same category `docs/rewrite/master-roadmap.md`
  schedules as "operator mailbox/identity are configuration" for slice 10. Slice 8 applies the same, already-decided
  reasoning to the second place the identical link appeared, rather than silently diverging from it.
- New behavior: the "Kontakt speichern" `InfoCard` is omitted. The other `InfoCard` (the `mail@nusszopf.org` support
  address) is kept, consistent with `Project::NUSSZOPF_CONTACT`/`MAIL_FROM_ADDRESS` staying as documented defaults "for
  now" elsewhere in the app (same mail-layout precedent) — a live mailbox address is a lower-stakes default than a
  downloadable, saveable identity artifact. The Sponsoring subsection's Steady link is likewise kept as a historical
  default for the same reason (a passive external link, not an artifact vouching for an identity).
- Affected screens: Profile.
- Affected domain: none.
- Affected workflows: none.
- Migration implications: none.
- Tests: `tests/Feature/Profile/ProfilePageTest.php` — asserts the support `mailto:` renders and the vCard link does not.
- Approval: Approved (extends the sixth slice's already-approved decision, `docs/rewrite/sixth-slice.md`).

---

### Double opt-in on every newsletter path (BUG-011)

- Status: Approved — decision A-1 (maintainer, 2026-09-21); implemented in slice 9
- Date: 2026-09-23
- Historical behavior: three paths create a `Lead`. The public form (`/api/newsletter` action
  `subscribe`) creates it unconfirmed and mails a 7-day confirmation link. The registration checkbox
  (Auth0 `syncWithHasura` → action `auth0SyncHasura`) and the Profile page (`profile.js`
  `handleSubscribe`: `addLead` then `updateLead` straight from the browser) create it **already
  confirmed**, with no mail. The Profile toast then says "Du bist jetzt angemeldet!". Nothing records
  when or through which text consent was given beyond a `privacy` boolean (hard-coded `true` on the
  registration path), and an unconfirmed lead stays in the table forever.
- Why it changes: the maintainer's GDPR requirement (A-1). Consent must be provable and tied to the
  owner of the address; two of three paths never proved either.
- New behavior:
  - Every path — public form (`App\Livewire\Newsletter\SubscribeForm`, mounted by Home in slice 10),
    registration checkbox, Profile — goes through `App\Support\Newsletter::subscribe()`: a **pending**
    lead plus the same confirmation mail (`App\Mail\NewsletterSubscribeMail`). Only the link confirms.
  - Consent record on the lead: `requested_at`, `confirmed_at`, `source` (`form`, `registration`,
    `profile`), `consent_version` (`NEWSLETTER_CONSENT_VERSION`, operator-set because the operator owns
    the privacy text, decision A-4). No IP address. The historical `privacy` boolean is not kept: every
    path requires the consent checkbox, so it would always be `true`; the record replaces it.
  - Re-requesting a pending subscription refreshes `requested_at`/`source`/`consent_version` (the
    consent the visitor is about to confirm is the latest one) and resends the mail.
  - Unconfirmed leads are deleted 14 days after their latest request by the scheduled
    `newsletter:purge-unconfirmed` command (daily) — the first real scheduler task. 14 days is twice
    the link lifetime, so a lead with a still-valid link is never purged.
  - The Profile subsection's subscribe toast becomes the public form's historical
    "E-Mail verschickt! Bitte bestätige deine Anmeldung." (the old "Du bist jetzt angemeldet!" would be
    false); the subsection still shows the subscribe form until the lead is confirmed, exactly as
    historically (`!lead.hasConfirmed`). Its unsubscribe stays immediate after the native `confirm()`
    (the session already proves who owns the address).
  - Deleting an account deletes the lead with the same address (`App\Support\AccountDeleter`).
  - Registration stays fail-open for the newsletter side effect, as historically (the Auth0 rule
    swallowed every error): a failure is reported, the account is still created.
  - Relation to A-3 (an unverified address may not be subscribed): the confirmation click *is* the
    proof of ownership, so a lead is never confirmed for an address nobody verified. No separate
    `hasVerifiedEmail()` gate is added — it would contradict A-1's registration path, where the
    address is always unverified at the moment of the request.
- Affected screens: registration, Profile (newsletter subsection), the three `/newsletter/*` pages,
  Home's newsletter section (form built now, placed in slice 10).
- Affected domain: `Lead` (`docs/domain/entities.md`).
- Affected workflows: "newsletter lead creation, confirmation and cleanup", "account deletion"
  (`docs/domain/workflows.md`).
- Migration implications: new `leads` table (no data import, A-2).
- Tests: `tests/Feature/Newsletter/SubscribeTest.php` (every path pending + mail, consent record),
  `ConfirmationPagesTest.php`, `PurgeUnconfirmedLeadsTest.php`, `tests/Feature/Auth/RegistrationTest.php`
  (checkbox), `tests/Feature/Profile/ProfileNewsletterTest.php`, `DeleteAccountTest.php` (lead deleted);
  `tests/E2E/specs/visitor/newsletter.spec.ts`.
- Approval: Approved (decision A-1, `docs/rewrite/decisions-register.md`).

---

### Neutral, idempotent newsletter answers (BUG-032, BUG-033, BUG-034)

- Status: Approved — decision A-1 / register B-9; implemented in slice 9
- Date: 2026-09-23
- Historical behavior: see `docs/rewrite/bugs.md` BUG-032 (duplicate subscribe → HTTP 500),
  BUG-033 (unsubscribe-by-email → 404 for an unknown address) and BUG-034 (a valid subscribe link for a
  vanished lead → an empty success page).
- Why it changes: a server error for a normal action, a lost-mail dead end, a public list-membership
  oracle, and a false success message.
- New behavior: subscribe answers the same success toast whether the address is new, pending or
  confirmed (pending → fresh mail; confirmed → nothing changes, no mail). Unsubscribe-by-email answers
  the same success toast for every valid address and mails only a lead that exists. Confirming a
  subscription is idempotent (a second click shows the page again without moving `confirmed_at`); a
  link whose lead no longer exists is a 404 like any invalid link. The unsubscribe link stays
  idempotent and keeps showing its page after the lead is gone, as historically.
- Affected screens: the three `/newsletter/*` pages, Profile, the (slice 10) Home form.
- Affected domain/workflows: `Lead`; newsletter workflow.
- Migration implications: none.
- Tests: `tests/Feature/Newsletter/SubscribeTest.php`, `UnsubscribeTest.php`, `ConfirmationPagesTest.php`.
- Approval: Approved (decision A-1 names both neutral answers; register B-9 names the duplicate case).

---

### Newsletter mechanics: SendGrid list sync, JWT links and 307 redirects are replaced

- Status: Approved — decision A-6 and the architecture mapping; implemented in slice 9
- Date: 2026-09-23
- Historical behavior: confirming or deleting a lead fired the `sync_leads_sendgrid` Hasura trigger,
  which added/removed the address on a SendGrid marketing list that issues were sent from by hand.
  Links carried a JWT signed with `EMAIL_SECRET` (7 days). An invalid link answered with a 307 to `/404`
  (`/500` on an exception) from `getServerSideProps`.
- Why it changes: SendGrid is not a dependency of Nusszopf 2 (register B5, SMTP only); JWT/`EMAIL_SECRET`
  and SSR redirects are obsolete mechanisms (category **Replace**, product behavior unchanged).
- New behavior: the `leads` table is the only subscriber list. `php artisan newsletter:export` writes
  the confirmed subscribers as CSV for an operator's own sender; any external sender must link to this
  instance's `/newsletter/unsubscribe/lead` page so unsubscribing always happens here
  (`docs/deployment/operations.md`). Links carry a compact HMAC-signed token keyed from `APP_KEY`
  (`App\Support\NewsletterToken`), still expiring after 7 days and bound to the lead's id (a link
  cannot confirm or delete a later lead for the same address). An invalid, expired, tampered or
  unknown link renders the 404 page in place (status 404) instead of redirecting to `/404`.
- Tests: `tests/Feature/Newsletter/NewsletterTokenTest.php`, `ConfirmationPagesTest.php`,
  `ExportSubscribersTest.php`.
- Approval: Approved (decision A-6; register B5).

---

### Sitemap, `robots.txt` and Open Graph URLs use this instance's own address (BUG-035, BUG-036, BUG-037)

- Status: Approved — roadmap-authorized (`docs/rewrite/master-roadmap.md`, "Slice 10"); implemented in slice 10
- Date: 2026-09-23
- Historical behavior: the sitemap and `robots.txt` name `https://nusszopf.org` literally; `og:url` is the
  bare request path; `twitter:creator`/`twitter:site` are `@handle`/`@site` placeholders.
- Why it changes: see `docs/rewrite/bugs.md` BUG-035/BUG-036 — wrong host for every other deployment,
  an invalid relative `og:url`, and documentation placeholders shipped as data.
- New behavior: sitemap, `robots.txt`, canonical and `og:url` are built from `APP_URL`; the two
  placeholder Twitter tags are not rendered; the web manifest and `browserconfig.xml` name the icons at
  `/favicons/…`, where they actually are (BUG-037). Everything else (the three static URLs plus public projects,
  `lastmod`, the 10 / 15 min throttle, title/description truncation, `de_DE`) is unchanged.
- Affected screens: none visible; `/sitemap.xml`, `/robots.txt`, every page's `<head>`.
- Affected domain/workflows: none. Migration implications: none.
- Tests: `tests/Feature/Seo/SitemapTest.php`, `tests/Feature/Seo/SeoTagsTest.php`.
- Approval: Approved (slice 10 scope; A-5's "operator identity becomes configuration").

---

### Registration is throttled per IP instead of Auth0's bot-detection captcha

- Status: Approved — Replace (obsolete infrastructure); Claude-decidable under roadmap B-7 ("throttle
  thresholds"); implemented in the finish-line phase P-1/P-4 (2026-09-23)
- Date: 2026-09-23
- Historical behavior: registration and login ran through Auth0, whose invisible bot-detection captcha
  (`webAuth.renderCaptcha`) appeared only when Auth0 judged an attempt risky
  (`docs/authentication/README.md` §2). No app code limited sign-ups.
- Why it changes: the captcha was part of the Auth0 service, which is gone. Without a replacement anyone
  could script unlimited sign-ups, and every sign-up sends two e-mails (welcome and verification). A
  hosted captcha (hCaptcha, Turnstile) would add a mandatory third-party service, which `CLAUDE.md`
  rules out.
- New behavior: each IP address may create 10 accounts per 15 minutes, the historical budget of the other
  public forms (contact, newsletter). Submissions that fail validation do not count. Once the limit is
  reached, the form shows "Zu viele Versuche. Bitte warte kurz." under the username (the login throttle's
  copy). Operators can change the number with `NUSSZOPF_REGISTER_LIMIT`; the development/CI stack raises
  it because its browser tests register many accounts from one address.
- Affected screens: Login/Register (register tab), only when the limit is hit.
- Affected domain/workflows: account creation. Migration implications: none.
- Tests: `tests/Feature/Auth/RegistrationTest.php` ("limits each IP to 10 new accounts…", "does not spend
  the registration budget…").
- Approval: Approved (Replace of a dropped dependency; no visible behavior for a normal visitor).

---

### Accessibility to the A-7 bar: focus indicator, Escape, names, error association, headings (BUG-039, BUG-040, BUG-041)

- Status: Approved — decision A-7 (maintainer, 2026-09-21), which names the visible focus indicator as an
  intentional change; implemented in finish-line phase P-3 (2026-09-23)
- Date: 2026-09-23
- Historical behavior:
  - no visible keyboard focus (`outline-none` everywhere);
  - the request editor ignores Escape;
  - names that differ from the visible text, and unnamed controls;
  - error messages not tied to their fields;
  - no heading on some pages and paragraphs as section titles.

  See `docs/rewrite/bugs.md` BUG-039–041.
- New behavior:
  - keyboard focus (`:focus-visible`) draws a 2px outline in the text colour;
  - every dialog and popover takes, keeps and returns focus and closes on Escape;
  - Escape in the request editor is "Abbrechen", including its `confirm()` when the form is dirty; an
    outside click still does nothing;
  - accessible names contain the visible text;
  - messages are referenced by `aria-describedby`/`aria-invalid`;
  - a hidden `h1` where none existed, and `h2` section titles.
- Visible change: only the focus outline, and only for keyboard use; the visual baselines are unchanged.
- Affected screens: every screen. Affected domain/workflows: none. Migration implications: none.
- Tests: `tests/E2E/specs/a11y/axe.spec.ts`, `tests/E2E/specs/a11y/keyboard.spec.ts`,
  `tests/Feature/Views/FieldErrorTest.php`, `tests/Feature/Views/TextComponentTest.php`.
- Approval: Approved (A-7).

---

### Three colours darkened to meet the contrast minimum (BUG-042, BUG-043, BUG-044)

- Status: Approved by the maintainer on 2026-09-23 (decision A-7: every contrast fix needs the maintainer's
  approval); implemented in P-3. BUG-045 (dimmed toasts) was waived instead.
- Date: 2026-09-23
- Historical behavior: the contact dialog's validation messages are 4.20:1, the Home newsletter button's text
  4.42:1, and the menu's "Ausloggen" 2.10:1. The WCAG AA minimum is 4.5:1.
- New behavior, the historical hue kept in every case:
  - messages in the contact dialog use `warning-750` (#ae4005);
  - the Home newsletter button's text is `steel-800`;
  - "Ausloggen" is `warning-900` (#612403).

  The two warning tones are new theme tokens, each the lightest shade of `warning-700`'s hue that reaches 4.6:1 on its
  background.
- Affected screens: project page (contact dialog), Home, the signed-in nav menu. Domain and migrations: none.
- Tests: `tests/E2E/specs/a11y/axe.spec.ts` (colour contrast is gated now, with two documented exceptions); the Home
  visual baselines.
- Approval: Approved (maintainer, 2026-09-23).

---

### Security headers and a Content-Security-Policy on every response (BUG-046)

- Status: Approved — Fix, P-4 security review (2026-09-24), finding SEC-06 of `docs/release/parity/P-04-security.md`
- Date: 2026-09-24
- Historical behavior: no security headers at all (BUG-046).
- Why it changes: nothing in the browser limited what an injected script, a framing site or MIME sniffing could do.
  The web server also named its PHP and nginx versions.
- New behavior:
  - every response carries `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, a Referrer-Policy, a
    Permissions-Policy and a Content-Security-Policy that allows scripts only from the instance itself, with no
    inline scripts;
  - the one inline script (a toast flashed across a redirect) and the two inline `onclick` handlers became data
    attributes read by `resources/js/app.js`;
  - toasts show their message as text.
- Visible change: none (the visual suite is unchanged, 69 of 69).
- Affected screens: all (markup only: Privacy's "Zurück", the project banner's close button, flashed toasts).
  Domain and migrations: none.
- Tests: `tests/Feature/Security/SecurityHeadersTest.php`, `tests/E2E/specs/security/csp.spec.ts` (no CSP violation on
  the journeys, three engines), `tests/Feature/Legal/LegalPagesTest.php`, the smoke test.
- Approval: Approved (no visible behavior; Claude-decidable hardening, recorded for the maintainer's review with P-4).

---

### "Passwort vergessen" is limited per IP, newsletter mails per address (P-4)

- Status: Approved — Replace of Auth0's own limits and a threshold choice under roadmap B-7 ("throttle thresholds");
  P-4 findings SEC-03 and SEC-04 (2026-09-24)
- Date: 2026-09-24
- Historical behavior:
  - reset mails were sent by Auth0, whose own sending limits are platform configuration and unrecoverable;
  - the newsletter API was limited to 10 requests per 15 minutes per IP (Preserve, unchanged), with no limit per
    recipient.
- Why it changes: without Auth0, one client could have a reset mail sent to every registered address. A sender that
  changes its address could mail one person newsletter links without end.
- New behavior:
  - "Passwort vergessen" accepts 10 requests per IP per 15 minutes, the budget of the other public forms. Past it,
    the field shows "Zu viele Versuche. Bitte warte kurz."; invalid input does not count.
  - One address receives at most 3 newsletter confirmation mails and 3 unsubscribe mails per hour. Past that the form
    answers as before and sends nothing, so the answer still reveals nothing (BUG-032/033).
- Affected screens: Passwort vergessen (only when the limit is hit); the newsletter forms (no visible change).
- Domain and migrations: none.
- Tests: `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Newsletter/SubscribeTest.php`,
  `tests/Feature/Newsletter/UnsubscribeTest.php`.
- Approval: Approved (B-7).

---

### A password reset ends the account's other sessions (P-4)

- Status: **Approved by the maintainer on 2026-09-24** as intentional security behavior; implemented in P-4, finding SEC-10.
  The unknown historical Auth0 behavior does not block the decision.
- Date: 2026-09-24
- Historical behavior: Unknown. Sessions were Auth0/`nextjs-auth0` cookies; whether a password change revoked them
  is Auth0 tenant configuration that neither repository contains.
- Why it changes: in Nusszopf 2 as built, a session signed in elsewhere (a stolen or forgotten device) stayed valid
  for up to 8 rolling hours after the owner reset the password. Resetting the password is exactly what a person does
  when they fear someone else is signed in.
- New behavior: Laravel's `AuthenticateSession` runs on every web request. A session whose stored password hash no
  longer matches the account is logged out on its next request and lands on the login screen. Accounts without a
  password (Google-only) are unaffected.
- Affected screens: any page opened on another device after a reset. Domain and migrations: none.
- Tests: `tests/Feature/Auth/PasswordResetTest.php` ("ends every other signed-in session …", "keeps a session signed in
  while the password is unchanged").
- Approval: Approved (maintainer, 2026-09-24).

---

### Equally ranked search hits are ordered oldest first (BUG-047)

- Status: **Approved by the maintainer on 2026-09-25** as an intentional fix; implemented in P-11 (finding P11-01).
- Date: 2026-09-25
- Historical behavior: hits the ranking rules rank equal, among them always the requests of one project, came in
  Meilisearch's internal document order (BUG-047).
- Why it changes: that order depends on the order the documents were written in, so the documented recovery
  (`search:reindex`) answered with the same hits in another order than before the loss.
- New behavior: the ranking rules end in `updated_at:desc, id:asc` (`config/scout.php`). Equally ranked hits come
  oldest first, by their time-ordered UUID. For data indexed live that is normally the order they already had.
- Visible change: only where the historical rules rank hits equal: requests inside a card, and projects saved within
  the same second. Existing installations get the rule when `php-fpm` starts (the entrypoint applies the settings); no
  reindex is needed.
- Affected screens: Search. Domain and migrations: none.
- Tests: `tests/Feature/Search/ReindexSearchTest.php`, `scripts/search-recovery-test.sh`.
- Approval: Approved (maintainer, 2026-09-25): the deterministic tie-break removes rebuild-dependent ordering of
  otherwise tied results and makes search recovery reproducible, without changing the intended ranking.

---

### Home describes the current Nusszopf (demo, audiences, newsletter wording)

- Status: **Approved by the maintainer on 2026-09-29** (explicit instruction) and implemented the same day. Not a bug fix: decision A-5 chose verbatim Home copy and recorded that it would be dated ("revisit before release candidate if it reads as stale or inaccurate"); this is that revisit.
- Date: 2026-09-29
- Historical behavior: Home announced a 2021 rework ("Wir sind am Kneten…", "den veralteten ersten Prototyp"), titled the how-to "How To Nusszopf (Alte Version)" with the CTA "Alte Version entdecken", described the Zukunftspreis as a pending submission ("wir sind fest am Daumen drücken"), showed four sponsor logos ("Wir werden unterstützt von"), offered "Werde Fördermitglied!" (Steady) and "Werde Partner:in!", and presented the newsletter as one the Nusszopf sends ("Nussiger Newsletter … Wir versorgen euch …").
- Why it changes: every one of those statements is untrue of the project today. The software is the current Nusszopf, not a prototype awaiting a rework; the award was received (verified 2026-09-29: the City of Augsburg's release "Augsburger Zukunftspreise 2021 verliehen", published 17.05.2022, lists "Nusszopf – Netzwerk für gemeinsame Ideen und Projekte" as a prize of the Schülerinnen- und Schülerjury and dates the ceremony 16 May 2022; the hallo-augsburg.de report of the same event gives "Montag, den 16. Mai" without a year and agrees); the project has no funding programme, no sponsors it uses (the rewrite uses none of Vercel, Auth0, Sanity) and no partner programme; and the project runs no newsletter and plans none.
- New behavior: the "Kneten" card is gone; the how-to is "How To Nusszopf" with the CTA "Projekte entdecken" (→ `/search`); a new "Wofür ist der Nusszopf gut?" section names four audiences using only what the application does; the Contest section reports the award with a link to the report; the sponsor row and both funding/partner options are removed and the "Zopfstarke Mitstreiter:innen" section keeps its design and "Gib uns Feedback!" and adds "Betreibe Deinen eigenen Nusszopf!" and "Mach mit!" (installation guide, `CONTRIBUTING.md`; `NUSSZOPF_SOURCE_URL`); the newsletter section is "Newsletter für Deine Community" with neutral copy, and on a demo instance shows the explanation that the project runs no newsletter instead of the form. In demo mode a card in the hero slot the "Kneten" card occupied offers "Demo ausprobieren" and "Geführte Tour starten" (`docs/deployment/demo.md`). The application's newsletter feature (sign-up, double opt-in, export, Profile and registration checkbox) is unchanged.
- Also: how-to step 3 ("Gesuche") shows the number 3 like the other steps instead of the historical `Request` icon (maintainer, 2026-09-29). The audience card for Vereine & Organisationen names members, places and periods; it does not advertise legal texts.
- Visible change: Home copy and the sections above; colours, layout and components are the historical ones.
- Also removed (maintainer, 2026-09-29): Profile's "Fördermitgliedschaft" / "Steady öffnen" block — the same stale funding story. The Instagram footer link and the newsletter e-mail subjects ("Nussiger Newsletter – …") keep the historical wording.
- Affected screens: Home; Profile (the Steady block is removed for everyone; the demo account also loses account deletion and avatar editing); the login/register screen and the project contact form (demo mode only, below). Domain and migrations: none.
- Tests: `tests/Feature/Home/HomePageTest.php`, `tests/Feature/Demo/DemoTest.php`, `tests/E2E/specs/demo/demo-tour.spec.ts`.
- Approval: Approved (maintainer, 2026-09-29).

---

## Explicitly deferred (not proposed here, need a product decision first — see `docs/rewrite/open-questions.md` / `docs/rewrite/architecture-decisions.md`)

The following were identified during archaeology as *possible* candidates for change but are deliberately **not** proposed above, because reasonable product intent could explain the historical behavior as-is:

- Native `window.confirm()` for destructive actions (vs. a custom styled dialog) — see open question.
- Login always returning to `/user/projects` regardless of referring page — see open question.
- No email-verification gate before use — historically confirmed absent; treated as product-fidelity default (preserve), not a defect, unless explicitly overridden.
- Apple social login button (present, disabled, never wired) — recommendation is **do not implement**, i.e. drop the dead UI entirely rather than "fix" it into a working feature, since it was never a real historical capability. This will need its own entry once the auth UI is actually built, if the disabled button's mere *presence* is judged worth reproducing pixel-for-pixel (unlikely) vs. simply omitted (likely) — flag for that decision at implementation time.
