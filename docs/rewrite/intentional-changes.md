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
- Tests: `tests/Feature/Projects/ProjectFormTest.php`, "denies a non-owner from editing another users
  project" — updated to assert `404` instead of `403`.
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

## Explicitly deferred (not proposed here, need a product decision first — see `docs/rewrite/open-questions.md` / `docs/rewrite/architecture-decisions.md`)

The following were identified during archaeology as *possible* candidates for change but are deliberately **not** proposed above, because reasonable product intent could explain the historical behavior as-is:

- Native `window.confirm()` for destructive actions (vs. a custom styled dialog) — see open question.
- Login always returning to `/user/projects` regardless of referring page — see open question.
- No email-verification gate before use — historically confirmed absent; treated as product-fidelity default (preserve), not a defect, unless explicitly overridden.
- Apple social login button (present, disabled, never wired) — recommendation is **do not implement**, i.e. drop the dead UI entirely rather than "fix" it into a working feature, since it was never a real historical capability. This will need its own entry once the auth UI is actually built, if the disabled button's mere *presence* is judged worth reproducing pixel-for-pixel (unlikely) vs. simply omitted (likely) — flag for that decision at implementation time.
