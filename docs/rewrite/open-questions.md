# Open Questions

Unresolved historical behavior, consolidated from the archaeology pass across `docs/domain/`, `docs/design/`, `docs/authentication/`, `docs/email/`, `docs/search/`. None of these are resolved by inventing an answer — each needs either more evidence (a follow-up archaeology pass over a specific file/dataset named below) or an explicit product decision once evidence is exhausted. Do not silently resolve any of these in application code.

### Lead (newsletter) creation route

- Status: **Resolved**
- Area: Domain / Newsletter
- Sources inspected: `web-nusszopf/projects/webapp/src/pages/api/newsletter.js`, `src/utils/functions/newsletter.function.js`, `src/utils/services/newsletter.service.js`, `src/utils/hasura/queries/newsletter.query.js`, `src/assets/data/newsletter.data.js`.
- Resolution: a single privileged endpoint, `POST /api/newsletter`, action-dispatches to both paths as suspected. Full mechanism now documented in `docs/domain/entities.md` (`Lead` entity) and `docs/domain/workflows.md` ("Workflow: newsletter lead creation, confirmation..."). Headline finding: the two creation paths (public form vs. signup checkbox) have **different confirmation guarantees** — the signup-checkbox path auto-confirms with no verification email, unlike the public form's true double opt-in. Flagged as a candidate historical-bugs entry, not yet a formal intentional-change proposal.
- Date resolved: 2026-09-18

### `Project.location` / `Project.period` exact JSON shape

- Status: **Resolved** (fully — including `location.data`'s complete shape)
- Area: Domain / Project
- Sources inspected: `webapp/src/containers/user/ProjectForm/LocationField.js`, `PeriodField.js`, `webapp/src/utils/services/location.service.js`, `pages/projects/[id].js`.
- Resolution: `location = { remote: boolean, searchTerm: string, data: object }`; `period = { flexible: boolean, from: string, to: string }` with `from`/`to` in **`dd.MM.yyyy`** string format (Confirmed by Yup validation), not ISO 8601. `location.data`'s complete shape is now Confirmed: `{ key, postcode, city, countryCode, geo: {lat,lon}, osm: {id,type} }`, sourced from the **LocationIQ** autocomplete API (`api.locationiq.com`, German cities/towns/villages only) — not OpenStreetMap Nominatim directly, correcting the earlier guess. Full detail in `docs/domain/entities.md`'s `Project` entity.
- Date resolved: 2026-09-18

### `Request.category` value set

- Status: **Resolved**
- Area: Domain / Request
- Sources inspected: `webapp/src/assets/data/request-form.data.js`.
- Resolution: German labels confirmed: `companions` → "Mitstreiter:innen", `rooms` → "Räume", `materials` → "Materialien", `financials` → "Finanzielles", `others` → "Sonstiges"; the select's empty state is a literal `-` placeholder, not a labeled `none` option. Recorded in `docs/domain/entities.md`'s `Request` entity.
- Date resolved: 2026-09-18

### `ProjectAnalytics` row auto-creation mechanism

- Status: **Resolved**
- Area: Domain / Project
- Sources inspected: `webapp/src/pages/projects/[id].js` (`updateViews` effect), re-verified directly.
- Resolution: **Confirmed.** The first non-owner browser to view a project's detail page (deduplicated via a `localStorage` array, not per-account) directly issues a client-side GraphQL mutation: `INSERT {project_id, views: 1}` if no analytics row exists yet, else `UPDATE views = views + 1`. No server-side/trigger-based creation exists. `docs/domain/relationships.md` and `docs/domain/entities.md` have been reconciled to state this as Confirmed. **New finding surfaced during this resolution**: `contactRequests` has no confirmed increment call site anywhere read so far — it may be effectively dead/unused in the frontend despite existing in the schema; flagged as a fresh, narrower open question (not yet its own entry — track if a future pass needs to confirm or refute this before the rewrite decides whether to keep a `contactRequests`-equivalent field at all).
- Date resolved: 2026-09-18

### Newsletter signup-checkbox path skips double opt-in

- Status: Unknown (product-intent question, not a research gap)
- Area: Domain / Newsletter / Authentication
- Sources inspected: `web-nusszopf/projects/webapp/src/pages/api/newsletter.js`, `src/utils/functions/newsletter.function.js` (see the resolved "Lead (newsletter) creation route" entry above for full context).
- Historical evidence: a `Lead` created via the public newsletter-signup form goes through true double opt-in (unconfirmed → confirmation email → click-through confirms). A `Lead` created via the "newsletter" checkbox at account signup is created **already confirmed**, with no confirmation email sent at all.
- Conflicting evidence: none — the asymmetry is consistent and deliberate-looking (different code paths, not a shared function with a skipped step).
- Possible interpretations: (a) intentional — the user already verified their email by completing Auth0 registration, so a second confirmation is redundant; (b) an inconsistency/oversight where the signup path was never brought in line with the public form's opt-in guarantee (relevant since double opt-in is often a legal/consent requirement, not just a UX nicety, given `leads.privacy`'s GDPR-consent-flavored column).
- Recommended investigation: none further possible from source; this is a product/compliance-intent question.
- Decision: pending human decision — do not silently pick (a) or (b). If Nusszopf 2 relies on double opt-in for consent-compliance reasons, interpretation (b) (fix: always require confirmation) is the safer default; record whichever is chosen in `docs/rewrite/intentional-changes.md` or here as Resolved.
- Date: 2026-09-18

