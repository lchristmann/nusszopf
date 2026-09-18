# Open Questions

Unresolved historical behavior, consolidated from the archaeology pass across `docs/domain/`, `docs/design/`, `docs/authentication/`, `docs/email/`, `docs/search/`. None of these are resolved by inventing an answer — each needs either more evidence (a follow-up archaeology pass over a specific file/dataset named below) or an explicit product decision once evidence is exhausted. Do not silently resolve any of these in application code.

### Lead (newsletter) creation route

- Status: Unknown
- Area: Domain / Newsletter
- Sources inspected: `be-nusszopf/hasura/metadata/tables.yaml` (confirms `anonymous`/`user` cannot insert `leads` directly), `be-nusszopf/auth0/rules/syncWithHasura.js` (confirms a `POST /api/newsletter` call exists for the signup-opt-in case)
- Historical evidence: the actual `/api/newsletter` route implementation lives in `web-nusszopf` (Next.js API route, not opened in the backend-focused archaeology pass) and is presumably also how the public newsletter-subscribe form (`containers/home/NewsletterSection`) creates leads.
- Conflicting evidence: none — just unread.
- Possible interpretations: single shared privileged endpoint for both "opt in during signup" and "opt in via the public home-page form"; or two separate paths that happen to share a name.
- Recommended investigation: read `web-nusszopf/projects/webapp/src/pages/api/newsletter.js` and `src/utils/functions/newsletter.function.js`.
- Decision: pending investigation.
- Date: 2026-09-18

### `Project.location` / `Project.period` exact JSON shape

- Status: Unknown
- Area: Domain / Project
- Sources inspected: `be-nusszopf/hasura/migrations` (confirms both are `jsonb NOT NULL`, no shape enforced at the DB level)
- Historical evidence: frontend code references `location.data.osm`, `location.data.city` (`webapp/src/pages/projects/[id].js`) and a `remote` boolean + `searchTerm` (`user/project/create.js` initial values) — enough to know the shape has at least `{ remote, searchTerm, data: { osm, city, ... } }` for location and `{ flexible, from, to }` for period, but not the complete shape (e.g. what `osm` contains beyond `type`/`id`).
- Conflicting evidence: none.
- Possible interpretations: `location.data` mirrors an OpenStreetMap Nominatim result subset.
- Recommended investigation: read `ProjectForm/LocationField.js`, `PeriodField.js`, and the `Combobox`-based location search implementation.
- Decision: pending investigation.
- Date: 2026-09-18

### `Request.category` value set

- Status: Unknown
- Area: Domain / Request
- Sources inspected: `be-nusszopf/hasura/migrations`, `tables.yaml` (confirms `category` is unconstrained free text, no CHECK/enum)
- Historical evidence: `webapp/src/utils/enums.js` defines `REQUEST_CATEGORY = { companions, rooms, materials, financials, others, none }` — this is the frontend's own enum, and E2E (`_projects.spec.js`) selects `companions` by value.
- Conflicting evidence: none — this is actually resolved by frontend evidence already read (`docs/design/screens.md` cites the same enum). Downgrading this entry's severity: the **value set** is Confirmed (`companions`, `rooms`, `materials`, `financials`, `others`, `none`); what remains Unknown is only the exact **displayed German labels** for each category.
- Possible interpretations: labels likely appear in `assets/data/request-form.data.js` or similar, not yet read.
- Recommended investigation: read `webapp/src/assets/data/*.data.js` for the category select's option labels.
- Decision: pending investigation.
- Date: 2026-09-18

### `ProjectAnalytics` row auto-creation mechanism

