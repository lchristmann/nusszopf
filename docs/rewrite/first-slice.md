# First Vertical Slice

This is an engineering-sequencing decision, not a product-redesign decision — nothing here changes
what Nusszopf 2 is; it only decides what gets built first so the architecture gets validated with
the least throwaway work. The first slice is implemented; the second slice (the wizard, `docs/rewrite/second-slice.md`) is implemented.

## Candidate 1 — Public read-only surface (Landing → Search → Project detail)

**Scope**: `/`, `/search`, `/projects/{id}`, no authentication, no writes. Seed the database and
Meilisearch index directly (factories/seeders), not through a write path.

- Why useful: the fastest way to get something visually comparable to the historical product on
  screen — directly exercises the hardest-to-get-right part of the fidelity requirement (exact
  colors, type scale, `FramedGridCard` layout, Masonry search grid) against real historical
  screenshots/markup early, while the evidence is freshest.
- Layers exercised: routing, Blade views, the shared component library (`Frame`, `FramedGridCard`,
  `Masonry`, `HitCard`), Eloquent reads, PostgreSQL, Laravel Scout query-side integration with
  Meilisearch, `Project::visible()`/`Request::visible()` scopes (closing BUG-002 from day one).
- Historical behavior validated: home page section order/colors, search's three states
  (initial/results/empty) and masonry breakpoints, project-detail layout and dual contact paths,
  visibility filtering (the single highest-priority open security question in the whole spec).
- Infrastructure forced: `web`/`php-fpm`/`postgres`/`meilisearch` Compose services, Scout index
  settings as versioned config (closing BUG-008), base Docker image build.
- Tests enabled: Playwright visual-parity screenshots for three screens, Feature tests for the
  visibility scope, a Meilisearch integration test.
- Risks: builds Blade/component patterns before any Livewire interactivity exists to validate them
  against real form/state complexity — risk of later rework once forms need to reuse these
  components interactively. No auth, no writes, no queue, no mail — three-quarters of the stack is
  unvalidated.
- Leaves unresolved: everything write-side (auth, project creation, the two remaining
  authorization bug fixes that need a write path to test end-to-end, mail, background jobs).

## Candidate 2 — Account lifecycle (register → login → logout → password reset → profile → delete)

**Scope**: no `Project`/`Request` domain at all yet — just `User` and the full authentication
surface replacing Auth0.

- Why useful: every other screen in the product requires a session; this is the actual dependency
  root of the whole application, and it is the single largest historical-infrastructure
  replacement (Auth0 → Laravel-native), so proving it out first de-risks the biggest "this needs a
  fresh design, not a port" piece of the whole rewrite.
- Layers exercised: Laravel's auth guard/session, password hashing/policy, Laravel Notifications
  (welcome email, password-reset email), queued mail, rate limiting (`throttle` middleware),
  account-deletion cascade (though nothing to cascade yet), Livewire forms with Formik-equivalent
  validation UX (inline errors, the historical five-rule password policy).
- Historical behavior validated: registration/login field validation and copy, no-email-verification
  default, social login (Google), the 8-hour session duration decision, welcome-email content,
  password-reset two-step flow, account-deletion confirm-then-logout-then-toast flow.
- Infrastructure forced: full mail configuration (SMTP + queue), `queue-worker` Compose service,
  session/cache backing via Redis, first real CI Feature-test suite, first Playwright auth journey
  with `storageState` reuse (per `docs/testing/README.md`).
- Tests enabled: the full historical `_auth.spec.js`/`_settings.spec.js` journeys as Playwright
  specs, Feature tests for every validation rule and rate limit.
- Risks: on its own, doesn't touch the `Project`/`Request` domain at all — after this slice ships,
  the product still doesn't "look like Nusszopf" to an outside observer, and none of the
  domain-specific bug fixes (BUG-001, BUG-002) get exercised.
- Leaves unresolved: the entire project/request domain, search, visual fidelity of the app's main
  screens (only auth screens get built).