### `ProjectAnalytics.contactRequests` — possibly dead/unused historically

- Status: Unknown
- Area: Domain / Project
- Sources inspected: `webapp/src/pages/projects/[id].js` (the file expected to contain a contact-counter increment call, alongside the confirmed `views` increment).
- Historical evidence: no increment call site for `contactRequests` was found in any file read across either research pass, despite the column existing in the schema with the same permission shape as `views` (see BUG-001).
- Conflicting evidence: the column's existence and its identical bounds/permissions to `views` strongly suggest it was intended to be used the same way.
- Possible interpretations: (a) a call site exists elsewhere in `webapp/src/**` not yet read (most likely — e.g. inside `ContactDialog`'s submit handler); (b) the feature was built at the schema level and never wired up on the frontend.
- Recommended investigation: grep `contactRequests`/`contact_requests`/`addProjectAnalytics` across all of `webapp/src/**` before the rewrite decides whether to keep an equivalent field.
- Decision: pending investigation. Do not silently drop the field from Nusszopf 2's `ProjectAnalytics` model without confirming (a)/(b) — the historical *intent* (count contact actions) is clear from the column name and the contact dialog's existence, even if the wiring is unconfirmed.
- Date: 2026-09-18

### Does `/projects/{id}`'s SSR enforce `visibility`, or only existence?

- Status: Unknown
- Area: Security / Domain / Project
- Sources inspected: `docs/domain/permissions.md` (Hasura `select_permissions` on `projects`, which deny a private project to any non-owner), `docs/design/screens.md` (confirms `getServerSideProps` returns `notFound: true` only when "the project id does not resolve", not specifically checked against a `visibility`-aware query in this pass).
- Historical evidence: the Hasura permission layer is unambiguous — a private project is not selectable by anyone but its owner. Whether the `webapp`'s own server-side data-fetching code for `/projects/{id}` actually goes through that same permission-checked path (using the viewer's own role/token) or instead uses an elevated/admin credential server-side (which would bypass the visibility filter and only fail on true non-existence) was not confirmed in either archaeology pass.
- Conflicting evidence: none directly, but an unverified note surfaced during the second pass speculated that "private" might mean "unlisted but reachable via direct link" — this would contradict the Hasura evidence above if true, so it must not be assumed either way.
- Possible interpretations: (a) SSR uses the viewer's own token/role, so the Hasura filter applies normally and a private project 404s for non-owners (expected, consistent with all other evidence); (b) SSR uses an elevated credential and only checks existence, meaning private projects are actually viewable by anyone with the direct URL — a real, additional authorization gap beyond BUG-002, not yet recorded as such.
- Recommended investigation: read `webapp/src/pages/projects/[id].js`'s `getServerSideProps` in full, specifically which GraphQL client/credential it uses for the initial fetch.
- Decision: pending investigation. Do not implement Nusszopf 2's project-detail route without this being resolved — default to the safe interpretation (a) (enforce visibility, not just existence) unless investigation proves otherwise.
- Date: 2026-09-18

