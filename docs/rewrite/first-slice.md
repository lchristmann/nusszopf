# First Vertical Slice

This is an engineering-sequencing decision, not a product-redesign decision — nothing here changes
what Nusszopf 2 is; it only decides what gets built first so the architecture gets validated with
the least throwaway work. Nothing in this document has been implemented.

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

## What this decision does not settle

The rich-text editor package, the `Request`/`ProjectRequest` naming decision, and the mail-provider
default (needed as soon as any Notification is added) must still be made — see
`docs/rewrite/decisions-register.md` — before the *second* slice (the full creation wizard, requests,
and account lifecycle completion) can begin. This document only sequences the *first* slice.