## Candidate 3 — Full owner-side project lifecycle (create → edit → publish → delete, with requests)

**Scope**: the historical 4-step creation wizard, the 3-tab edit screen, requests as a child
resource, visibility toggle, search indexing on write, view/contact counters.

- Why useful: this is the product's actual "spine" — the single richest concentration of domain
  logic, authorization rules, and the two highest-severity historical bug fixes (BUG-001, BUG-002)
  in one place. Shipping this proves the architecture can handle the hardest case early.
- Layers exercised: multi-step Livewire forms with cross-step validation, the rich-text editor
  decision (now unblocked — six-tool toolbar confirmed), `ProjectPolicy`/`RequestPolicy`, Eloquent
  relationships + cascading deletes, Scout write-path indexing via queued jobs (closing BUG-009),
  server-controlled analytics counters (closing BUG-001), inherited visibility (closing BUG-002).
- Historical behavior validated: the entire project-creation/edit/settings/delete journey
  (`_projects.spec.js`), the request category enum and its confirmed German labels, the
  location/period JSON shapes now fully documented, the "no separate publish workflow" behavior.
- Infrastructure forced: everything Candidates 1 and 2 force, simultaneously, plus the rich-text
  package decision and the `Request`-vs-`ProjectRequest` naming decision must both be made before
  a single migration can be written.
- Tests enabled: the full historical `_projects.spec.js` journey, Feature tests for both
  authorization fixes with regression coverage, search-sync job tests.
- Risks: by far the largest scope of the three — depends on Candidate 2 already existing (a user
  must be able to log in before owning a project), and depends on two currently-undecided
  architecture questions (rich-text package, `Request` naming) being resolved first, which could
  stall the start of implementation rather than de-risking it early.
- Leaves unresolved: the public/anonymous side of search and project viewing (Candidate 1's scope),
  the newsletter/`Lead` domain entirely.

## Selected slice: a thin, complete vertical cut through Candidates 1 + 2 + a minimal Candidate 3

Recommendation: **do not build any single candidate whole.** Each one, built to completion first,
either produces a large amount of infrastructure with nothing recognizably "Nusszopf" to show for
it (Candidate 2), or builds UI patterns before the interactive/write-path complexity exists to
validate them (Candidate 1), or is too large and dependency-blocked to be a *first* slice
(Candidate 3).

The recommended first slice is the smallest possible **complete path** that touches every
architectural layer once, deliberately scoped down from the full historical feature set:

1. Register and log in (Candidate 2's core, without password reset, without social login, without
   avatar — just email/username/password registration and session login).
2. Create **one** project via a **single-form** (not the historical 4-step wizard — the wizard
   itself is deferred to a follow-up slice once the rich-text/naming decisions are made), with no
   `Request` child resource yet, visibility defaulting to `private`.
3. Publish it (`visibility → public`) and view it on its own public `/projects/{id}` page as an
   anonymous visitor.
4. See it appear in `/search` (proving the Scout/Meilisearch write path and the visibility-aware
   index end to end, closing BUG-002's core mechanism from the very first commit).

This single path forces the *entire* architecture to exist — Docker Compose foundation, Postgres,
Redis, Meilisearch, the auth guard, one real `Policy`, one real Livewire form with validation, one
queued job (search sync), one Blade public view, one CI pipeline exercising all of it — without
committing to the historical wizard's exact step structure, the rich-text package, the `Request`
naming decision, or mail/notifications, every one of which can be decided and added in the
*second* slice without reworking anything this first slice establishes. It produces the smallest
amount of throwaway work of the three pure candidates, because nothing about a single-form project
creation needs to be rebuilt once the wizard is added later (the wizard becomes an additional UI
layer over the same underlying create action, not a replacement for it), and nothing about minimal
auth needs to be reworked once password reset/social login are added (they're additive routes on
the same guard).

## The historical 4-step wizard — full mechanics (Confirmed, pre-implementation review pass 2026-09-18)

Read in full for this review: `webapp/src/pages/user/project/create.js`,
`ui-library/stories/organisms/Stepper/useStepper.js`. This is the exact historical behavior that
the first slice's single form temporarily stands in for — it is the specification the *second*
slice's wizard implementation must match, not a design still to be invented.

- **One Formik form instance for the entire wizard**, not four separate per-step forms and not a
  persisted multi-step draft. `initialValues` covers every field across all four steps at once
  (`title`, `goal`, `description`, `requests: []`, `location`, `period`, `team`, `motto`,
  `visibility: 'public'`, `contact: false`) and the same `formik.values` object accumulates state
  as the visitor moves through steps.
- **No server-side persistence between steps and no draft entity.** Nothing is written to the
  backend until the final step's submit fires `addProject`. Leaving the wizard mid-flow (closing
  the tab, navigating away) discards all progress silently — there is no "resume your draft" and
  no autosave. This is a real historical behavior to preserve, not an oversight to fix — do not add
  draft persistence as an "obvious improvement" without a fresh product decision.
- **Step navigation is a URL query parameter** (`?step=N`), driven by `useStepper`'s `goForward`/
  `goBack`, which both call `router.push({ pathname: '/user/project/create', query: { step } })`.
  The step is deep-linkable and browser-back/forward-navigable, not just component-internal state.
- **Per-step validation gates forward navigation**, not backward navigation. *(Corrected in the second slice: the gate is Formik's `validationSchema` for the current step, not `getNextStep`'s `requiredSchema` — no step ever receives that prop — see `docs/rewrite/second-slice.md`.)* A step with no schema (the Requests step) can always be passed through, confirming zero
  requests is a fully valid, unremarkable path, not an edge case to special-case. `goBack` has no
  validation at all — the wizard never blocks moving backward.
- **Defensive re-validation at final submit**: `handleSubmit` re-runs `step1ValidationSchema`/
  `step2ValidationSchema` against the accumulated values even after the stepper itself considered
  the form complete, and shows a distinct error toast if that defensive check somehow fails despite
  the stepper's own gating — a belt-and-suspenders pattern worth preserving conceptually (validate
  once more at the true point of persistence), though Nusszopf 2's single-pass Livewire validation
  may make the redundant check moot; confirm at implementation time rather than assuming either way.