### `NavHeader mode="external"` and `Footer variant="auth0"` actual usage sites

- Status: **Resolved**
- Area: Design / Navigation
- Sources inspected: `auth-login/src/containers/Page/Page.js`, `auth-password/src/containers/Page/Page.js`.
- Resolution: interpretation (a) was correct — both auth apps' shared `Page.js` renders `<NavHeader mode="external" />` and `<Footer variant="auth0" .../>` unconditionally on every screen. Not dead code. Recorded in `docs/design/navigation.md`.
- Date resolved: 2026-09-18

### Auth0 password policy authoritative configuration

- Status: Unknown
- Area: Authentication
- Sources inspected: `auth-login/src/containers/SignUpForm/SignUpForm.js` (client-side mirror of the policy, 5 composed rules)
- Historical evidence: a source comment ties the rule directly to "Auth0 Dashboard/Authentication/Database/PasswordPolicy" — i.e. the authoritative config lived in the Auth0 tenant dashboard, which is not present in any repository.
- Conflicting evidence: none.
- Possible interpretations: the 5 client-mirrored rules are the complete policy (most likely, since Auth0's dashboard UI for a custom password policy typically maps 1:1 to exactly these kinds of rules), or the dashboard had additional server-enforced rules never mirrored client-side.
- Recommended investigation: none possible from repository evidence alone; treat the 5 client-mirrored rules as the best available approximation.
- Decision: adopt the 5-rule policy as the Nusszopf 2 default, explicitly flagged as "best available approximation, not verified against the authoritative source" — record as an architecture decision once approved.
- Date: 2026-09-18

### IP-block thresholds and breached-password-check scope

- Status: Unknown
- Area: Authentication / Security
- Sources inspected: `emails-nusszopf/src/auth0/{blocked-account,password-breach-alert}.mjml`, `be-nusszopf/docs/auth0`
- Historical evidence: both are Auth0 platform features (Attack Protection, breached-password detection); their thresholds/behavior live entirely in Auth0 tenant configuration, not in any repository.
- Conflicting evidence: none.
- Possible interpretations: cannot be recovered from available evidence.
- Recommended investigation: none possible; this must be a fresh decision (see `docs/rewrite/architecture-decisions.md`), not a port.
- Decision: pending architecture decision.
- Date: 2026-09-18

### 8-hour rolling session duration — deliberate or default?

- Status: Unknown
- Area: Authentication
- Sources inspected: `webapp/src/utils/libs/auth0.js` (`rollingDuration` literal, 8 hours)
- Historical evidence: the value is a literal in application config; no comment or documentation explains why 8 hours specifically.
- Conflicting evidence: none.
- Possible interpretations: a deliberate product choice (e.g. "log back in daily"), or simply the `@auth0/nextjs-auth0` library's suggested/example default that was never revisited.
- Recommended investigation: check `be-nusszopf/docs/auth0` for any session-duration rationale; otherwise unresolvable.
- Decision: preserve 8 hours as the Nusszopf 2 default pending a specific reason to change it — record as an architecture decision.
- Date: 2026-09-18

### `be-nusszopf` apparent staleness relative to `web-nusszopf`

- Status: Unknown
- Area: General / Evidence quality
- Sources inspected: `git log -1` on both repositories (see `docs/rewrite/source-map.md`)
- Historical evidence: `be-nusszopf`'s last commit is dated 2021-05-20; `web-nusszopf`'s last commit is dated 2023-08-30 — a 2.3-year gap.
- Conflicting evidence: none directly, but this is a notable asymmetry worth flagging rather than assuming away.
- Possible interpretations: (a) the backend genuinely froze in 2021 and the frontend evolved against an unchanging contract for over two years (plausible for a small side project); (b) this checkout of `be-nusszopf` is not actually the final historical state and a later revision exists elsewhere.
- Recommended investigation: if any specific domain/permission finding in `docs/domain/` ever contradicts frontend behavior observed in a later `web-nusszopf` commit, treat that as evidence of (b) and re-open this question; otherwise treat (a) as the working assumption.
- Decision: proceed on assumption (a) unless contradicted.
- Date: 2026-09-18

### SendGrid dashboard template drift risk

- Status: Unknown
- Area: Email
- Sources inspected: `emails-nusszopf/src/sendgrid/newsletter/{subscribe,unsubscribe}.mjml`
- Historical evidence: both templates pass a `username` template variable that the static MJML source never renders anywhere.
- Conflicting evidence: the variable's presence implies it's used *somewhere*.
- Possible interpretations: the actual live templates were edited directly in SendGrid's dashboard (dynamic templates) and drifted from what's committed here; or `username` is simply unused dead data passed defensively.
- Recommended investigation: none possible without SendGrid dashboard access (not available). Treat the committed `.mjml` source as the best-available but **not necessarily final** copy source for these two templates specifically.
- Decision: use committed copy as the baseline; flag to a human reviewer that dashboard-side drift is possible for these two templates only.
- Date: 2026-09-18

### Button `ButtonVariant` (clean/outline/filled) — was "filled" ever implemented?

- Status: **Resolved**
- Area: Design / Visual language
- Sources inspected: exhaustive grep of every `<Button variant="...">` call site across `webapp/src/**`, `ui-library/stories/**`, `auth-login/src/**`, `auth-password/src/**`.
- Resolution: interpretation (b) was correct, refined — `variant="filled"` is never called anywhere (dead vocabulary); only `outline` and `clean` are real. A genuinely filled look exists at exactly two call sites via ad hoc inline Tailwind background classes layered on top of the shared component (`NewsletterForm.js`, `HowToSection.js`), not via the `filled` prop value. See `docs/rewrite/bugs.md` BUG-014 and `docs/design/visual-language.md`.
- Date resolved: 2026-09-18

### Native `window.confirm()` for every destructive action — gap or deliberate simplicity?

- Status: Unknown (leaning: unfinished, not deliberate — see `docs/design/states.md`)
- Area: Design / States
- Sources inspected: every delete/unsubscribe/discard-changes call site across `webapp/src/pages/**`
- Historical evidence: a fully custom, animated, on-brand `Dialog` organism exists and is used for several non-destructive flows, but literally zero destructive-action confirmations use it.
- Conflicting evidence: none — the pattern is 100% consistent (always native, never custom) across every instance found.
- Possible interpretations: (a) the team simply never got to building a styled confirm variant; (b) a deliberate choice to rely on the browser's own trusted-UI confirm for irreversible actions (some products do this intentionally, since a native dialog can't be visually spoofed by injected content the way a custom one theoretically could).
- Recommended investigation: none further possible from source alone — this is a product-intent question for a human decision, not a fact recoverable from code.
- Decision: pending product decision — do not silently "upgrade" to a custom dialog; record whichever choice is made in `docs/rewrite/intentional-changes.md`.
- Date: 2026-09-18

