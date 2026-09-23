# Historical Bugs and Incomplete Functionality

Every meaningful suspected defect or incomplete feature found during archaeology, in one place,
with a stable ID. This is the inventory; `docs/rewrite/intentional-changes.md` is where an ID
graduates into a fully-specified, approved correction (historical behavior → why defective →
corrected behavior → regression test → approval). An ID can exist here with **no** corresponding
`intentional-changes.md` entry yet — that means it is classified but not yet spec'd for a fix, or
classified as something other than Fix.

Classification follows `CLAUDE.md`: **Preserve** (reproduce as-is), **Fix** (demonstrably defective,
correct it deliberately), **Replace** (obsolete infrastructure, behavior preserved), **Unknown**
(evidence insufficient — a human product decision is required, not an inference).

| ID      | Area                                        | Severity | Classification                      | Status                                                                                  |
|---------|---------------------------------------------|----------|-------------------------------------|-----------------------------------------------------------------------------------------|
| BUG-001 | Authorization / Project analytics           | High     | Fix                                 | Implemented — fifth slice (2026-09-22)                                                  |
| BUG-002 | Authorization / Request visibility          | High     | Fix                                 | Implemented — third slice (2026-09-21)                                                  |
| BUG-003 | Auth / route protection                     | Medium   | Fix                                 | Implemented — first vertical slice (2026-09-18)                                         |
| BUG-004 | Auth / avatar sync                          | Low      | Fix                                 | Implemented — seventh slice (2026-09-22)                                                |
| BUG-005 | Email / contact form                        | Medium   | Fix                                 | Implemented — sixth slice (2026-09-22)                                                  |
| BUG-006 | Email / copy                                | Trivial  | Fix                                 | Implemented — ninth slice (2026-09-23), `docs/rewrite/ninth-slice.md` |
| BUG-007 | Domain / `visibility` constraint            | Low      | Fix                                 | Implemented — first vertical slice (2026-09-18)                                         |
| BUG-008 | Search / operations                         | Medium   | Fix                                 | Implemented — first vertical slice (2026-09-18); extended to requests and completed with `search:reindex` — fourth slice (2026-09-21) |
| BUG-009 | Background jobs / operations                | Medium   | Fix                                 | Implemented (search-sync path) — first vertical slice (2026-09-18); failed-job regression test incl. requests — fourth slice (2026-09-21) |
| BUG-010 | Email / contact form validation             | Medium   | Fix                                 | Implemented — sixth slice (2026-09-22)                                                  |
| BUG-011 | Newsletter / consent asymmetry              | Medium   | Fix                                 | Implemented — ninth slice (2026-09-23), `docs/rewrite/ninth-slice.md` |
| BUG-012 | Auth / Apple social login                   | Low      | Replace (drop)                      | Decided — do not implement                                                              |
| BUG-013 | Design / destructive-action confirmation    | Low      | Preserve                            | Decided — register B10 (native `confirm()`), reconciled 2026-09-21                      |
| BUG-014 | Design / Button "filled" variant            | Trivial  | Preserve (dead vocabulary)          | Decided 2026-09-21 — the never-used `filled` variant is not implemented; the two ad hoc filled looks stay as they are |
| BUG-015 | Navigation / `login` return destination     | Low      | Preserve                            | Decided — register B11 (always `/user/projects`), reconciled 2026-09-21                 |
| BUG-016 | Security / Meilisearch CORS configuration   | Low      | Replace (moot)                      | Resolved — pre-implementation review pass, 2026-09-18 (summary row reconciled 2026-09-23) |
| BUG-017 | Domain / `ProjectAnalytics.contactRequests` | Low      | Fix (do not reproduce)              | Resolved — pre-implementation review pass, 2026-09-18 (summary row reconciled 2026-09-23) |
| BUG-018 | Testing / historical search E2E coverage    | Medium   | Fix (close the gap)                 | Implemented — fourth slice (2026-09-21): `tests/E2E/specs/visitor/search.spec.ts`; the contact-from-result journey completed in the sixth slice (2026-09-22) |
| BUG-019 | Design / rich-text editor field-order bug   | Low      | Replace (moot)                      | Verified — every E2E journey fills title and goal before the description and submits (`ProjectWizardPage.fillStepOne`) |
| BUG-020 | Security / SSR Apollo client shared state   | Low      | Replace (moot)                      | Resolved — pre-implementation review pass, 2026-09-18                                   |
| BUG-021 | Authorization / Project edit screen access  | Low      | Fix                                 | Implemented — first-slice verification pass (2026-09-19)                                |
| BUG-022 | Domain / Project period validation          | Low      | Fix                                 | Implemented — second slice (2026-09-19)                                                 |
| BUG-023 | Domain / Project period display             | Low      | Fix                                 | Implemented — second slice (2026-09-19)                                                 |
| BUG-024 | Accessibility / rich-text list buttons      | Trivial  | Fix                                 | Implemented — second slice (2026-09-19)                                                 |
| BUG-025 | Design / copy typos                         | Trivial  | Fix                                 | Implemented — second slice (2026-09-19)                                                 |
| BUG-026 | Domain / whitespace-only title and goal     | Trivial  | Fix                                 | Implemented — second slice (2026-09-19); extended to request titles in the third slice   |
| BUG-027 | Domain / project creation is not atomic     | Low      | Fix                                 | Implemented — third slice (2026-09-21)                                                  |
| BUG-028 | Domain / request title length               | Trivial  | Preserve                            | Decided — third slice (2026-09-21): the field caps at 30, validation allows 40           |
| BUG-029 | Security / search hit rendering             | Medium   | Fix                                 | Implemented — fourth slice (2026-09-21); ratified by the maintainer as an intentional Fix (2026-09-22) |
| BUG-030 | Auth / no e-mail verification               | Low      | Fix (product change, decision A-3)  | Implemented — seventh slice (2026-09-22)                                                                |
| BUG-031 | Security / avatar upload server-side trust  | Medium   | Fix                                  | Implemented — eighth slice (2026-09-23)                                                                 |
| BUG-032 | Newsletter / duplicate subscribe            | Low      | Fix                                  | Implemented — ninth slice (2026-09-23), `docs/rewrite/ninth-slice.md` |
| BUG-033 | Newsletter / unsubscribe-by-email enumeration | Low    | Fix                                  | Implemented — ninth slice (2026-09-23), `docs/rewrite/ninth-slice.md` |
| BUG-034 | Newsletter / confirm link for a vanished lead | Trivial | Fix                                 | Implemented — ninth slice (2026-09-23), `docs/rewrite/ninth-slice.md` |
| BUG-035 | SEO / sitemap and robots host               | Low      | Fix                                 | Implemented — tenth slice (2026-09-23), `docs/rewrite/tenth-slice.md` |
| BUG-036 | SEO / `og:url` and Twitter placeholders     | Trivial  | Fix                                 | Implemented — tenth slice (2026-09-23), `docs/rewrite/tenth-slice.md` |
| BUG-037 | SEO / web manifest icon paths               | Trivial  | Fix                                 | Implemented — tenth slice (2026-09-23), `docs/rewrite/tenth-slice.md` |
| BUG-038 | SEO / sitemap lists `noindex` project pages | Trivial  | Preserve                            | Decided — finish-line parity audit (2026-09-23); detail page `noindex` restored |