- Status: Unknown
- Area: Domain / Project
- Sources inspected: `be-nusszopf/hasura/migrations/1615727014588_feature_visitor_counter`, `tables.yaml`
- Historical evidence: no trigger/default creates a `projects_analytics` row when a `Project` is created; the insert permission is open to both roles with no check, implying *something* client-side inserts a zeroed row (likely on first view of the project-detail page, mirroring the view-increment logic in `webapp/src/pages/projects/[id].js`, which does distinguish "no analytics row yet" (`_views === null`) from "row exists").
- Conflicting evidence: none.
- Possible interpretations: confirmed by re-reading `projects/[id].js`'s `updateViews` effect — it does branch on `_views === null || undefined` and calls `apolloAddProjectAnalytics` in that case. This is **actually Confirmed**, not Unknown — downgrade: the mechanism is "first viewer's browser lazily inserts the row." Kept here as a flag that this entry needs its status corrected in a follow-up documentation pass rather than left contradictory between `docs/domain/relationships.md` (still marked Unknown) and this finding.
- Recommended investigation: none further needed — just reconcile the two docs.
- Decision: reconcile `docs/domain/relationships.md`'s `ProjectAnalytics` section to Confirmed on next edit.
- Date: 2026-09-18

### `NavHeader mode="external"` and `Footer variant="auth0"` actual usage sites

- Status: Unknown
- Area: Design / Navigation
- Sources inspected: all of `webapp/src/pages/**` (no call site found passing either prop value)
- Historical evidence: both code paths exist and are fully implemented in `ui-library`.
- Conflicting evidence: none.
- Possible interpretations: (a) used by `auth-login`/`auth-password`, which share the same `ui-library` package but weren't opened for this check; (b) dead code from an earlier iteration of the marketing/auth split.
- Recommended investigation: grep `auth-login/src` and `auth-password/src` for `NavHeader`/`Footer` imports and prop values.
- Decision: pending investigation — do not delete as "dead code" without checking (a).
- Date: 2026-09-18

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

- Status: Unknown
- Area: Design / Visual language
- Sources inspected: `ui-library/stories/atoms/Button/Button.theme.js`, `Button.css`
- Historical evidence: the theme file declares three variants, but `Button.css` defines only one visual treatment per color, and both `outline` and `filled` map to the identical class.
- Conflicting evidence: none within these two files; not yet cross-checked against every `<Button variant="...">` call site across the whole frontend.
- Possible interpretations: (a) "filled" was planned but never styled; (b) some call sites apply additional inline Tailwind utility classes on top to fake a filled look, making it a real (if inconsistently-implemented) visual state.
- Recommended investigation: grep every `<Button ... variant="filled"` / `variant="outline"` call site in `webapp/src/**` and `ui-library/stories/**` and diff their surrounding `className` props.
- Decision: pending investigation; do not silently implement a "filled" look in Nusszopf 2 without confirming it was ever real.
- Date: 2026-09-18

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

- Status: Unknown
- Area: Testing / Journeys
- Sources inspected: `e2e/cypress/integration/_settings.spec.js`
- Historical evidence: a hardcoded `cy.wait(2000)` immediately before clicking "Delete account," with no comment explaining why.
- Conflicting evidence: none.
- Possible interpretations: waiting for user/session data to finish loading before the delete button becomes interactive (a loading-state timing issue the test worked around rather than asserting on); or simple test flakiness mitigation.
- Recommended investigation: check whether `/user/profile` has an observable loading state gating the delete button's interactivity (`docs/design/states.md` — profile page loading only covers the newsletter subsection, not explicitly the delete button, in what was read so far).
- Decision: the Playwright rewrite should assert on an explicit loading-state signal rather than reproducing a fixed sleep, regardless of the answer.
- Date: 2026-09-18

### Landing-page `data-test` selectors not found in the read source

- Status: Unknown
- Area: Design / Journeys
- Sources inspected: `webapp/src/pages/index.js`, `_landingpage.spec.js`
- Historical evidence: the E2E spec drives `[data-test="route_search-page"]` and `[data-test="route_create-project-page"]`, neither of which appears in `pages/index.js` itself.
- Conflicting evidence: none — likely just located inside a container/CMS-data path not opened in this pass (`HowToSection`, or a `Route` inside `assets/data/header.data.js`-driven markup).
- Recommended investigation: grep `containers/home/**` and `assets/data/*.data.js` for these two literal strings before finalizing Journey 1 in `docs/journeys/README.md`.
- Decision: pending investigation.
- Date: 2026-09-18