### Login always returns to `/user/projects`, never the referring page

- Status: Confirmed (historical behavior) / Unknown (whether intentional)
- Area: Authentication / Navigation
- Sources inspected: `webapp/src/pages/api/login.js` (hardcoded `returnTo: '/user/projects'`)
- Historical evidence: every login, regardless of trigger location (nav menu, landing-page CTA, etc.), lands on the same fixed destination.
- Conflicting evidence: none.
- Possible interpretations: (a) deliberate simplicity — there's only one meaningful authenticated landing screen in this product anyway; (b) an unaddressed UX gap (a visitor who clicked "create project" from a specific context loses that context after login).
- Recommended investigation: none further possible from source; product-intent question.
- Decision: pending product decision — preserve as-is by default (per product-fidelity rules, this is observable behavior, not an obvious defect) unless explicitly approved as an improvement.
- Date: 2026-09-18

### Slate rich-text-editor field-order workaround

- Status: Confirmed (historical bug existed) / Unknown (relevance to the replacement editor)
- Area: Design / Project creation
- Sources inspected: `e2e/cypress/integration/_projects.spec.js` (source comment + linked upstream issue)
- Historical evidence: a documented upstream `slate` bug required the description rich-text field to be interacted with before other fields, or project creation would fail.
- Conflicting evidence: none.
- Possible interpretations: irrelevant once Nusszopf 2 picks a different rich-text approach (see `docs/architecture/mapping.md`), but the underlying *symptom* (a form silently failing to submit under some field-interaction order) should be explicitly tested against in the replacement's regression suite, in case a different-but-analogous issue exists.
- Recommended investigation: none until the rich-text-editor replacement is chosen.
- Decision: pending rich-text-editor architecture decision.
- Date: 2026-09-18