---

### BUG-001 — `ProjectAnalytics` counters are world-writable

- Affected area: Authorization, `Project` view/contact counters
- Historical behavior: any caller, authenticated or not, can insert/update `views`/`contactRequests`
  for any `project_id` — no ownership check, no filter, bounded only by a `CHECK (0–1,000,000)`.
- Evidence: `docs/domain/permissions.md` (`ProjectAnalytics` table), `docs/domain/entities.md`.
- Severity/impact: High — any visitor can corrupt any project's (including private projects')
  engagement counters to an arbitrary value with a single GraphQL mutation.
- Classification: **Fix**
- Intended Nusszopf 2 behavior: counters are incremented exclusively by server-side controller
  logic, never exposed as a client-writable field.
- Implementation consequence: no `ProjectAnalyticsPolicy` "update" ability exists at all for end
  users — there is nothing to authorize because there is no user-facing write path.
- Regression test: a Feature test asserting an unauthenticated/unauthorized request cannot set an
  arbitrary counter value via any route.
- Full spec: `docs/rewrite/intentional-changes.md` → "`ProjectAnalytics` counters become
  server-controlled only".

### BUG-002 — `Request` visibility doesn't inherit from its parent `Project`

- Affected area: Authorization, `Request`
- Historical behavior: `requests.select_permissions.filter` is `{}` for both roles — a request
  under a private project is still selectable by anyone who can reach it.
- Evidence: `docs/domain/permissions.md`, `docs/domain/relationships.md`.
- Severity/impact: High — a private project's requests are not actually private.
- Classification: **Fix**
- Intended Nusszopf 2 behavior: a `Request` is only visible when its parent `Project` is visible
  to the caller (public, or owned by the caller).
- Implementation consequence: `RequestPolicy::view()` and a `Request::visible()` query scope that
  inherits `Project::visible()`, applied to every read path (not just `show`).
- Regression test: a Feature test asserting a request under a private project is never returned by
  any query/search result the request's owner didn't make.
- **Resolved context (pre-implementation review pass, 2026-09-18)**: the permission gap is real and
  must still be fixed at the API/Policy layer, but it was **never actually exploited by the
  historical product**. Two independent facts confirm this: (1) the search indexer itself
  (`search.function.js`) fetches the parent project via an admin-secret call and only indexes a
  request when the parent is public — so private-project requests were never discoverable via
  search regardless of the Hasura gap (see `docs/search/README.md`); (2) no frontend code path was
  found anywhere in `webapp/src/**` that queries `Request` independently of `projects_by_pk.requests`
  (a nested field, naturally scoped by the parent project query returning nothing for a private
  project a non-owner requests — see `docs/rewrite/open-questions.md`, the `/projects/{id}` SSR
  resolution). The fix remains required — a hand-crafted GraphQL query against Hasura directly could
  still have exploited the gap, and Nusszopf 2 must not reproduce an equivalent unscoped read path
  even if none was ever built historically — but this is defense-in-depth against a *possible*
  exposure, not closing a *demonstrated* historical leak.
- Full spec: `docs/rewrite/intentional-changes.md` → "`Request` visibility inherits from its parent
  `Project`".