- **No explicit "cancel wizard" affordance and no discard-confirmation prompt** — unlike the *edit*
  screen's tab-switch "discard changes?" `confirm()` (see `docs/design/states.md`), the create
  wizard has no equivalent for leaving mid-flow. The only exit affordance is the `NavHeader`'s
  back-chevron (`goBackUri: '/user/projects'`), which navigates away with no prompt at all. This
  asymmetry between create (silent discard) and edit (confirmed discard) is itself historical
  behavior to preserve, not to unify — they are different flows with different state models (create
  has nothing persisted yet to lose; edit has real persisted data the discard-confirm protects).
- **The final submit button is the same DOM element/label as "Next"** on every step but the last —
  differentiated only by which step is current, not by rendering a visually distinct "Create"
  button earlier.

## Final Nusszopf behavior vs. temporary implementation-slice scaffolding

This distinction is load-bearing: nothing below in the "temporary" column is a product decision,
and none of it should be mistaken for the target Nusszopf 2 UX by a future implementation agent
picking this document up cold.

| Concern | Final Nusszopf 2 behavior (the actual target) | First-slice scaffolding (temporary only) |
|---|---|---|
| Project creation UI | 4-step wizard, exact mechanics above, built once the rich-text-editor and `ProjectRequest`-naming decisions are in place (both now Adopted — see `docs/rewrite/decisions-register.md` — so nothing blocks starting the wizard in the *second* slice) | A single-page form covering only `title`, `goal`, `description`, `visibility` — no steps, no requests, no team/motto/contact fields. This exists purely to prove the Livewire form → Policy → Eloquent → Scout path once, end to end, with the least code. It is explicitly not the four-step wizard, not a simplified *permanent* creation flow, and not evidence that Nusszopf 2 "doesn't really need" the wizard. |
| Requests (child resource) | Every project can have zero or more `Request`s, added via a dialog during creation (step 3) or from the edit screen's "Gesuche" tab | Out of scope entirely for the first slice — no `Request` model/table touched yet. Added in the second slice alongside the wizard. |
| Authentication | Full registration/login/logout/password-reset/social-login(Google)/avatar-upload surface, per `docs/authentication/README.md` in full | Only email/username/password registration and session login/logout. No password reset, no Google Socialite, no avatar. This is a sequencing cut, not a scope cut — `docs/authentication/README.md` remains the full authoritative spec for what ships in a later slice, unchanged by what the first slice implements. |
| Visibility default | `private` at creation, matching history (`docs/domain/entities.md`) — *the second slice found that the wizard's own initial value is `public` (`create.js` `initialValues`); the DB default is `private`* | Same — the first slice does not change this default; it publishes explicitly as its own step (see acceptance criteria below), not automatically. |
| Draft/autosave | None — matches history exactly (see above) | None — this happens to be identical between final and temporary behavior, so there is nothing to reconcile later. |

