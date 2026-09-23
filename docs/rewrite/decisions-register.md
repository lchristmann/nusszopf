# Decision Register

A concise index of what's settled and what genuinely still needs a human decision. Full context,
alternatives, and consequences for every "Requires human decision" item live in
`docs/rewrite/architecture-decisions.md` (architecture/process) and `docs/rewrite/bugs.md` (product
behavior corrections) — this page is the short list, not a replacement for that detail.

## Already decided

These are established and should not be silently re-litigated by a future contributor or AI agent:

- License: **GPL-3.0-or-later**, consistent with the historical repositories (all GPL v3.0); MIT was
  never valid because the rewrite reproduces GPL-licensed copy, templates and marks. Decided by the
  maintainer 2026-09-21. `LICENSE` is the verbatim GPL-3.0 text; `NOTICE` lists third-party components.
- Email verification (decided by the maintainer 2026-09-21; A-3): historical Nusszopf **never** enforced,
  displayed or used it (Confirmed: no `email_verified` use anywhere, no verification template, E2E logs in
  immediately). Nusszopf 2 keeps registration and login ungated but sends a verification email, and an
  unverified address cannot be published as the public "Persönlich" project contact nor be subscribed to
  the newsletter. This is a deliberate product change: it needs a `bugs.md` entry and an
  `intentional-changes.md` entry (Proposed → Approved) before slice 7 implements it, with regression tests.
  Google login must link accounts by email only when the provider asserts the address is verified.
- Newsletter consent (decided by the maintainer 2026-09-21; A-1; the rewrite must be GDPR-compliant):
  every subscription path (public form, registration checkbox, profile) creates a **pending** lead and
  sends the same double-opt-in confirmation email; only the link click confirms. The historical instant
  confirmation of the registration and profile paths is not reproduced (BUG-011 → Fix). Deleting an
  account also deletes the lead for the same address. Adopted without further ask: a consent record
  (requested-at, confirmed-at, path, consent-text version; no IP address), unconfirmed leads purged 14 days
  after creation by a scheduled job, a duplicate subscribe resends/answers neutrally instead of HTTP 500,
  and unsubscribe-by-email answers identically whether or not a lead exists. Recorded (BUG-011, BUG-032–034,
  `intentional-changes.md`) and implemented in slice 9 (2026-09-23).
- Newsletter scope (decided by the maintainer 2026-09-21; A-6): subscription and consent only. Historically
  the application only collected and confirmed subscribers and mirrored them to a SendGrid list; issues
  ("Nussig No. 1-3") and `support.mjml` were sent by hand from SendGrid (Inferred; no code or UI sends them).
  Nusszopf 2 adds no issue composer, sender or admin UI, and does not ship those templates. It provides a
  documented operator export of confirmed subscribers, and docs stating that any external sender must link
  to Nusszopf's own unsubscribe URL so the lead table stays the source of truth.
- Historical data import (decided by the maintainer 2026-09-21; A-2): **out of scope, no importer, no
  migration guide.** There is effectively no production data from the previous version, and none is
  committed to the historical repositories (empty `hasura/seeds`, no dumps). Nusszopf 2 starts fresh. No
  work item, slice or finish-line criterion exists for it; nothing needs to be designed around it.
- Browser and accessibility release bar (decided by the maintainer 2026-09-21; A-7). Historically neither
  was specified (Cypress at one 1440x800 desktop viewport, no browserslist, no a11y audit, no WCAG target).
  **Browsers**: latest two stable Chrome/Edge, Firefox and Safari on desktop, plus current iOS Safari and
  Android Chrome; no legacy browsers, no JavaScript-off support. CI gate = Chromium/Firefox/WebKit;
  visual baselines at 375/768/1440; a manual real-iPhone and real-Android smoke of the main journeys on
  each release candidate. **Accessibility**: release blocks on an axe scan of every reachable E2E screen and
  state with zero critical/serious violations (colour contrast excluded), full keyboard operability
  (dialogs/popovers trap focus, close on Esc, restore focus), correct German accessible names and
  error-to-field association, `lang="de"`, sensible headings, and a visible `:focus-visible` indicator
  (recorded as an intentional change, since the historical design removes outlines). Colour-contrast
  failures inherited from the historical palette are measured and documented, and each proposed fix is a
  separate bug entry the maintainer approves or waives; no palette change without that approval.