- **Implemented (third slice, 2026-09-21)**: `ProjectRequestPolicy::view()` and
  `ProjectRequest::scopeVisible()` both delegate to `Project::scopeVisible()`; every read path
  (project detail, the edit screen's "Gesuche" list) goes through the scope, the request write paths
  resolve a request only *through its own project* and authorize against it, and requests are indexed
  only while their project is public. Tests: `ProjectRequestAuthorizationTest`,
  `ProjectRequestDetailTest`, `ProjectRequestEditTest`, `ProjectRequestSearchTest`.

### BUG-003 — Client-side-only authentication gate (flash-then-redirect)

- Affected area: Authentication, route protection
- Historical behavior: `withAuth` enforces `isAuthRequired` after the page shell has already
  mounted; an unauthenticated visitor briefly sees protected chrome before being redirected.
- Evidence: `docs/authentication/README.md` §3.
- Severity/impact: Medium — minor content leak (layout/chrome, not real data) and a real UX smell.
- Classification: **Fix**
- Full spec: `docs/rewrite/intentional-changes.md` → "Server-side authentication gate".

### BUG-004 — Social-login avatar sync unconditionally overwrites a manual upload

- Affected area: Authentication, `User.picture`
- Historical behavior: every social login overwrites `users.picture` from the provider's avatar,
  with no guard against an existing (including manually-uploaded) value.
- Evidence: `docs/domain/workflows.md` ("Workflow: profile picture replacement").
- Severity/impact: Low — annoying, not a security issue; destroys a user's own customization choice.
- Classification: **Fix**
- Full spec: `docs/rewrite/intentional-changes.md` → "Social-login avatar sync only fills an empty
  picture, never overwrites".

### BUG-005 — Contact-form email has no `Reply-To` header

- Affected area: Email, contact-form
- Historical behavior: the visitor's email is placed only in the body copy, not as `Reply-To`;
  replying goes to `noreply@nusszopf.org`.
- Evidence: `docs/email/README.md`, "Suspected historical issues" #2.
- Severity/impact: Medium — the contact form's entire purpose (letting the recipient reply) is
  undermined for anyone who hits "Reply" instead of copying the address out of the body.
- Classification: **Fix**
- Full spec: `docs/rewrite/intentional-changes.md` → "Contact-form email sets `Reply-To`".

### BUG-006 — Newsletter email grammar ("Bestätigte" → "Bestätige")

- Affected area: Email copy
- Historical behavior: both newsletter templates open with the wrong verb form.
- Evidence: `docs/email/README.md`, "Suspected historical issues" #1.
- Severity/impact: Trivial — cosmetic, native-speaker-visible grammar error only.
- Classification: **Fix**
- Full spec: `docs/rewrite/intentional-changes.md` → "Newsletter email copy typo fix".

### BUG-007 — `projects.visibility` has no CHECK constraint / enum

- Affected area: Domain, data integrity
- Historical behavior: `visibility` is unconstrained `text`; nothing prevents a third value.
- Evidence: `docs/domain/invariants.md`.
- Severity/impact: Low — zero observed real-world impact (only two values were ever used), pure
  hardening.
- Classification: **Fix**
- Full spec: `docs/rewrite/intentional-changes.md` → "`projects.visibility` becomes a real
  enum/CHECK constraint".

### BUG-008 — Meilisearch index configured by hand, once, via Postman

- Affected area: Search operations
- Historical behavior: at least one index's settings were applied via a one-off manual API call,
  documented in `be-nusszopf/docs/meilisearch/prod_setup.md`, not reproducible or code-reviewable.
- Evidence: `docs/search/README.md`.
- Severity/impact: Medium — operational risk (settings can't be reliably reproduced across
  environments), not a product-behavior defect.
- Classification: **Fix**
- Full spec: `docs/rewrite/intentional-changes.md` → "Meilisearch index settings become versioned
  application config".

### BUG-009 — Sync webhooks retry 3 times then silently give up

- Affected area: Background jobs / operations
- Historical behavior: every Hasura event-trigger webhook (search sync, mailing-list sync, cleanup)
  retries 3 times over ~30s, then gives up with no dead-letter queue or alerting.
- Evidence: `docs/domain/entities.md`, `docs/domain/workflows.md`, `docs/search/README.md`.
- Severity/impact: Medium — a permanently-lost sync is invisible (e.g. a project silently never
  gets indexed).
- Classification: **Fix**
- Full spec: `docs/rewrite/intentional-changes.md` → "Queue-backed sync jobs with retry/dead-letter".

### BUG-010 — Contact-form fields have no server-side validation or sanitization

- Affected area: Email, contact-form input handling
- Historical behavior: `contact.js` passes `req.body.email`, `title`, `request`, `msg` straight
  into the SendGrid dynamic-template payload with only rate-limiting in front of it — no format
  validation on `email`, no length limits, no confirmed HTML-escaping.
- Evidence: `docs/email/README.md`, "Suspected historical issues" #3.
- Severity/impact: Medium — malformed input reaches an email template unchecked; whether SendGrid's
  dynamic-template engine escapes HTML by default is itself Unknown from this evidence, so this
  should be treated as a real gap rather than assumed-safe.
- Classification: **Fix**
- Intended Nusszopf 2 behavior: server-side validation (required, `email:rfc`, historical length
  caps) before the Mailable is even built; Blade's default escaping handles output safety.
- Implementation consequence: since the historical `api/contact.js` route is replaced by a
  Livewire action on the project-detail screen (not a separate HTTP endpoint), the validation is
  Livewire's own `$this->validate()` — the idiomatic equivalent of a Form Request in that
  architecture — rather than a standalone Form Request class; a Feature test asserts invalid input
  is rejected with the historical copy and no mail sent.
- Regression test: submit malformed/oversized input and assert rejection + no mail sent
  (`Mail::fake()`).
- Implemented — sixth slice (2026-09-22). Full spec: `docs/rewrite/intentional-changes.md` →
  "Contact-form fields get server-side validation, and their output is escaped".

### BUG-011 — Newsletter opt-in has two different consent guarantees for the same intent

- Affected area: Domain / Newsletter (`Lead`)
- Historical behavior: opting into the newsletter via the **public home-page form** goes through
  true double opt-in (a `Lead` row is created unconfirmed, a confirmation email is sent, the lead
  only becomes `hasConfirmed = true` after the visitor clicks the emailed link). Opting in via the
  **"newsletter" checkbox at registration** creates the `Lead` **already confirmed**, with **no
  confirmation email sent at all** (`handleAuth0SyncHasura` calls `addLead(...)` immediately
  followed by `updateLead(...)`, which unconditionally sets `hasConfirmed: true`).
- Evidence: `web-nusszopf/projects/webapp/src/pages/api/newsletter.js`,
  `src/utils/functions/newsletter.function.js`, `src/utils/hasura/mutations/leads.mutation.js`
  (`UPDATE_LEAD` mutation body: `_set: { hasConfirmed: true }`, unconditional). Documented in
  `docs/domain/entities.md`'s `Lead` entity and `docs/rewrite/open-questions.md`.
- Severity/impact: Medium — if GDPR-style double opt-in was the actual compliance intent behind the
  `privacy` consent field existing at all, the signup-checkbox path bypasses verification of email
  ownership before marketing email starts (though arguably the registration flow itself already
  verifies the email is reachable, e.g. via the historical `welcome` email, and the user has already
  given explicit consent via the registration privacy checkbox — so this may be defensible, not a
  bug).
- **Update 2026-09-21**: reclassified **Fix** by the maintainer's GDPR requirement (see
  `decisions-register.md`). There are three historical paths, not two: the **profile page** also skips
  the confirmation email (`profile.js` `handleSubscribe` inserts the lead and sets `hasConfirmed` from the
  browser). The historical privacy policy itself states verification of the address is necessary.