No temporary shortcut in the row above is a silent product decision. If a future implementation
agent is ever tempted to treat the first slice's single-form creation screen as "good enough" and
skip building the wizard, that would be an unapproved product change requiring the same scrutiny as
any other deviation from `docs/design/screens.md` — this document does not grant that permission.

## First-slice acceptance criteria

The first slice is complete only when all of the following hold, each independently verifiable:

1. A visitor can register (email + username + password + required privacy-consent checkbox,
   validated per `docs/authentication/README.md` §2's five-rule password policy) and is logged in
   immediately afterward — no email-verification gate (matches history).
2. A logged-in user can create exactly one project through a single Livewire form (`title`, `goal`,
   `description`, `visibility`), defaulting to `visibility = private`.
3. The owner can view their own private project at `/projects/{id}` (owner-only visibility works).
4. A different/anonymous visitor requesting the same private project's URL receives a 404, not the
   project content and not a 403 revealing existence (matches the confirmed historical behavior —
   see the resolved `/projects/{id}` SSR open question).
5. The owner can toggle `visibility` to `public` through the same update path used for editing
   content (no separate "publish" mutation/ability — matches history).
6. Once public, an anonymous visitor can view the project at `/projects/{id}`.
7. The published project appears in `/search` results (proving the Scout/Meilisearch write path)
   and — this is the one point where the first slice must exceed simple parity — a *private*
   project (or one flipped back to private) must never appear in `/search`, closing the confirmed
   historical indexing-time visibility gate (`docs/search/README.md`) from the very first commit.
8. `RequestPolicy`/`Request::visible()` do not need to exist yet (no `Request` model in this slice),
   but `ProjectPolicy::view()` and a `Project::visible()` scope must exist and be the single
   enforcement point used by both the direct-URL path and the search-indexing path — not two
   independently-implemented checks that could drift apart.
9. CI runs and passes: Pint, Larastan, the Feature-test suite (including an allow-case and a
   deny-case for every `ProjectPolicy` ability exercised), and at minimum one Playwright journey
   covering steps 1–7 above end to end.
10. The whole stack runs via `compose.dev.yaml` (Postgres, Redis, Meilisearch, `php-fpm`, `web`,
    `workspace`) with no host-installed PHP/Node/Postgres/Redis/Meilisearch.

## What this decision does not settle

The rich-text editor package, the `Request`/`ProjectRequest` naming decision, and the mail-provider
default are now Adopted (Category B, `docs/rewrite/decisions-register.md`) and no longer block the
*second* slice (the full creation wizard, requests, and account lifecycle completion). The one
genuine remaining human decision before the second slice's newsletter-adjacent work can proceed is
the newsletter opt-in asymmetry (BUG-011, Category A) — everything else previously listed here has
been resolved. This document only sequences the *first* slice; it does not itself constitute
approval to begin implementation — see `docs/rewrite/specification-review.md` for the overall
pre-implementation status.