- Legal pages and branding (decided by the maintainer 2026-09-21; A-4/A-5):
  - **Rights**: confirmed. The Nusszopf name, logos, og-image and the historical German copy may be used
    (the maintainer is an original author or has their agreement).
  - **Legal pages** (Impressum, AGB, Datenschutz): the pages and footer/consent links exist as historically,
    but their text is operator-provided from configured files; until configured they show a clear
    "not configured" notice. The historical texts ship only as clearly labelled examples of the original
    operator, never as defaults. No legal text is written by the project. Datenschutz text must not name
    processors the rewrite does not use (Auth0, SendGrid, Visitor Analytics).
  - **Home content**: reproduced **verbatim**, including the Contest section and the sponsor/fellows logos.
    Known consequences, recorded so they are not rediscovered: the Contest text is dated (ceremony
    16.05.2022), and the sponsor row names Vercel, Auth0 and Sanity, which the rewrite does not use.
    This is the maintainer's explicit fidelity choice; revisit before release candidate if it reads as
    stale or inaccurate (phase P-15/P-16).
  - Adopted without further ask: the operator mailbox (`mail@nusszopf.org`) and operator identity become
    instance configuration rather than hard-coded values; third-party analytics (Visitor Analytics) is
    not reproduced (own server-side counter only, GDPR requirement); email logo and fonts are self-hosted
    (the historical templates load them from SendGrid's CDN and Google Fonts).
- Container registry and namespace (decided by the maintainer 2026-09-21; A-10): **GHCR**, published from
  GitHub Actions on a version tag, under the repository owner's personal namespace:
  `ghcr.io/lchristmann/nusszopf-php-fpm` and `ghcr.io/lchristmann/nusszopf-web`. The repository stays at
  `github.com/lchristmann/nusszopf`; a later move to an organization would change every operator's pull
  command and must be treated as a breaking change. Release-process requirements: set the packages public
  after the first push, add OCI `source`/`licenses` (GPL-3.0-or-later) labels, immutable version tag plus
  floating `latest`, multi-arch (amd64/arm64) if CI cost is reasonable. `compose.prod.yaml` names updated.
- Password policy: the five rules mirrored from the historical `SignUpForm` (the Auth0 tenant's own
  configuration is unrecoverable) are the adopted policy, flagged as "best available approximation", already
  implemented in `App\Rules\PasswordPolicy`. Recorded 2026-09-21; login throttling (B12) and the 8-hour
  session (B13) are unchanged.
- Single Git repository, one modular Laravel monolith — no microservices, no separate frontend
  deployment, no GraphQL layer (`docs/architecture/README.md`).
- FOSS, self-hostable via Docker Compose — no SaaS concepts (tenants, subscriptions, billing).
- Stack: PHP 8.5, Laravel 13, PostgreSQL, Blade, Livewire 4, Tailwind CSS 4, Redis, Meilisearch,
  Pest, Playwright, Laravel Pint, Larastan, Docker Compose, GitHub Actions (`CLAUDE.md`).
- Faithful revival: historical Nusszopf behavior is the baseline; deviations require the documented
  bug-fix protocol, not silent "improvement" (`CLAUDE.md`, `docs/rewrite/bugs.md`).
- Historical bugs are fixed deliberately and individually (`docs/rewrite/bugs.md`,
  `docs/rewrite/intentional-changes.md`) — nine are already fully spec'd (BUG-001 through BUG-009).
- LCxHolz is the engineering-quality/DX reference; Waffle Dashboard is the FOSS-lifecycle reference;
  official Laravel Docker examples are the Docker-implementation reference — none of the three are
  product/domain sources (`docs/references/README.md`).
- Semantic versioning, unprefixed tags (`1.2.0`, not `v1.2.0`), starting at `0.x.y` until the
  first vertical slice(s) reach historical parity (`docs/release/versioning.md`).
- `CHANGELOG.md` in Keep a Changelog format, `**Breaking:**`/`**Migration required:**` inline tags
  (`docs/release/changelog.md`).
- Release build/publish/tag automated via GitHub Actions, gated on the full quality-gate suite
  passing (`docs/rewrite/architecture-decisions.md`).
- Operators get a standalone, `image:`-only `docker-compose.yaml` (Waffle Dashboard's pattern),
  separate from the contributor-facing `compose.dev.yaml`/`compose.prod.yaml` pair
  (`docs/deployment/README.md`).
- Reverse proxy/TLS is documented as operator-owned (Nginx Proxy Manager primary, Caddy/Traefik as
  alternatives), never bundled into Nusszopf's own Compose files.
- Larastan level 7 as the starting static-analysis bar; coverage observed, not merge-gated
  (`docs/development/quality.md`).
- Nusszopf 2's application image builds its own Vite assets in its own multi-stage Dockerfile, and
  the nginx image is built `FROM` that exact application image tag (no shared asset volume) —
  Waffle Dashboard's pattern, chosen specifically to avoid a demonstrated defect in the Laravel
  Docker examples reference (`docs/references/laravel-docker-examples.md` §4).
- Dedicated `queue-worker` and `scheduler` Compose services (LCxHolz's pattern) — Nusszopf has real
  background work (search indexing, mail) that needs them, unlike two of the three references.

## Decision categories (pre-implementation review pass, 2026-09-18)

The 16 items previously listed as a flat "Requires human decision" list have been re-audited and
reclassified into exactly one of three categories, per the pre-implementation specification review
(`docs/rewrite/specification-review.md`, §7). The goal is to minimize decision overhead for the
human maintainer without letting Claude silently resolve a genuine product/security/foundational
question — see that document for the reasoning behind each reclassification.

- **A — Must decide before implementation**: a product, security, or foundational architecture
  decision that genuinely blocks work.
- **B — Claude may decide**: a technical implementation detail where a reasonable engineering
  choice does not alter the historical product. Claude has authority to choose the simplest
  defensible option (the recommendation already on file below) and proceed; the choice is recorded
  here as **Adopted**, not left open.
- **C — Defer**: safely postponable without affecting the first implementation slices.

### A — Must decide before implementation

| # | Question | Why it's genuinely A | Full detail |
|---|---|---|---|
| 1 | Newsletter opt-in asymmetry (BUG-011): should the signup-checkbox path also require double opt-in? | A real product/compliance-intent question (GDPR-style consent semantics), not resolvable from code or engineering judgment alone — the two plausible answers have materially different legal/UX consequences | `docs/rewrite/bugs.md` → BUG-011 |

### B — Claude may decide (adopted defaults)

| # | Question | Adopted | Why this is safely Claude's call |
|---|---|---|---|
| 1 | Container registry: Docker Hub vs. GHCR? | **GHCR** | Ties provenance to the repo, no extra credential in CI; purely an operational/CI choice, zero product impact. (Note: this is the one B-item worth a light human sanity check before the *first* release specifically, since changing registries later breaks every operator's pull command — not because the decision itself needs product judgment.) |
| 2 | Backup tier for v1: plain `pg_dump`+`tar`+cron, or `spatie/laravel-backup`-equivalent from day one? | **Simple tier first**, advanced tier documented as an upgrade path | Purely an ops/engineering maturity tradeoff, fully reversible later, no product-behavior implication |
| 3 | Health-check depth: bare Laravel `/up`, or a dependency-by-dependency status page? | **`spatie/laravel-health`-equivalent** | Small dependency, directly serves the self-hosting goal, no product impact |
| 4 | Object storage in v1: local disk (S3 as documented upgrade) or required from the start? | **Local disk in v1**, S3-compatible storage as a documented later upgrade | Reversible infrastructure choice; avatars are the only affected feature and their historical behavior is unaffected either way |
| 5 | Mail provider default: SMTP-only, or document specific transactional providers too? | **SMTP as the universal path**, common providers documented as options | Self-hosting operational concern, not a product decision — never hard-couple to a paid vendor |
| 6 | Rich-text editor replacement for Slate — which package/approach? | **Any Livewire-compatible package, configured down to exactly the confirmed six-tool historical toolbar** (bold/italic/underline, ordered/unordered list, link) — **implemented as TipTap in the second slice** | The product-visible capability ceiling is already Confirmed from evidence (`architecture-decisions.md`) — only the implementing package is left, a pure technical substitution with zero product-visible difference as long as the toolbar is configured down correctly |
| 7 | `Request` model naming — `App\Models\Request` vs. `App\Models\ProjectRequest`? | **`ProjectRequest`** | Avoids permanent import friction with `Illuminate\Http\Request`; no product-visible effect (German UI copy unaffected either way) |
| 8 | `ProjectAnalytics` — separate table/model, or columns directly on `Project`? | **Keep separate** | Preserves hot-write/content-write isolation; low-stakes and reversible via a later migration either way |
| 9 | Visual-parity testing mechanism — Playwright's built-in screenshot comparison, or a dedicated visual-regression tool? | **Playwright's built-in `toHaveScreenshot`** | Lowest setup cost, same suite, purely a tooling choice |
| 10 | Native `window.confirm()` for destructive actions (BUG-013): preserve, or upgrade to a styled dialog? | **Preserve as historically observed** | `CLAUDE.md`'s product-fidelity default already answers this absent contrary evidence: preserve unless demonstrably defective. The historical pattern is 100% consistent (never once uses the app's own `Dialog`), which is evidence of a deliberate-enough pattern to preserve, not evidence of a bug — and it is fully testable via Playwright's own `page.on('dialog', ...)`, so there's no engineering reason to deviate |
| 11 | Login return destination (BUG-015): always `/user/projects`, or return to the referring context? | **Preserve as historically observed** | Same reasoning as above — no evidence of unintended behavior, product-fidelity default applies directly |
| 12 | Auth: IP-block threshold for the `throttle`-middleware equivalent | **A documented, reasonable default (e.g. 5 attempts/minute)** | Auth0's actual threshold is unrecoverable from any available evidence (platform config, not in-repo) — there is no historical value to match, so any reasonable, documented default is an equally valid engineering judgment call, not a product decision |
| 13 | Auth: preserve the 8-hour rolling session duration? | **Preserve as the default** | No evidence it's arbitrary and no reason to change it without one; already the `docs/rewrite/open-questions.md` recommendation — this entry only formalizes moving it out of "open" |

### C — Defer

| # | Question | Why deferred | Full detail |
|---|---|---|---|
| 1 | Who writes changelog entries: PR author (CI-enforced) or maintainer at release time? | Explicitly revisit-once-there-are-more-contributors; does not affect the first implementation slices at all | `architecture-decisions.md` → "Who writes changelog entries" |
| 2 | Auth: breached-password-check scope (Auth0's breached-password detection feature) | An optional enhancement beyond historical parity (nothing user-facing beyond one email template depended on it uniquely) — not required for the first slices, and can be added later without rework | `docs/authentication/README.md` §7–8 |

### Resolved (moved out of the decision register)

| # | Question | Resolution | Full detail |
|---|---|---|---|
| 1 | `ProjectAnalytics.contactRequests` (BUG-017): confirm real usage before deciding whether to keep an equivalent field | **Resolved, Confirmed dead** — `ContactDialog.js`'s submit handler was read in full and contains no GraphQL mutation of any kind. Nusszopf 2 does not reproduce this field. | `docs/rewrite/bugs.md` → BUG-017 |

## Explicitly not open (do not re-ask)

- Whether to reproduce Apple social login (BUG-012): **no** — it was never functional historically.
- Whether to reproduce the `auth-login`/`auth-password`/`webapp` three-app split: **no** — purely
  Auth0-hosting plumbing, not a product requirement.
- Whether Nusszopf needs an admin/staff role: **no** — confirmed absent from the entire historical
  system; do not add one without new, explicit, approved evidence.