- Original classification: **Unknown** — this is a genuine product-intent question, not resolvable from
  code alone: is single-path-different-guarantees the deliberate design (registration consent is
  "stronger" than a bare email address typed into a public form, so it doesn't need re-verification),
  or an oversight (the signup path should also double-opt-in, or the public-form path is needlessly
  stricter)? Do not silently pick an answer.
- Recommended default if no decision is made before implementation: preserve both paths exactly as
  historically observed (product-fidelity default), and record whichever answer is eventually given
  in `docs/rewrite/intentional-changes.md`.
- Full spec (2026-09-23): `docs/rewrite/intentional-changes.md` → "Double opt-in on every newsletter path (BUG-011)".

### BUG-012 — Apple social login button present but never wired up

- Affected area: Authentication, login screen
- Historical behavior: an Apple login button is rendered but `disabled`, with a
  `// todo: create auth0-apple connection` source comment — never functional.
- Evidence: `docs/authentication/README.md` §3.
- Severity/impact: Low — dead UI, not a security or data issue.
- Classification: **Replace (drop)** — this was never a real historical capability; do not
  reproduce a disabled Apple button, and do not build working Apple Sign In unless a fresh product
  decision explicitly asks for it (would be new functionality, not a revival of existing behavior).
- Decision: already effectively made in `docs/rewrite/intentional-changes.md`'s "Explicitly
  deferred" section — recorded here for completeness of the bug inventory.

### BUG-013 — Every destructive action uses the unstyled native `confirm()`, never the app's own `Dialog`

- Affected area: Design, confirmation states
- Historical behavior: delete account, delete project, delete request, unsubscribe, discard unsaved
  changes — every single one uses `window.confirm()`, despite a fully custom, animated, on-brand
  `Dialog` organism existing and being used for non-destructive flows.
- Evidence: `docs/design/states.md`.
- Severity/impact: Low — a visual/UX inconsistency, not a functional defect (the confirm still
  works; it just looks like the browser, not the product).
- Classification: ~~Unknown~~ → **Preserve**, decided (register B10; summary row). Original note: 100%-consistent patterns can be either "never got to it" or a
  deliberate choice (native dialogs can't be visually spoofed by injected page content the way a
  custom one theoretically could). Not recoverable from source; needs a human product decision.
- Recommended default if undecided: preserve native `confirm()` exactly (product-fidelity default,
  and it is testable via Playwright's own `page.on('dialog', ...)` the same way Cypress stubbed it).

### BUG-014 — Button `variant="filled"` is declared but (mostly) unstyled

- Affected area: Design, `Button` atom
- Historical behavior: `Button.theme.js` declares `clean`/`outline`/`filled` variants, but
  `Button.css` maps `outline` and `filled` to the identical CSS class — there is only one real
  bordered-pill visual treatment. At least one confirmed call site (`NewsletterForm.js`'s submit
  button, `className="mt-10 bg-blue-400 sm:mt-12"`) achieves a filled look via an ad hoc inline
  Tailwind utility class layered on top of the shared `Button` component, not via the component's
  own `variant` prop.
- Evidence: `docs/design/visual-language.md`, `docs/rewrite/open-questions.md`.
- Severity/impact: Trivial — a design-system naming/implementation inconsistency, not a functional
  defect.
- Classification: ~~Unknown~~ → **Preserve (dead vocabulary)**, decided 2026-09-21 (master-roadmap B-1; summary row). Original note: whether other call sites do the same ad hoc override, and whether
  a real "filled" design language was ever intended, needs a full call-site audit (see
  `docs/rewrite/open-questions.md`) before Nusszopf 2's Blade component library decides whether to
  implement a real filled variant or drop the unused name entirely.
- Recommended default if undecided: implement only the one visual treatment that's actually real
  (bordered pill), and do not add a "filled" component variant speculatively.

### BUG-015 — Login always lands on `/user/projects`, never the referring page

- Affected area: Authentication / navigation
- Historical behavior: every login (`/api/login`) hardcodes `returnTo: '/user/projects'` regardless
  of what triggered it.
- Evidence: `docs/design/navigation.md`, `docs/rewrite/open-questions.md`.
- Severity/impact: Low — a visitor who clicked "create project" while logged out loses that intent
  after logging in and has to navigate again.
- Classification: ~~Unknown~~ → **Preserve**, decided (register B11; summary row). Original note: could be deliberate simplicity (there is essentially one meaningful
  authenticated landing screen in this product) or an unaddressed gap.
- Recommended default if undecided: preserve as historically observed (product-fidelity default);
  this is observable behavior, not an obvious defect, per `CLAUDE.md`.

### BUG-016 — Meilisearch CORS proxy: wildcard origin + credentials

- Affected area: Security, search infrastructure
- Historical behavior: the nginx CORS proxy in front of Meilisearch answers every request (not just
  preflight) with `Access-Control-Allow-Origin: *` and `Access-Control-Allow-Credentials: true`,
  confirmed in both staging and production nginx configs, not just local dev.
- Evidence: `docs/security/README.md`.
- Severity/impact: Low (revised down from Medium — see resolution below).
- Classification: **Replace (moot)** — resolved, no longer Unknown.
- **Resolved (pre-implementation review pass, 2026-09-18)**: `search.service.js` confirms the
  browser queries Meilisearch directly with `MEILI_PK` (named as a public/search-only key, distinct
  from the `MEILI_API_KEY`/admin-style key used server-side by the indexer in
  `search.function.js`), so the risky CORS pattern was at least paired with what looks like a
  restricted key, not the master key, in the browser — partially resolving the "Unknown: which key"
  question from `docs/security/README.md`. More importantly, the confirmed indexing-time visibility
  gate (`docs/search/README.md`) means **no private content was ever present in the index for this
  CORS pattern to expose**, regardless of the key's exact scope — there was nothing sensitive to
  leak via this specific vector historically. Nusszopf 2's architecture removes the pattern
  entirely regardless (Meilisearch is only ever queried server-side via Scout, never from the
  browser), so this is fully moot for the rewrite, not just low-risk historically.

### BUG-017 — `ProjectAnalytics.contactRequests` has no real increment call site

- Affected area: Domain, `ProjectAnalytics`
- Historical behavior: the column exists, is covered by the same open-write permission as `views`,
  but no call site incrementing it exists anywhere in the frontend.
- Evidence: `docs/domain/entities.md`, `docs/rewrite/open-questions.md`.
- Severity/impact: Low — dead schema, not a functional gap.
- Classification: **Resolved / Fix (do not reproduce)**.
- **Resolved (pre-implementation review pass, 2026-09-18)**: `ContactDialog.js`'s `handleSubmit` was
  read in full — it posts only to `/api/contact` (the email-sending route) and contains no
  GraphQL mutation of any kind. Combined with the earlier confirmed read of `pages/projects/[id].js`
  (which only ever increments `views`), both plausible call sites are now exhausted:
  `contactRequests` is genuinely dead schema, never wired to any product behavior at any point in
  the evidence available.
- Intended Nusszopf 2 behavior: **do not implement a `contactRequests` counter or field.** Per
  `CLAUDE.md`'s "Never: invent domain behavior" — there is no historical trigger to revive, and
  building one (e.g. "increment on contact-dialog submit") would be inventing new product behavior,
  not reviving existing behavior, however plausible it seems from the column name alone. If a
  "contact requests" metric is wanted for Nusszopf 2, it needs its own fresh product decision, not
  a silent revival of an unused historical column.

### BUG-018 — Historical search E2E coverage was entirely stubbed out

- Affected area: Testing
- Historical behavior: `_search.spec.js` is wrapped in `xcontext` (never runs) with three
  literal `expect(true).to.equal(true)` stub tests — querying, filtering, and contacting a project
  owner from search results were never actually covered by the historical E2E suite.
- Evidence: `docs/journeys/README.md`, Journey 5.
- Severity/impact: Medium — not a product defect, but a real testing-debt flag: these three flows
  must be specified from implementation evidence (`docs/design/screens.md`) rather than E2E
  evidence, and the new Playwright suite must cover them from scratch.
- Classification: **Fix** (close the gap; "no historical E2E" is not "no requirement to test").

### BUG-019 — Slate rich-text editor field-interaction-order bug

- Affected area: Design, project creation form
- Historical behavior: a documented upstream `slate` bug (`ianstormtaylor/slate#3476`) required the
  description field to be interacted with before other fields, or the form silently failed to
  submit correctly — worked around with a specific field-interaction order in the historical E2E
  test, not fixed in the product itself.
- Evidence: `docs/design/screens.md`, `docs/rewrite/open-questions.md`.
- Severity/impact: Low — becomes moot once Nusszopf 2 picks a different rich-text approach (see
  `docs/rewrite/architecture-decisions.md`, "Rich-text editor replacement for Slate").
- Classification: **N/A** — not something to fix or preserve, since it's tied to a library being
  replaced entirely. The regression-test *symptom* (a form silently failing to submit under some
  field-interaction order) should be explicitly tested against for whatever editor replaces Slate,
  in case an analogous issue exists in the replacement.

### BUG-020 — SSR Apollo client's shared mutable `accessToken` module state

- Affected area: Security, `/projects/{id}` (and every other SSR page using `apolloClient.js`)
- Historical behavior: `webapp/src/utils/libs/apolloClient.js` declares `let accessToken` at
  **module scope** (not per-request). `requestAccessTokenServer(req, res)`, called from the
  `authLink`'s `setContext` callback on every SSR GraphQL request, `await`s the current request's
  Auth0 session and then assigns the result to this shared module variable before the
  `Authorization` header is read from it. Because the assignment and the read both happen across
  an `await` boundary on a variable shared by the whole Node.js process (not scoped to the request
  closure), two SSR requests handled by the same warm process/instance in close succession could,
  in principle, interleave: request A's `accessToken` assignment could be overwritten by request
  B's before request A's own header-construction step reads it back, causing **one visitor's
  session token to be used for another visitor's outbound Hasura query** in that request's window.
- Evidence: `webapp/src/utils/libs/apolloClient.js` (`accessToken`, `requestAccessTokenServer`,
  `authLink`), read in full during the pre-implementation review pass (2026-09-18).
- Severity/impact: Low/Unknown-in-practice — the code pattern is a genuine shared-mutable-state
  race condition (bad practice regardless of platform), but whether it was ever actually
  exploitable depends on Vercel's specific concurrency model for this deployment (whether a single
  warm Next.js server process ever handled two different users' `getServerSideProps` calls with
  interleaved async execution) — Unknown, unverifiable from this repository, and not something to
  guess at. No evidence exists that this was ever observed or reported as an incident.
- Classification: **Replace (moot)** — Nusszopf 2's architecture has no equivalent hazard: Laravel
  serves each HTTP request in its own process/request lifecycle (traditional PHP-FPM
  request-per-process model, not a shared long-lived event-loop process with module-level mutable
  state), so there is no shared variable for one request's authentication context to bleed into
  another's. Nothing needs to be "fixed" — the architecture change itself eliminates the class of
  bug. Recorded here only so a future contributor porting SSR-adjacent patterns doesn't
  reintroduce an analogous shared-mutable-state hazard (e.g. a static/singleton HTTP client
  carrying per-request auth state in a queue worker or long-running process).

### BUG-021 — Project edit screen's data-fetch is visibility-scoped, not ownership-scoped

- Affected area: Authorization, Project edit screen (`/user/project/[id]/edit`)
- Historical behavior: `pages/user/project/[id]/edit.js` fetches its data via `apollo.useGetProject(id)`
  — confirmed, by reading `apollo.service.js`, to be the exact same hook/query
  (`GET_PROJECT`) the **public** `pages/projects/[id].js` detail page uses. Hasura's
  `select_permissions` on `projects_by_pk` is therefore the same `visibility = public OR
  user_id = caller` rule for both screens, not an owner-only rule for edit specifically. The
  practical effect: a non-owner who navigates directly to a **public** project's edit URL gets
  a `200` with the project's (public) data rendered into the edit UI — the `useEffect`-driven
  `router.push('/404')` only fires when `projectData` comes back `null`, which happens only for a
  **private** project the caller doesn't own (the same condition the detail page's SSR 404 uses).
  Separately, the `update`/`insert` **mutation** permissions remain owner-only regardless, so a
  non-owner who reached this screen could never actually save a change — only view a read-only
  copy of a project they could already see on its public detail page.
