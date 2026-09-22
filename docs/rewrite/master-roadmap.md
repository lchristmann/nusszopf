# Master Roadmap — finishing the Nusszopf rewrite

Status: **Revised 2026-09-21 after the human-decision review (A-1…A-8, A-10 decided; see
`docs/rewrite/decisions-register.md`) and APPROVED by the maintainer (A-9, 2026-09-21) for slice-by-slice implementation.** The original text was a planning
document only; slice and finish-line wording below now reflects those decisions. Written after slices 1 and 2 were
verified. It supersedes the informal "later slices" list in `docs/rewrite/README.md`.

Evidence labels follow `CLAUDE.md` (**Confirmed** / **Inferred** / **Unknown**). "Confirmed (repo)"
means confirmed by inspecting the current rewrite repository; everything else cites the historical
sources through the existing `docs/` files, which are snapshots (`docs/rewrite/source-map.md`).

## 0. How this was built, and its limits

Read: `CLAUDE.md`, `.claude/rules/*`, `docs/rewrite/*` (register, open questions, bugs, both slice
specs, architecture decisions, review), `docs/design/screens.md` + `screen-specs.md`,
`docs/journeys`, `docs/domain/workflows.md`, `docs/email`, `docs/authentication`, `docs/security`,
`docs/search`, `docs/testing`, `docs/deployment`, `docs/release`, the routes, Livewire components,
Blade views, migrations, tests, Compose files, CI, `composer.json`. Historical spot checks:
`webapp/src/pages/**` (route and API inventory), `pages/api/{sitemap,upload,events/*}.js`,
`FilterPopover`, `hasura/metadata/cron_triggers.yaml`, license files.

Limits, stated plainly:

- I did not re-read every historical component. Where the roadmap says "verify" the slice's first
  task is a targeted archaeology pass (the same discipline slice 2 used), not a guess.
- I did not run the test suites or the Compose stack for this pass; the counts and states below are
  from reading the repository and the slice-2 status recorded in the docs.
- The task text was cut off mid-sentence at section 7 ("Nusszopf Rewrite Def…"). I read it as
  "Definition of Done" and wrote section 7 on that basis. Correct me if a different artifact was meant.

## 1. Where the rewrite stands (Confirmed, repo)

| Area | Implemented | Not implemented |
|---|---|---|
| Auth | Register (username/email/password, 5-rule policy), login (email or username), logout, server-side route gate (BUG-003), password reset, Google login (Socialite, hidden if unconfigured), welcome/reset/blocked-account/verification emails, per-account login lockout with notice (B-7/B-12), avatar sync (BUG-004), e-mail verification gating the personal contact (BUG-030, A-3) | Profile-page resend-verification UI (only reachable from the project contact field so far — slice 8) |
| Projects | Detail (owner/visitor, share, location/period/rich text/team/motto/contact), wizard (4 steps), edit (3 views), delete, visibility policy (BUG-007/021) | Requests, contact dialog, visitor counter, report link, avatar image |
| My Projects | Grid of own projects, "create" button, edit link | Per-card actions (visibility toggle throttled 1/s, delete, click-through), skeleton, `WelcomeCard` verification |
| Search | Meilisearch/Scout indexing of projects, results page, visibility defense-in-depth (BUG-008/009 search-sync path) | Request hits nested in project cards, category filter popover, load-more, scroll-to-top, skeleton/no-hits states (**verify** against `screen-specs.md`), reindex/recovery command |
| Static/public | Nav header, footer, `/privacy` placeholder, toasts | **Home** (7 sections), real Impressum/AGB/Datenschutz, 404/500 pages (no `resources/views/errors`), sitemap, SEO tags |
| Profile | — | Entire `/user/profile` (avatar dialog, newsletter, sponsoring, delete account) |
| Newsletter | — | Lead model, forms, confirm/unsubscribe pages, emails |
| Mail | Mailer configured (SMTP env) | Every mailable; MJML template port |
| Infra | Dev/prod Compose with queue-worker and scheduler; CI: lint, Larastan, Pest, asset build, Docker build, Playwright × 3 engines | Operator `docker-compose.yaml`, release/publish workflow, health depth, backup/restore scripts, scheduled jobs (`routes/console.php` is empty), version exposure, LICENSE/CONTRIBUTING/SECURITY files |
| Docs | Slice specs, indexes point at slices | `docs/deployment/operations.md` is still a labelled **Proposal** ("Nothing here is implemented") |

## 2. Remaining product scope — full inventory

Grouped by the categories requested. "Basis" is the historical evidence or the doc that requires it.
Items with **no** historical basis are listed separately in 2.2 so they are not built by habit.

### 2.1 Historical functionality still to reproduce

**Accounts and authentication**

1. Password reset: "Passwort vergessen" request view, set-new-password screen, reset-link email
   (`change-password.mjml`). Basis: `docs/authentication/README.md` §4.
2. Google login (Apple is dropped, BUG-012). Requires operator-supplied OAuth credentials.
3. Brute-force protection and the "IP blocked" notice email. Threshold is Adopted default (register B12);
   the *email* is only meaningful if lockout is designed to notify — see decision B-7.