### Search E2E coverage gap

- Status: Confirmed (gap exists) — not a historical-behavior ambiguity, listed here as a testing-debt flag
- Area: Testing / Journeys
- Sources inspected: `e2e/cypress/integration/_search.spec.js` (`xcontext`, three no-op stub tests)
- Historical evidence: no real E2E coverage of search querying, filtering, or contacting a project owner from a search result ever existed.
- Conflicting evidence: none.
- Possible interpretations: none needed — this is a known gap, not an ambiguity.
- Recommended investigation: none — action item, not a research item: the Playwright rewrite must cover these three flows from scratch, using `docs/design/screens.md`'s Search section and `docs/search/README.md` as the behavioral spec instead of E2E evidence.
- Decision: close the gap in the new suite; do not treat "no historical E2E" as "no requirement to test."
- Date: 2026-09-18

### Unexplained 2-second wait in the account-deletion E2E test

- Status: **Resolved** (negative result — cause remains unexplained, but the candidate explanation is ruled out)
- Area: Testing / Journeys
- Sources inspected: `webapp/src/pages/user/profile.js`, re-read in full.
- Resolution: **no loading-state gates the delete-account button's interactivity.** The button (`btn_delete-account_settings-page`) is only `disabled` while its own delete mutation is in flight (`disabled={loadingDeleteUser}`) — there is no page-load/user-data-load condition disabling it beforehand. The candidate explanation ("waiting for the button to become interactive") is therefore ruled out; the `cy.wait(2000)` remains genuinely unexplained (most likely plain flakiness mitigation). The Playwright rewrite should not reproduce a fixed sleep and has no loading-state to assert on instead for this specific button — just click it once the page has rendered.
- Date resolved: 2026-09-18

### Landing-page `data-test` selectors not found in the read source

- Status: **Resolved** (partially — one selector found, the other confirmed genuinely absent)
- Area: Design / Journeys
- Sources inspected: exhaustive grep of `route_search-page` / `route_create-project-page` across the entire `web-nusszopf` source tree, plus `HowToSection.js`/`StepCard.js`.
- Resolution: `route_search-page` **Confirmed** to live in `HowToSection.js` (see `docs/design/screens.md`/`docs/journeys/README.md` Journey 1). `route_create-project-page` **does not exist anywhere in this checkout** — not CMS data, not an unopened container, genuinely absent from the source tree at the studied commit. This is now treated as a confirmed stale/dead E2E assertion (the test may have already been broken at this historical commit), not an evidence gap to keep investigating. See `docs/journeys/README.md` Journey 1 for the corrected treatment.
- Date resolved: 2026-09-18