- Evidence: `web-nusszopf/projects/webapp/src/pages/user/project/[id]/edit.js` (the `useEffect`
  redirect condition), `web-nusszopf/projects/webapp/src/utils/services/apollo.service.js:66`
  (`useGetProject` calls the same `GET_PROJECT` query for both screens) — both read in full during
  this verification pass, 2026-09-19.
- Severity/impact: Low. No private data is exposed (only already-public project fields), and no
  write capability is gained (mutations stay owner-gated) — this is a confusing/incomplete UX
  artifact of query reuse, not a data-confidentiality defect. `docs/design/screen-specs.md`'s
  existing "Project edit" row states "Owner only... any other caller... must not reach this
  screen's data," which is accurate for a *private* project but overstates the historical
  guarantee for a *public* one — corrected in this pass to record the nuance rather than leave an
  inaccurate "Confirmed" claim standing (`CLAUDE.md`: reconcile the document against the
  historical source when implementation surfaces a contradiction).
- Classification: **Fix** — reproducing the exact historical leniency (rendering a non-mutable
  edit-styled UI to a non-owner for a public project) has no product value, was never a considered
  feature (it is a side effect of the historical frontend reusing one query for two screens, not a
  deliberate capability), and the already-adopted target in `docs/security/authorization-matrix.md`
  and `docs/design/screen-specs.md` is owner-only access to this screen's data. Tightening Nusszopf
  2's edit screen to be strictly owner-only (as it was already built) is keeping the existing,
  already-approved specification, not inventing a new one.