4. Welcome email on signup (unconditional, `welcome.mjml`).
5. Avatar sync from social login, fill-if-empty only (BUG-004, spec'd).
6. Email verification: historically **never enforced** (Confirmed). Whether to preserve is an open
   product decision (`authentication/README.md` line 120) — decision A-3.
7. Data: the `users` cascade on account deletion (projects, requests, analytics) and external cleanup.

**Projects and Requests (Gesuche)**

8. `ProjectRequest` (register B7): fields title, description, category
   (`companions|rooms|materials|financials|others`, German labels Confirmed, empty select is a literal `-`).
9. Create/edit/delete requests: `EditRequestDialog`, `RequestForm` fields, wizard step 3, edit "Gesuche"
   view with per-card context menu (Edit/Delete, `menu_edit-request-card`), native `confirm()` on delete.
10. Detail page: request cards, `RequestDialog` (view), empty `InfoCard`.
11. Request visibility inherits the parent project (BUG-002, spec'd, deferred *to this slice*).
12. Requests in the search index (project-gated at index time, `docs/search/README.md`).

**Search**

13. Hits grouped per project with nested request hits; masonry 3/2/1 columns (Confirmed literals).
14. `FilterPopover` with the five category checkboxes.
15. Load-more (`nbHits > hits.length`) with spinner; floating scroll-to-top; skeleton, no-hits.
16. Index recovery: a documented, tested full reindex (Scout `import`/`flush`) and settings sync
    (`scout:sync-index-settings` exists). Historical reindexing was manual (BUG-008/009).

**My Projects**

17. Card actions: open, edit, delete (native confirm), visibility toggle throttled to 1/s; loading
    skeleton; `WelcomeCard` empty state (verify whether the current empty state is the historical one).
    The current component docblock says these were "second-slice scope" — they were not delivered.

**Project detail leftovers**

18. `VisitorCounter` and server-controlled view counting (BUG-001). Historical: once per browser via
    `localStorage`, owner excluded.
19. "Projekt melden" `mailto:` report link with the project id in the subject.
20. Author avatar image (currently initial-on-grey scaffold, see 2.3).
21. `ContactDialog` → server-sent email when contact is "Über Nusszopf"; `Reply-To` (BUG-005),
    validation/sanitization (BUG-010), rate limiting (Preserve), copy fix (BUG-006 applies to newsletter).

**Profile** (`/user/profile`)

22. Avatar dialog: crop, upload (≤1 MB JPEG, versioned filename `…nz_vN.jpeg`), replace, old-file cleanup.
23. Newsletter subsection (subscribe with privacy checkbox / unsubscribe with native confirm).
24. Sponsoring subsection and two `InfoCard`s (contact document link, support `mailto:`) — copy is CMS
    data not yet transcribed (Unknown: literal text/targets).
25. Delete account: native confirm → cascade → logout → success toast; external cleanup (avatar file).

**Newsletter**

26. `Lead` entity; public form (Home `NewsletterSection`, Profile); true double opt-in with 7-day token;
    `/newsletter/subscribe/{token}`, `/newsletter/unsubscribe/{token}`, `/newsletter/unsubscribe/lead`;
    subscribe/unsubscribe confirmation emails; historical quirks (re-submit → HTTP 500 — needs a
    Preserve/Fix classification, see B-9). BUG-011 (consent asymmetry) blocks the signup-checkbox path.
27. Signup "newsletter" checkbox on the register form (currently absent).

**Public/static shell**

28. Home: header/hero, HowTo, About, Contest, Fellows, NewsletterSection (Carousel is disabled
    historically — Preserve as absent). Copy is CMS data to be transcribed verbatim.
29. Impressum, AGB (`/legalNotice`, `/legalPolicy`) and full Datenschutz with the `?back` behavior.
30. 404, 500, generic error and error-boundary page (`ErrorPage`, `bg-warning-200`).
31. Sitemap (`/api/sitemap` historically; public projects + 3 static URLs), `robots.txt`, `de_DE` SEO tags.

**Emails** (`docs/email/README.md`): welcome, change-password, blocked-account, contact, newsletter
subscribe, newsletter unsubscribe are product-triggered. Newsletter issues ("Nussig No. 1–3") and
`support.mjml` have **no app trigger** (Unknown wiring) — decision A-6. Password-breach alert is
Deferred (register C2).

### 2.2 Deliberately NOT in scope (no historical basis; do not build)

Admin/staff role (Confirmed absent); Apple login; `contactRequests` counter (BUG-017, Confirmed dead);
Carousel; tenants/billing/SaaS; comments, follows, notifications, drafts/autosave, bulk actions,
user profiles beyond name/avatar, an API, dark mode, i18n beyond German, cookie-consent banner
(none historically — see A-4 for the legal side), analytics dashboards. Newsletter *campaign sending*
and any admin UI for it (pending A-6).

### 2.3 Known deviations and doc debts to reconcile

- Author avatar: ui-avatars.com replaced by an initial circle in slice 2. That is a self-hosting
  replacement (category "dependency dropped") but has **no `intentional-changes.md` entry**; add one.
- `bugs.md` still shows BUG-013/014/015 as **Unknown** while the register (B10/B11) adopts
  "Preserve" for 013/015 and says nothing on 014. Reconcile (see B-1).
- Password policy: `open-questions.md` says "record as architecture decision once approved"; it is
  implemented (`App\Rules\PasswordPolicy`) and absent from the register. Record it (B-2).
- `composer.json` requires `php ^8.3` and declared `"license": "MIT"` (corrected 2026-09-21, see A-8); `CLAUDE.md` targets PHP 8.5 and
  the historical repositories are **GPL-3.0**. Reconcile the PHP constraint (B-3); license is A-8 (decided: GPL-3.0-or-later).
- `docs/rewrite/README.md` lists "Infrastructure foundation — not started" although dev infrastructure
  exists; the *operator* infrastructure is what is not started. Clarify when that doc is next touched.
- `docs/deployment/operations.md` still says "Nothing here is implemented".

### 2.4 Cross-cutting concerns to run through every slice

Validation parity and German error copy; loading/empty/error/success states per `states.md`;
responsive behavior (`responsive-behavior.md`); visual fidelity; authorization row coverage
(allow **and** deny test per `authorization-matrix.md` row); search-sync on every write path;
queued-job failure behavior (BUG-009); accessibility of new controls (BUG-024-class defects go
through the bug protocol); docs updated in the same change.

## 3. Feature slices

Numbering continues from the two finished slices. Each slice ends with the golden-master review pass
used for slices 1–2 (spec vs. code vs. historical source) and a `CHANGELOG.md` entry. Every slice
begins with an archaeology step for its "verify" items and records corrections in the specs *before*
coding, as slice 2 did.

Slice sizes: S = a few days of focused work, M = about a week, L = more than a week.

### Slice 3 — Project Requests (Gesuche) — **L** — ✅ implemented 2026-09-21 (`docs/rewrite/third-slice.md`)

- **Purpose**: complete the project concept; two scaffolds (wizard step 3, edit "Gesuche") disappear.
- **Covers**: inventory 8–12.
- **Scope**: `ProjectRequest` model/migration (register B7), factory; `RequestPolicy` + `Request::visible()` scope (BUG-002);
  request dialog (create/edit) with category select; wizard step 3 list + dialog; edit view "Gesuche" with the
  context menu; detail page cards + view dialog + empty state; project-delete cascade; **indexing of requests
  only** (documents, project-gated) — search UI stays for slice 4.
- **Depends on**: slice 2.
- **Historical evidence**: `EditRequestDialog`, `RequestForm/*`, `RequestDialog`, `request-form.data.js`,
  `EditProjectViews/RequestsView`, `CreateProjectSteps`, `_projects.spec.js` (request steps), be `tables.yaml`.
- **Existing docs**: `second-slice.md` scaffolding table, `entities.md` (Request), `permissions.md`, `authorization-matrix.md`,
  `screen-specs.md` (create/edit/detail), `search/README.md`.
- **Bugs/decisions affected**: BUG-002 (implement — needs its `intentional-changes` entry already spec'd),
  register B7 (naming, decided), BUG-013 (native confirm, Preserve). Open: request field limits
  (**verify** in `RequestForm/*`), what happens to requests when a project turns private (Inferred: hidden,
  reindexed away — verify against the search trigger).
- **Tests**: policy allow/deny per matrix row; visibility inheritance across every read path; validation;
  wizard step 3 (zero requests valid); edit CRUD; cascade delete; index document shape and delete/hide sync
  (real Meilisearch group).
- **Playwright**: Journey 3 completed (create with requests → edit request → delete request → delete project);
  private project's requests never visible to a visitor.
- **Parity**: request card, dialog, context menu, `InfoCard`, phone layout screenshots.
- **Done when**: no request scaffolding remains, `second-slice.md` "Intentional scaffolding" rows for Gesuche removed,
  BUG-002 marked Implemented.
- **Human approval before starting**: no.

### Slice 4 — Search completion — **M** — ✅ implemented 2026-09-21 (`docs/rewrite/fourth-slice.md`)

- **Covers**: 13–16 (+ BUG-018).
- **Scope**: grouped hits with nested requests; category filter popover; load-more; scroll-to-top; skeleton and
  no-hits states; documented reindex/recovery command and an idempotency test; historical page size (**verify**).
- **Depends on**: slice 3.
- **Evidence**: `search.js`, `search.service.js`, `HitCard`, `FilterPopover`, `SkeletonHits`, `NoHitsSection`,
  `search.function.js`, `docs/search/README.md`.
- **Bugs**: BUG-018 (close the E2E gap — three journeys: query, filter, contact-owner-from-result; the last
  gains its final step in slice 6), BUG-008/009 (extend to requests).
- **Tests**: filter semantics per category combination, pagination boundary (`nbHits` vs. loaded), private/hidden
  never returned, stale index documents filtered, reindex from an empty index equals live-synced index.
- **Playwright**: search → filter → load-more → open project; empty result; index-recovery smoke.
- **Parity**: masonry breakpoints 3/2/1, filter popover animation, floating button, skeleton.
- **Done when**: search screen matches `screen-specs.md` line by line; recovery command documented in
  `docs/deployment`.
- **Human approval**: no.

### Slice 5 — My Projects and project lifecycle completion — **S/M** — ✅ implemented 2026-09-22 (`docs/rewrite/fifth-slice.md`)

- **Covers**: 17–19.
- **Scope**: card actions with the 1-per-second toggle throttle, delete, click-through; skeleton and `WelcomeCard`;
  server-side view counting and `VisitorCounter` (BUG-001); report link.
- **Depends on**: slice 2 (requests not required).
- **Evidence**: `EditProjectCard`, `ProjectsSkeleton`, `WelcomeCard`, `VisitorCounter`, `[id].js` `updateViews`.
- **Bugs/decisions**: BUG-001 (spec'd; implement); dedupe mechanism is B-4; BUG-013 preserved.
- **Tests**: counter cannot be written by any route (regression named in BUG-001), owner views not counted,
  dedupe, toggle sync to search, delete cascades to the index; card action authorization.
- **Playwright**: publish/hide from the grid, delete from the grid, visitor counter increments once.
- **Done when**: `ProjectAnalytics` exists as its own model (register B8) with no client-writable path.
- **Human approval**: no.

### Slice 6 — Mail foundation and project contact — **M** — ✅ implemented 2026-09-22 (`docs/rewrite/sixth-slice.md`)

- **Covers**: 21 and the shared mail infrastructure.
- **Scope**: a mail layout reproducing the shared template anatomy; port of the MJML templates (approach B-5);
  queued mailables on the existing queue worker with retry/failure behavior; `ContactDialog` + endpoint with
  server-side validation/sanitization (BUG-010), `Reply-To` (BUG-005), rate limiting; local mail-catcher service
  for dev/CI; the "Kontaktieren"/"Über Nusszopf" dual path (replaces the slice-2 `mailto:` scaffold).
- **Depends on**: slice 2. Independent of 3–5 (may be reordered).
- **Evidence**: `docs/email/README.md` §5, `ContactDialog.js`, `api/contact.js`, `contact.mjml`, `emails-nusszopf/src`.
- **Bugs**: BUG-005, BUG-010 (needs its `intentional-changes.md` entry *first*), BUG-006 (copy convention).
- **Tests**: mail fakes for trigger/recipient/subject/Reply-To/escaping; header-injection attempts; throttle;
  failed-send retry and dead-letter; rendered HTML snapshot vs. historical template structure.
- **Playwright**: contact from detail page, message received in the mail catcher.
- **Parity**: rendered email compared to the MJML build output; dialog states.
- **Done when**: the slice-2 mailto scaffold row is removed; search-result contact journey (BUG-018) completed.
- **Human approval**: no (A-6 is about later campaign mail, not this).

### Slice 7 — Authentication completion — **M/L** — ✅ implemented 2026-09-22 (`docs/rewrite/seventh-slice.md`)

- **Covers**: 1–6.
- **Scope**: forgot-password + set-new-password screens; reset email; Google login via Socialite, visible only
  when configured (B-6), linking existing accounts by email only when Google asserts the address is verified;
  welcome email; login throttling per B12 with lockout notice (B-7); **email verification per A-3** (verification
  email and `email_verified_at`; login is never gated; an unverified address cannot be published as the
  "Persönlich" project contact nor subscribed to the newsletter — this also touches the existing wizard/edit
  contact field, with regression tests); avatar sync (BUG-004 fill-if-empty); the registration newsletter
  checkbox is rendered but records nothing until slice 9 (explicit scaffold row).
- **Prerequisite docs**: `bugs.md` + `intentional-changes.md` entries (Proposed → Approved) for the verification
  change before coding.
- **Depends on**: slice 6.
- **Evidence**: `auth-login`/`auth-password` apps, `authentication/README.md` §2–6, `auth0/rules/*`.
- **Bugs/decisions**: BUG-004, decision A-3, B12/B13, open question "password policy".
- **Tests**: token expiry/reuse, enumeration-safe reset request (Inferred — verify what historical reset
  screens revealed), throttle windows, session lifetime (8 h), Socialite with a faked provider, avatar sync guard.
- **Playwright**: reset journey end to end via the mail catcher; login lockout; (Google via provider stub).
- **Parity**: the auth screens' `NavHeader mode="external"` and `Footer variant="auth0"` variants (Confirmed live).
- **Human approval**: no (A-3 decided; only the intentional-changes entry needs recording).

### Slice 8 — Profile, avatars and account deletion — **L**

- **Covers**: 20, 22–25.
- **Scope**: `/user/profile` (skeleton, two-column layout); avatar dialog with crop (client crop, server
  validation/re-encode ≤1 MB, versioned filenames), storage on the local disk (register B4), serving path (see §6 —
  a real gap in `compose.prod.yaml`), old-file cleanup; author avatar image on detail/cards replacing the
  initials scaffold; sponsoring + info cards (copy transcription pass); delete account with cascade, logout, toast,
  search de-indexing and avatar-file removal in a way that cannot orphan external state (the historical risk);
  newsletter subsection placeholder recorded as a scaffold row until slice 9.
- **Depends on**: slice 7 (BUG-004 interplay), slice 5 (analytics cascade).
- **Evidence**: `profile.js`, `AvatarDialog.js`, `api/upload.js`, `users.function.js`, `workflows.md`
  (account deletion, picture replacement), `screen-specs.md` profile.
- **Bugs**: BUG-004; open question about orphaned external state on account deletion (resolved by design here).
- **Tests**: deletion cascade completeness (projects, requests, analytics, index docs, files), upload limits and
  MIME/decode validation (do not trust the client crop), version increment, old-file removal, auth = "me" only.
- **Playwright**: upload/crop/replace avatar; delete account journey (no fixed sleeps — open question resolved).
- **Parity**: profile layout, avatar variants (`settings`, `project`), dialog.
- **Human approval**: no.

### Slice 9 — Newsletter — **M**

- **Covers**: 23, 26, 27 (and Home's form component for slice 10).
- **Scope**: `Lead` model; subscribe/unsubscribe flows with signed expiring tokens (7 days historically);
  three public pages; two emails (BUG-006 copy fix); profile subsection; **double opt-in on every path**
  (public form, registration checkbox, profile) — BUG-011 is a Fix (A-1); consent record (requested-at,
  confirmed-at, path, consent-text version; no IP); scheduled purge of unconfirmed leads after 14 days
  (first real use of the scheduler); neutral responses for duplicate subscribe and unsubscribe-by-email
  (no HTTP 500, no enumeration); the lead is deleted with the account (extends slice 8's deletion);
  throttling (Preserve); a documented operator export of confirmed subscribers (Artisan command). Newsletter
  issue composing/sending is **out of scope** (A-6) and the "Nussig" issues and `support.mjml` are not shipped.
- **Depends on**: slices 6, 7, 8. Unblocked by decisions (A-1, A-6).
- **Prerequisite docs**: `bugs.md`/`intentional-changes.md` entries for BUG-011 and the new duplicate/enumeration
  fixes before coding.
- **Evidence**: `newsletter.js`, `newsletter.function.js`, `newsletter/*` pages, `subscribe.mjml`,
  `unsubscribe.mjml`, `workflows.md`, `leads` event trigger (`leads.js`).
- **Tests**: token tamper/expiry, double opt-in state machine, idempotency, unsubscribe-by-email, rate limits,
  emails via mail fake.
- **Playwright**: subscribe → confirm via mailbox → unsubscribe; unknown token → 404 page.
- **Human approval**: no (A-1, A-6 decided).

### Slice 10 — Public shell: Home, legal, errors, SEO — **L**

- **Covers**: 28–31.
- **Scope**: Home in full (transcribe the CMS `*.data.js` copy verbatim; the "how it works" list; sections
  and colors per `screens.md`); Impressum/AGB/Datenschutz pages with `?back`; 404/500/generic error and
  boundary; sitemap and robots; SEO/Open Graph tags; replace the `/` → `/search` redirect (the routes file
  marks it temporary scaffolding) and the `/privacy` placeholder. Home is reproduced **verbatim** (A-5),
  including Contest and the sponsor/fellows logos. Legal pages render **operator-provided content** from
  configured files with a "not configured" notice (A-4); historical texts ship only as labelled examples.
  Operator mailbox/identity are configuration. Self-hosted email logo/fonts; no third-party analytics.
- **Depends on**: slice 9 (newsletter section). Unblocked by decisions (A-4, A-5).
- **Evidence**: `pages/index.js`, `containers/home/*`, `assets/data/*.data.js`, `legal*.js`, `privacy.js`,
  `ErrorPage`, `api/sitemap.js`, `public/robots.txt`.
- **Tests**: sitemap contains only public projects and the three static URLs, 404 shape indistinguishable for
  private vs. missing (invariant from the review), `?back` behavior, error pages for 404/500/other.
- **Playwright**: Journey 1 (landing CTAs; the stale `route_create-project-page` selector stays retired).
- **Parity**: this is the most visual slice — screenshot comparison against the historical Home at three widths.
- **Human approval**: no (A-4, A-5 decided). Recommended: re-read the verbatim Home/sponsor decision before the release candidate.

### Slice 11 — Historical data import — **DROPPED** (A-2 decided 2026-09-21: no import)

Removed: there is no production data to import (A-2). No work item, no finish-line criterion.

### Sequencing notes

- Order 3 → 4 → 5 is fixed by dependency; 6 can move earlier; 7 needs 6; 8 needs 7; 9 needs 6 and 8; 10 last.
- Recommended interleaving: run the **operational track (section 4, O-1/O-2)** right after slice 4 so every
  later slice is exercised in a production-like stack, instead of discovering deployment problems at the end.
- Slices 3 and 8 are the largest; if either overruns, split the dialog UI from the persistence work rather than
  shrinking the definition of done.

## 4. Non-feature phases

These are required to *finish* the rewrite; most are only partly automatable. A phase is needed only
where the evidence says so — reasoning is stated for each.

| # | Phase | Why it is (or is not) necessary | Exit evidence |
|---|---|---|---|
| O-1 ✅ | **Operator distribution** (early, after slice 4) — **done 2026-09-22** (`docs/deployment/README.md`; every named piece exists except a real tag): standalone image-only `docker-compose.yaml` (register decision), entrypoint (migrate, cache), version exposure (architecture decision "Proposed"), `/up` plus health depth (B3 adopted, `spatie/laravel-health` not installed), release workflow to GHCR (only `ci.yml` exists), install script/commands | Self-hosting is a stated goal and none of it exists; historical Nusszopf was never distributed, so there is no evidence to copy | A tag builds, publishes, and a clean VM runs `docker compose up` from the docs |
| O-2 ✅ | **Scheduler and queue reality check** — **done 2026-09-22** (`docs/deployment/operations.md`, "What happens when a dependency is down"; SMTP part deferred to the mail slice, nothing sends mail yet): historical cron triggers are **empty** (Confirmed) so nothing periodic must be reproduced; scheduler is needed only for new hygiene (e.g. expired newsletter tokens, index reconciliation, backup health) — decide per slice, keep `routes/console.php` honest | Avoids inventing jobs; verifies failure/retry (BUG-009) for every queued path | Kill Meilisearch/SMTP/Redis during a write; jobs retry, fail visibly, recover |
| P-1 | **Parity audit** (per-screen) | The only way to detect drift from "faithful"; the slice reviews check slices, this checks the whole | Every `screen-specs.md` row ticked with evidence; every Confirmed claim contradicted by implementation reconciled in docs |
| P-2 | **Visual regression vs. historical** | Hard requirement. Decide the reference source early: Storybook (`ui-library/stories`) is Confirmed to exist; running the full historical webapp needs Auth0/Hasura and is **Unknown** feasible | Screenshot baselines for each screen × {phone, tablet, desktop}; diffs reviewed by a human |
| P-3 | **Accessibility verification** | Historical had known defects (BUG-024); no historical a11y target exists (Unknown) so the bar is decision A-7 | Automated axe run per screen + manual keyboard/screen-reader pass on the journeys; findings triaged through the bug protocol |
| P-4 | **Security review** | Historical had real defects; the rewrite adds mail, uploads, tokens, OAuth | Authorization-matrix coverage report; upload/mail/token abuse tests; security headers and CSP review; `composer audit`/`npm audit` clean or triaged; secret handling; the `/up` and debug surfaces off in production |
| P-5 | **Browser/device verification** | CI already runs Chromium/Firefox/WebKit; real devices and the historical browserslist (verify) are not covered | Manual matrix on iOS Safari and Android Chrome for the journeys; findings fixed |
| P-6 | **Performance sanity** (not a benchmark program) | Historically relevant only where the product did work: search debounce, masonry, SSR pages, image size limit | N+1 check on list screens, page weight of the Vite bundle, search latency on a realistic seeded dataset; no invented budgets |
| P-7 | **Production Compose verification** | The E2E stack is `compose.dev.yaml`; prod images have only been *built* in CI | The whole Playwright suite also runs against the prod images |
| P-8 | **Fresh install** by someone who has not seen the code, using only the docs | Direct test of the "never seen the source" requirement | Timed run recorded; every stumble becomes a doc fix |
| P-9 | **Upgrade/migration testing** N-1 → N with populated data, including the rollback statement (`operations.md`) | Migrations exist and will multiply; policy is "restore from backup, not migrate down" | Tested on data from the previous tag; `upgrades.md` matches |
| P-10 | **Backup/restore drill** | An untested restore is not a backup (own docs) | Restore into an empty host reproduces users, projects, requests, avatars |
| P-11 | **Search/index recovery drill** | Index is derived; recovery must be a command, not folklore | Wipe Meilisearch → one documented command → identical results |
| P-12 | **Queue/scheduler verification** | See O-2 | Included above |
| P-13 | **Email delivery verification** with a real SMTP relay | Fakes prove content, not deliverability (SPF/DKIM are operator concerns — document them) | Every mail type received and rendered in at least Gmail, Outlook and Apple Mail; docs list operator DNS prerequisites |
| P-14 | **Documentation completion**: `operations.md` promoted from Proposal, `docs/` stale-check against code, README/README-DEV, operator guide, troubleshooting, config reference | `CLAUDE.md`: "never leave docs stale" | Doc review checklist, all "Proposal" banners resolved |
| P-15 | **FOSS repository hygiene**: LICENSE (A-8, done), CONTRIBUTING, SECURITY.md, CODE_OF_CONDUCT (historical repos have one), issue/PR templates, third-party license notices, asset provenance (A-5), Dependabot/renovate, `.env.example` audit, no secrets in history | Nothing of this exists in the repository today | Checklist against Waffle Dashboard's repository layout |
| P-16 | **Release preparation and RC testing**: changelog consolidation, versioning per `docs/release/*` (0.x until parity), `1.0.0-rc.N` tags, registry sanity check (register B1 flagged as worth a human look) | Process is documented but never run | An RC installed by a second person; no open Blocker |
| P-17 | **Final parity sign-off** | Human, evidence-based | Section 7 all checked and signed by the maintainer |

Not required in the form suggested by the brief: a separate "load test" and any monitoring/APM stack —
nothing historical requires them and self-hosting principles say to avoid unnecessary dependencies. Logs via
`docker compose logs` are the documented baseline (`operations.md`).

## 5. Unresolved decisions audited

### A. Must be decided by the human

| # | Decision | Why it is a human call | Blocks |
|---|---|---|---|
| A-1 | **DECIDED 2026-09-21: double opt-in on every path, lead deleted with account (see decisions register).** Original: **BUG-011**: should the signup-checkbox newsletter path also require double opt-in? The answer also determines what the privacy text (A-4) must say about consent | Consent/legal semantics; two answers have different legal and UX consequences; not derivable from code | Slice 9 (and the checkbox in slice 7) |
| A-2 | **DECIDED 2026-09-21: no import; slice 11 dropped (see decisions register).** Original: **Is importing the historical production data (users, projects, requests, leads) a goal?** | Scope. Nothing in `CLAUDE.md` demands it, but "revival" may imply continuity for existing users; Auth0 credentials likely cannot migrate, which changes user-facing behavior (forced resets) | Slice 11's existence; release announcement |
| A-3 | **DECIDED 2026-09-21: verify, gate only sharing (see decisions register).** Original: **Email verification**: preserve "never enforced" (fidelity default) or add it (product change; also a real spam/abuse trade-off for self-hosters) | Product behavior vs. security; the docs explicitly call it an approval item | Slice 7 |
| A-4 | **DECIDED 2026-09-21: operator-provided legal text (see decisions register).** Original: **Legal pages**: the historical Impressum/AGB/Datenschutz are the original operator's legal text. What does the FOSS project ship — the historical text as an example, a template operators must replace, or nothing until configured? Includes whether the app may ship without operator-supplied legal pages | Legal/policy; Claude must not write or endorse legal text | Slice 10; release |
| A-5 | **DECIDED 2026-09-21: rights confirmed; Home reproduced verbatim (see decisions register).** Original: **Branding and content assets**: logos, imagery, fonts, Home copy, sponsor/partner logos (Contest, Fellows sections) — may they be reproduced in a FOSS repo, and are the partner sections part of the product or of one 2021 instance? | Rights and project identity; historical sponsors are third parties | Slice 10; release (P-15) |
| A-6 | **DECIDED 2026-09-21: subscription and consent only, plus operator export (see decisions register).** Original: **Newsletter campaigns and `support.mjml`**: no app trigger exists historically (Unknown wiring). Confirm they are out of scope (recommended) or specify how issues are sent | Scope: adding a sender/admin surface would contradict "no admin role" | Slice 9 scope |
| A-7 | **DECIDED 2026-09-21 (see decisions register).** Original: **Accessibility and browser-support policy** (e.g. target level, minimum browser versions) | Project policy; historical had no stated target | Phase P-3/P-5 only (not slices) |
| A-8 | **DECIDED 2026-09-21: GPL-3.0-or-later** (LICENSE, `composer.json`, README, NOTICE done). Original question: license of the repository: `composer.json` says MIT, both historical repos are GPL-3.0, no LICENSE file exists | Legal/policy; also affects what historical code and assets may be reused | Release prep (P-15); should be answered before the first public tag |
| A-9 | **APPROVED 2026-09-21 as written.** Original: **Approval of the finish line (section 7)** and the "1.0.0 = parity" criteria | The maintainer owns what "done" means | Everything downstream; ideally now |
| A-10 | **DECIDED 2026-09-21: GHCR, namespace `ghcr.io/lchristmann/` (see decisions register).** Original: **GHCR vs. Docker Hub sanity check** (register B1) | Adopted already; changing after release breaks every operator's pull command, so it deserves a deliberate human "yes" before the first tag | First release tag |

### B. Claude can decide (constrained by existing spec and principles)

| # | Question | Recommended default |
|---|---|---|
| B-1 | BUG-013/015 Unknown → Preserve (already adopted in register B10/B11); BUG-014 `filled` variant → never implemented (dead vocabulary, Confirmed) | Update `bugs.md` classifications; no behavior impact |
| B-2 | Password policy: the 5 client-mirrored rules as the recorded, flagged-approximation default | Record as architecture decision; already implemented |
| B-3 | `php ^8.3` → align `composer.json` with PHP 8.5 target | Align; verify CI stays green |
| B-4 | Visitor-count dedupe mechanism (historical: per-browser `localStorage`, owner excluded, explicitly "not spoof-proof") | Server-side counting; per-browser dedupe cookie; record as the mechanism behind BUG-001 |
| B-5 | MJML porting approach | Compile committed MJML to HTML at build time into Blade mail views (keeps the historical source of truth); no runtime Node in production images |
| B-6 | Google login when credentials are unset | Button hidden and route disabled (dependency-dropped category; same pattern as `LOCATIONIQ_KEY`); record in `intentional-changes.md` |
| B-7 | Throttle thresholds, whether lockout sends the "IP blocked" email | Documented default (register B12); send the notice only to the account owner when lockout is per-account; document |
| B-8 | Avatar storage layout and serving path | Local disk, versioned names, served by Laravel or by mounting the volume read-only into `web` (see §6) |
| B-9 | Newsletter duplicate-subscribe behavior (historical HTTP 500 is clearly a defect, not a feature) | Classify as new BUG entry (Fix): idempotent, resend confirmation; needs `bugs.md`/`intentional-changes.md` entries first |
| B-10 | Search page size and debounce values | Read from `search.service.js`, do not guess |
| B-11 | Health endpoint content, backup script layout (Tier 1, register B2), reindex command name, version exposure | As already adopted |
| B-12 | Sitemap route (`/sitemap.xml` with a redirect from the historical `/api/sitemap`) | Provide both if search engines historically indexed the API path (verify) |

### C. May remain deferred

Changelog authorship (register C1); breached-password check (C2); S3-compatible storage (B4 says later
upgrade); log aggregation; restricting Meilisearch keys further (BUG-016 moot); the disabled Carousel
(never built); any newsletter-issue tooling if A-6 confirms out of scope.

## 6. Architecture audit

**Verdict: the current architecture is sufficient. No required architectural change.** Every
remaining feature is Livewire + Blade + Eloquent + queue + mail + storage, all present. Checks and
the small items each slice must handle:

| Component | Sufficient? | Notes |
|---|---|---|
| Laravel 13 / PHP 8.5 | Yes | Reconcile `composer.json` constraint (B-3) |
| PostgreSQL | Yes | Need `project_requests`, `leads`, `project_analytics`; cascade deletes at DB level (historical relied on FK cascade) |
| Livewire 4 / Blade | Yes | Dialogs, popover, crop UI already have precedent in slice 2; keep Alpine minimal (rule already decided) |
| Tailwind 4 | Yes | Design tokens for the remaining colors (`bg-warning-200`, home section colors) come from the historical theme |
| Redis / queues | Yes | queue-worker exists; mail and index sync ride on it; failure/retry must be *tested* (BUG-009) |
| Scheduler | Yes, currently unused | Nothing periodic is historically required |
| Scout / Meilisearch | Yes | A second searchable model (`ProjectRequest`) and grouped queries; extend `scout:sync-index-settings`; reindex command to document |
| Storage | **Needs one concrete decision** | `compose.prod.yaml`'s `web` (nginx) service has no storage volume while uploads will live in `laravel-storage`; avatars would 404 unless nginx mounts the volume or Laravel serves them. Decide in slice 8 (B-8). Not an architecture change, a wiring fix |
| Mail | Yes | SMTP is the universal path (B5); needs a dev/CI mail catcher service in `compose.dev.yaml` |
| Authentication | Yes | Add Socialite (new dependency) and password-broker views; Laravel-native reset tokens replace Auth0 |
| Image handling | Yes | GD is already in the CI PHP extension list; a client-side crop needs one small JS dependency — the rule "smallest client-side solution" applies; evaluate in slice 8 |
| Docker Compose | Yes | Add operator file, entrypoint and release workflow (phase O-1); these are missing deliverables, not redesign |
| Testing | Yes | Add a mail-catcher-backed E2E helper and a screenshot-baseline convention (B9 adopted) |

**Optional improvements (not needed to finish):** a rich health page via `spatie/laravel-health`
(adopted in principle, B3); `spatie/laravel-backup` tier 2 (register B2 defers); Horizon (no).
**Explicitly not recommended:** any change to the modular-monolith shape, a JS framework, a separate
service for mail or search, object storage in v1.

## 7. The finish line — Nusszopf Rewrite Definition of Done

The rewrite is finished — and `1.0.0` may be tagged — when **every** item below is true and the
maintainer has signed section 7.7. Anything marked *(A-n)* depends on that human decision.

### 7.1 Product parity

1. Every route in `docs/design/screens.md`'s route inventory exists, except deliberately dropped ones
   listed in `intentional-changes.md`/`bugs.md`, and every `docs/design/screen-specs.md` row (purpose,
   access, layout, components, data, actions, validation, all states, responsive behavior,
   authorization, side effects) is ticked with a linked test or screenshot.
2. Every workflow in `docs/domain/workflows.md` and every journey in `docs/journeys/README.md` has a
   passing Playwright journey, including Journey 5 (search), which historically never had one.
3. Every email in `docs/email/README.md` marked product-triggered is sent, rendered and verified;
   campaign mail is out of scope per A-6.
4. Every `docs/rewrite/bugs.md` entry has a final classification. Each **Fix** is implemented with its
   regression test; each **Preserve** is covered by a test that pins the behavior; **Unknown** count = 0.
5. `docs/rewrite/open-questions.md` contains no entry whose status is Unknown and unowned.
6. No "Intentional scaffolding" rows remain in any slice document.

### 7.2 Authorization, security, privacy

7. Every row of `docs/security/authorization-matrix.md` has an allow test and a deny test.
8. The security review (P-4) has no open High/Medium finding; residual risks are documented.
9. Private projects and their requests are unreachable and unindexed for non-owners (test + index audit).
10. No user email is exposed to another user on any surface.

### 7.3 Visual fidelity and quality

11. Screenshot baselines exist for every screen at phone/tablet/desktop; every diff has been either fixed
    or recorded as an accepted, documented difference.
12. Accessibility per A-7 verified; no known keyboard trap or unlabeled control on the journeys.
13. CI is green on: Pint, Larastan (level 7 or higher), Pest, asset build, Docker build (both targets),
    Playwright on Chromium, Firefox and WebKit **also against the production images**.

### 7.4 Operations

14. **Fresh install** on a clean host from the documentation alone, by someone who has not seen the code, succeeds.
15. **Upgrade** from the previous tag with populated data succeeds; rollback/restore statement verified.
16. **Backup and restore** drill reproduces database and uploads; **search index recovery** by one documented command.
17. Queue and scheduler survive dependency outages (Meilisearch, SMTP, Redis restart) with visible failure and recovery.
18. Health endpoint(s), logs, version exposure and configuration reference are documented and tested.
19. Mail verified end to end through a real SMTP relay; operator DNS prerequisites documented.

### 7.5 Documentation and repository

20. No `docs/` page still labelled Proposal; `docs/` has been stale-checked against the code.
21. LICENSE *(A-8)*, CONTRIBUTING, SECURITY, CODE_OF_CONDUCT, issue/PR templates, dependency/asset
    provenance and legal-page policy *(A-4, A-5)* are in place; no secrets in git history.
22. `CHANGELOG.md` is complete from the first entry; upgrade notes tagged **Breaking:**/**Migration required:**.

### 7.6 Release

23. Release workflow publishes images to the chosen registry *(A-10)* on tag; at least one
    release candidate has been installed and exercised by a second person with no blocking issue.

### 7.7 Sign-off

24. A parity report (one page per phase P-1…P-16, linking evidence) is committed under `docs/release/`
    and the maintainer explicitly signs it off *(A-9)*.

Anything found after sign-off is a bug against 1.x under the normal process, not a reason to reopen the rewrite.

## 8. Next steps

1. Approve or amend this roadmap and finish line (A-9). Every other human decision is made.
2. Reconcile the doc debts in §2.3 and B-1/B-2/B-3 as one small docs-only change.
3. Start slice 3 (no human decision needed), then proceed slice by slice. The only later human touchpoints are
   the `intentional-changes.md` approvals for A-1/A-3-driven behavior changes, the A-7 contrast decisions as
   they are measured, and the release-candidate re-read of the verbatim Home.