- Intended Nusszopf 2 behavior: `ProjectPolicy::update()` denial on the edit screen must produce a
  hard `404`, not a `403` — matching `ProjectDetail`'s existing no-existence-leak treatment
  (`docs/rewrite/open-questions.md`, "Does `/projects/{id}`'s SSR enforce `visibility`, or only
  existence?") and the historical redirect target itself (`router.push('/404')`), rather than a
  `403` that would distinguish "exists but isn't yours" from "doesn't exist" through a different
  status code than every other unauthorized-access path in the app uses.
- Regression test: `tests/Feature/Projects/ProjectFormTest.php`, "denies a non-owner from editing
  another users project," updated to assert `404`, not `403`.
- Full spec: `docs/rewrite/intentional-changes.md` → "Project edit screen denies non-owners with a
  404, not a 403".

---

## Second-slice findings (Project wizard / edit)

Found while implementing the historical creation wizard and edit screen (2026-09-19), each read
directly from `web-nusszopf/projects/webapp/src/containers/user/ProjectForm/*` and
`ui-library/stories/**`. Fixes are specified in `docs/rewrite/intentional-changes.md`.

### BUG-022 — The period's "end before start" test still fires while the period is flexible

- Affected area: Domain, `PeriodField.js` validation (create wizard step 1, edit "Beschreibung")
- Historical behavior: `PeriodFieldValidationSchema` gates the `required` and `dd.MM.yyyy` tests on
  `flexible === false`, but the `period_to_isDesc` ("Enddatum vor Startdatum") test is attached to
  `to` through a separate `.when(['from'], …)` that ignores `flexible`. If a visitor types a start
  and an earlier end date and *then* switches to "Flexibel", the inputs are disabled (and their
  labels dimmed) but the ordering test still fails, so `Weiter`/`Speichern` is blocked by an error
  attached to a control the visitor can no longer edit, without changing the radio back.
- Evidence: `PeriodField.js:14-34` (Confirmed, read in full).
- Severity/impact: Low — a dead end reachable only through a specific sequence, with a way out.
- Classification: **Fix** — a flexible period has no dates by definition (`serializeProjectDescription`
  discards them), so validating them is defective, not a feature.

### BUG-023 — A stored period date is displayed in the *viewer's* time zone

- Affected area: Domain, project detail and edit screens
- Historical behavior: the form's `dd.MM.yyyy` strings are persisted as `formatISO(date)` — an
  ISO-8601 date-time at *local midnight with the author's UTC offset* — and the detail page
  renders them with `new Date(iso).toLocaleDateString('de-DE')`, i.e. in the *viewer's* zone. A
  project authored in Germany (`2027-03-01T00:00:00+01:00`) is shown as 28.2.2027 to a viewer west
  of UTC.
- Evidence: `utils/helper.js` (`parseDateISOString`), `projects.service.js:serializeProjectDescription`,
  `pages/projects/[id].js:period`.
- Severity/impact: Low — off-by-one-day display for some viewers.
- Classification: **Fix** — the intended value is the calendar date the author typed. The stored
  representation is kept exactly (ISO-8601 date-time, not `dd.MM.yyyy` — see the correction to
  `docs/domain/entities.md`); only the *display* now reads the stored calendar date instead of
  converting it.

### BUG-024 — The rich-text list buttons carry each other's accessible names

- Affected area: Accessibility, `RichTextEditor` toolbar
- Historical behavior: `rich-text-editor.data.js` defines `ordered: 'Liste ungeordnet'` and
  `unordered: 'Liste geordnet'` — swapped — and `RichTextEditor.organism.js` uses `cms.aria.unordered`
  for the bullet-list button and `cms.aria.ordered` for the numbered-list button. A screen reader
  announces the bullet list as "Liste geordnet" (ordered list) and vice versa.
- Evidence: `ui-library/assets/data/rich-text-editor.data.js`, `RichTextEditor.organism.js:57-73`.
- Severity/impact: Trivial (accessibility only).
- Classification: **Fix** — the labels are unambiguously wrong for the icons they name. This is
  also the "verify the new editor doesn't reintroduce an analogous bug" check BUG-019 asked for.

### BUG-025 — Two typos in user-facing copy

- Affected area: Design/copy
- Historical behavior: the visibility field's info text reads "…nur für bestimmte **Peronen** sichtbar…"
  (`project-form.data.js`), and the edit screen's save-error toast reads "…konnten nicht
  **gepeichert** werden." (`edit-projects-views.data.js`).
- Severity/impact: Trivial.
- Classification: **Fix** — precedent: BUG-006 (a grammar fix in an email). Corrected to "Personen" and
  "gespeichert". Every other string is reproduced verbatim (including the double spaces in the
  contact-field info text, which HTML collapses anyway).

### BUG-026 — A whitespace-only title or goal passes the historical validation

- Affected area: Domain, `TitleField.js` / `GoalField.js`
- Historical behavior: `string().max(40).required()` (Yup) treats `"   "` as a value, so a project
  can be created whose title (or goal) is only spaces — it then renders as an empty heading. Laravel's
  `required` rule rejects whitespace-only strings, so the new implementation would have differed
  from history in this corner either way.
- Severity/impact: Trivial.
- Classification: **Fix** — the intended rule is "Gib einen Titel ein"/"Gib ein Ziel ein"; a heading
  of spaces is not a title. Recorded rather than silently adopted as Laravel's default.
- Extended in the third slice to the **request title** (`RequestForm/TitleField.js` has the same
  `string().max(40).required()` schema): a whitespace-only request title fails with "Gib einen Titel ein".

### BUG-027 — Creating a project with requests is not atomic

- Affected area: Domain, project creation (`projects.service.js`, `addProject`)
- Historical behavior: the wizard inserts the project, *then* inserts its requests in a second call.
  If the second call fails, the user is told "Sorry, das Projekt konnte nicht erstellt werden." while
  the project exists (without its requests); creating again then produces a duplicate.
- Evidence: `containers/../utils/services/projects.service.js` `addProject` (`apolloAddProject`, then
  `apolloAddRequests` inside the same `try`).
- Severity/impact: Low — needs a failing second write, but the message and the state disagree.
- Classification: **Fix** — a project and the requests created with it are one unit: either the
  project exists with all its requests, or nothing was created and the error toast is true.
- Full spec: `docs/rewrite/intentional-changes.md` → "A project and its requests are created together (BUG-027)".

### BUG-028 — The request title field caps at 30 characters, the schema at 40

- Affected area: Domain, `RequestForm/TitleField.js`
- Historical behavior: the input has `maxLength={30}` while the Yup schema is `max(40, 'Maximal 40
  Zeichen')`. Through the UI a title can therefore never exceed 30 characters and the "Maximal 40
  Zeichen" message can never appear; the two limits disagree.
- Evidence: `TitleField.js` (read in full). Nothing else states an intended limit.
- Severity/impact: Trivial — no user can observe the disagreement.
- Classification: **Preserve** — both numbers are kept exactly where history put them: the input's
  `maxlength` is 30 and the server-side rule (which is what a hand-crafted request meets) is 40 with
  the historical copy; the column is 40 characters. Choosing one number would be inventing a rule.
  Recorded so a later product decision can align them deliberately.
- Regression test: `ProjectRequestWizardTest` ("validates a title of more than 40 characters"),
  `tests/E2E/specs/user/project-requests.spec.ts` (the 30-character cap).

### BUG-029 — Search hits are injected into the page as raw HTML

- Affected area: Security, search results (`HitCard`, `HitRequestCard`)
- Historical behavior: Meilisearch highlights matches by wrapping them in `<em>` inside the *unescaped* stored
  text, and the hit cards rendered that string with `dangerouslySetInnerHTML` (`HitCard.js`: title, goal and
  the summary line; `HitRequestCard.js`: title and description). A project or request whose own text
  contains markup therefore ran it in every visitor's browser on every search that surfaced it — stored XSS
  through the one screen anyone can open.
- Evidence: `web-nusszopf/.../containers/search/HitCard/HitCard.js`, `components/RequestCard/variants/HitRequestCard.js`;
  `MEILI_CONFIG.attributesToHighlight` in `search.service.js` (no custom tags, no escaping).
- Severity/impact: Medium — needs an author account, reaches anonymous visitors.
- Classification: **Fix**
- Corrected behavior: the text is escaped; only the engine's highlight is emphasis (`<em>`, the tag the
  historical index used), asked for through private-use marker characters instead of markup.
- Full spec: `docs/rewrite/intentional-changes.md` → "Search hits are escaped, only the highlight is markup (BUG-029)".

### BUG-030 — No e-mail verification exists

- Affected area: Authentication, `User.email`
- Historical behavior: no `email_verified` concept anywhere — no template, no Auth0 rule, no gate.
  Registering and logging in are never blocked by it (Confirmed, `docs/authentication/README.md` §2).
- Evidence: `docs/authentication/README.md` §2, §8; `docs/rewrite/decisions-register.md` (A-3).
- Severity/impact: Low — not a security defect on its own (the historical product never claimed to
  verify addresses), but the maintainer's decision treats an unverified address as too weak a basis
  for two specific, higher-trust actions: publishing it as a project's public contact, and (from
  slice 9) subscribing it to the newsletter.
- Classification: **Fix (deliberate product change, decision A-3)** — not a defect in historical
  behavior; the historical absence itself is preserved as the default (login/registration stay
  ungated). What changes is that Nusszopf 2 now tracks `email_verified_at` and gates exactly those two
  actions on it.
- Intended Nusszopf 2 behavior: registration and every new Google-created account send a verification
  e-mail (`App\Mail\VerifyEmailMail`, no historical template — new copy); the "Persönlich" project
  contact option requires `hasVerifiedEmail()`; Google login links/creates an account only when Google
  itself asserts the address is verified, and a Google login always marks the local account verified.
- Regression test: `tests/Feature/Auth/EmailVerificationTest.php`, `tests/Feature/Projects/ProjectEditTest.php`
  and `ProjectWizardTest.php` ("Persönlich" gating), `tests/Feature/Auth/GoogleLoginTest.php`.
- Full spec: `docs/rewrite/intentional-changes.md` → "E-mail verification, gating only the personal
  contact and the future newsletter subscription (BUG-030)".

---

### BUG-031 — Avatar upload trusts the client's crop/compress step entirely

- Affected area: Profile, avatar upload (`pages/api/upload.js`, `AvatarDialog.js`)
- Historical behavior: the upload endpoint issues an S3 presigned POST constrained only by
  `content-length-range: [0, 1048576]` (≤1 MB) and a fixed key/ACL — nothing server-side ever
  decodes, re-encodes, or re-crops the uploaded bytes. The 150×150 round crop and JPEG compression
  are entirely client-side (`react-easy-crop` + `compressorjs`); a request built by hand rather than
  through the real dialog could upload any file under 1 MB with a `.jpeg`-shaped key, whatever its
  actual content or dimensions.
- Evidence: `pages/api/upload.js`, `containers/user/AvatarDialog/AvatarDialog.js`, `stories/organisms/
  Cropper/utils/index.js` — all read in full for slice 8.
- Severity/impact: Medium — not a known historical incident, but a real, demonstrable gap: nothing
  server-side confirms the stored file is actually a decodable image, or that it is square/bounded, and
  a project rendering it via a plain `<img>` tag has no independent size/content guarantee.
- Classification: **Fix**
- Intended Nusszopf 2 behavior: the server still accepts whatever the client crops and uploads, but
  `App\Support\AvatarUploader` decodes every upload with GD, rejects anything that doesn't decode as a
  raster image, center-crops it to a square, caps it at 512×512, and re-encodes it as a fresh JPEG
  before it is ever stored or served — independent of what the client claimed.
- Implementation consequence: Livewire's built-in temporary-upload mechanism replaces the historical
  two-step signed-POST dance (register B8 — local disk, not S3); the 1 MB cap becomes a Livewire
  validation rule (`image`, `max:5120` pre-re-encode, generous because the server re-encodes
  regardless of input size) rather than an S3 bucket-policy condition.
- Regression test: `tests/Feature/Profile/AvatarUploadTest.php` — a non-image file with an `image/jpeg`
  content-type is rejected; an oversized/non-square source image is still stored as a bounded square.
- Full spec: `docs/rewrite/intentional-changes.md` → "Avatar uploads are re-validated and re-encoded
  server-side (BUG-031)".

### BUG-032 — Subscribing an address that already has a lead fails with HTTP 500

- Affected area: Newsletter, `handleSubscribe` (`webapp/src/utils/functions/newsletter.function.js`)
- Historical behavior: `if (existingLead) res.status(500).end('lead with email X could not be created')`
  — whether the existing lead is still pending or already confirmed. The form shows the generic error
  toast ("Sorry, es ist ein Fehler aufgetreten…"), and a visitor whose first confirmation mail was lost
  can never get a new one: the only way out is to unsubscribe first.
- Evidence: `newsletter.function.js` `handleSubscribe`, `newsletter.service.js` `handleRequest` (any
  non-2xx → error toast). Read in full for slice 9.
- Severity/impact: Low — a server error for a normal user action, a dead end for a lost mail, and a
  response that tells anyone whether an address is on the list (enumeration).
- Classification: **Fix** (register B-9; decision A-1 "a duplicate subscribe resends/answers neutrally").
- Intended Nusszopf 2 behavior: the answer is identical whether or not the address is known. A pending
  lead gets its consent record refreshed and a fresh confirmation mail; a confirmed lead is left
  untouched and no mail is sent.
- Regression test: `tests/Feature/Newsletter/SubscribeTest.php`.

### BUG-033 — Unsubscribe by e-mail reveals whether an address is subscribed

- Affected area: Newsletter, `handleUnsubscribe`, `/newsletter/unsubscribe/lead`
- Historical behavior: an unknown address answers HTTP 404 (error toast), a known one 200 (success
  toast "E-Mail verschickt! Bitte bestätige deine Abmeldung."). Anyone can test whether an address is
  on the list.
- Evidence: `newsletter.function.js` `handleUnsubscribe`; `pages/newsletter/unsubscribe/lead.js`.
- Severity/impact: Low — a list-membership oracle on a public, unauthenticated form.
- Classification: **Fix** (decision A-1: "unsubscribe-by-email answers identically whether or not a lead exists").
- Intended Nusszopf 2 behavior: the success toast for every valid address; the unsubscribe mail is sent
  only when a lead exists.
- Regression test: `tests/Feature/Newsletter/UnsubscribeTest.php`.

### BUG-034 — A valid subscribe link for a lead that no longer exists renders an empty confirmation

- Affected area: Newsletter, `handleSubscribeConfirm`, `/newsletter/subscribe/[token]`
- Historical behavior: the JWT is verified, then `updateLead(leadId)` updates nothing (the lead was
  unsubscribed in between) and returns `null`; the handler still answers 200 with `{ email: undefined }`
  and the page renders "… wurde zum Newsletter angemeldet." with no address and no lead confirmed.
- Evidence: `newsletter.function.js` `handleSubscribeConfirm`, `api.function.js` `updateLead`,
  `pages/newsletter/subscribe/[token].js`.
- Severity/impact: Trivial — a success message that is false.
- Classification: **Fix** — it is the same case as an unknown token: nothing was confirmed.
- Intended Nusszopf 2 behavior: 404, exactly like an invalid or expired token. (The unsubscribe link
  for a lead that is already gone keeps answering with the success page, as historically — the result
  the visitor asked for is true.)
- Regression test: `tests/Feature/Newsletter/ConfirmationPagesTest.php`.

### BUG-035 — Sitemap and `robots.txt` hard-code `https://nusszopf.org`

- Affected area: SEO, `pages/api/sitemap.js`, `public/robots.txt`
- Historical behavior: `SitemapStream({ hostname: 'https://nusszopf.org' })` and
  `Sitemap: https://nusszopf.org/sitemap.xml` are literals, not derived from `DOMAIN`.
- Evidence: `pages/api/sitemap.js`, `public/robots.txt` (while `Page.js` builds the canonical URL from
  `process.env.DOMAIN`, so the historical code already knew the host was configuration).
- Severity/impact: Low — every other deployment (the historical dev/staging hosts, and every self-hosted
  instance) advertises another site's URLs to search engines.
- Classification: **Fix**
- Intended Nusszopf 2 behavior: both use `APP_URL`, as the canonical URL does.
- Regression test: `tests/Feature/Seo/SitemapTest.php`.

### BUG-036 — `og:url` is a bare path; Twitter handles are next-seo placeholders

- Affected area: SEO, `components/Page/Page.js`
- Historical behavior: `openGraph.url` is `router.asPath` (e.g. `/projects/…`), while the Open Graph
  protocol requires an absolute URL; `twitter.handle`/`site` are `@handle`/`@site`, the example values
  from next-seo's documentation, rendered into every page.
- Evidence: `components/Page/Page.js`.
- Severity/impact: Trivial — link previews resolve the wrong URL or none; two meaningless meta tags.
- Classification: **Fix**
- Intended Nusszopf 2 behavior: `og:url` equals the canonical URL; the two placeholder tags are omitted
  (the `twitter:card` type stays).
- Regression test: `tests/Feature/Seo/SeoTagsTest.php`.

### BUG-037 — The web manifest and `browserconfig.xml` point at icons that do not exist

- Affected area: SEO/PWA metadata, `public/favicons/site.webmanifest`, `public/favicons/browserconfig.xml`
- Historical behavior: both files reference `/android-chrome-192x192.png`, `/android-chrome-512x512.png`
  and `/mstile-150x150.png` at the site root, while the files are served from `/favicons/`.
- Evidence: `webapp/public/favicons/*` (no copies at the root of `public/`).
- Severity/impact: Trivial — "add to home screen" and Windows tiles get 404s instead of the icons.
- Classification: **Fix**
- Intended Nusszopf 2 behavior: the same files with `/favicons/…` paths.
- Regression test: `tests/Feature/Seo/SeoTagsTest.php`.

### BUG-038 — The sitemap lists project pages that ask not to be indexed

- Affected area: SEO
- Historical behavior: `pages/projects/[id].js` renders `<Page noindex={true}>`, so every project page carries
  `noindex`, yet `/api/sitemap` lists every public project for search engines.
- Evidence: `webapp/src/pages/projects/[id].js:137-143`, `webapp/src/pages/api/sitemap.js`
  (`docs/rewrite/tenth-slice.md`).
- Severity/impact: Trivial — search engines honour `noindex`, so projects were effectively never indexed; the
  sitemap entries only cost crawl requests.
- Classification: **Preserve** — the two signals contradict each other, but which one was intended (projects
  findable on the web, or kept out of it) is a product and privacy question the code does not answer. Keeping
  both reproduces the observable result (projects not indexed) and indexes nothing new.
- Found: the finish-line visual parity audit (P-2) re-read every `<Page>` call; slice 10 had missed the
  detail page's `noindex`, which is restored.
- Regression test: `tests/Feature/Seo/SeoTagsTest.php` ("marks a project page noindex, as historically"),
  `SitemapTest` (projects still listed).

