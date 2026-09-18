# Decision Register

A concise index of what's settled and what genuinely still needs a human decision. Full context,
alternatives, and consequences for every "Requires human decision" item live in
`docs/rewrite/architecture-decisions.md` (architecture/process) and `docs/rewrite/bugs.md` (product
behavior corrections) — this page is the short list, not a replacement for that detail.

## Already decided

These are established and should not be silently re-litigated by a future contributor or AI agent:

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

## Requires human decision

Only genuinely open items — see the linked entry for full context before deciding.

| # | Question | Recommendation | Full detail |
|---|---|---|---|
| 1 | Container registry: Docker Hub vs. GHCR? | GHCR (ties provenance to the repo, no extra credential in CI) | `architecture-decisions.md` → "Container registry" |
| 2 | Who writes changelog entries: PR author (CI-enforced) or maintainer at release time? | Maintainer-curated for now (single-maintainer reality); revisit with more contributors | `architecture-decisions.md` → "Who writes changelog entries" |
| 3 | Backup tier for v1: plain `pg_dump`+`tar`+cron, or `spatie/laravel-backup`-equivalent from day one? | Start with the simple tier, document the advanced tier as an upgrade path | `architecture-decisions.md` → "Backup tier for the first release" |
| 4 | Health-check depth: bare Laravel `/up`, or a dependency-by-dependency status page? | `spatie/laravel-health`-equivalent — small dependency, directly serves the self-hosting goal | `architecture-decisions.md` → "Health-check depth" |
| 5 | Object storage in v1: local disk (S3 as documented upgrade) or required from the start? | Local disk in v1, S3-compatible storage as a documented later upgrade | `architecture-decisions.md` → "Object storage" |
| 6 | Mail provider default: SMTP-only, or document specific transactional providers too? | Document SMTP as the universal path, list common providers as options — never hard-couple to SendGrid | `architecture-decisions.md` → "Mail provider default recommendation" |
| 7 | Rich-text editor replacement for Slate — which package/approach? | A Livewire-compatible package configured down to exactly bold/italic/underline/lists/link (the confirmed historical toolbar) | `architecture-decisions.md` → "Rich-text editor replacement" |
| 8 | `Request` model naming — `App\Models\Request` (clashes with `Illuminate\Http\Request`) or `App\Models\ProjectRequest`? | `ProjectRequest` — avoids permanent import friction; no product-visible effect (German UI copy unaffected) | `architecture-decisions.md` → "`Request` model naming" |
| 9 | `ProjectAnalytics` — separate table/model, or columns directly on `Project`? | Keep separate (preserves hot-write/content-write isolation, low-stakes either way) | `architecture-decisions.md` → "`ProjectAnalytics` as its own model" |
| 10 | Visual-parity testing mechanism — Playwright's built-in screenshot comparison, or a dedicated visual-regression tool? | Playwright's built-in `toHaveScreenshot` — lowest setup cost, same suite | `architecture-decisions.md` → "Visual parity testing mechanism" |
| 11 | Newsletter opt-in asymmetry (BUG-011): should the signup-checkbox path also require double opt-in? | Preserve as historically observed by default (product-fidelity default); revisit only if GDPR-compliance review says otherwise | `docs/rewrite/bugs.md` → BUG-011 |
| 12 | Native `window.confirm()` for destructive actions (BUG-013): preserve, or upgrade to a styled dialog? | Preserve as historically observed by default | `docs/rewrite/bugs.md` → BUG-013 |
| 13 | Login return destination (BUG-015): always `/user/projects`, or return to the referring context? | Preserve as historically observed by default | `docs/rewrite/bugs.md` → BUG-015 |
| 14 | `ProjectAnalytics.contactRequests` (BUG-017): confirm real usage before deciding whether to keep an equivalent field | Investigate `ContactDialog.js` before implementation; default to keeping the field if intent is clear even if wiring is unconfirmed | `docs/rewrite/bugs.md` → BUG-017 |
| 15 | Auth: IP-block thresholds / breached-password-check scope — both were Auth0 platform features with no in-repo configuration | Laravel `throttle` middleware with a documented, reasonable default (e.g. 5 attempts/minute) for IP-block-equivalent; breached-password check is optional, not required for parity | `docs/authentication/README.md` §7–8 |
| 16 | Auth: preserve the 8-hour rolling session duration? | Preserve as the default; no evidence it's arbitrary, no reason to change it without one | `docs/rewrite/open-questions.md` → "8-hour rolling session duration" |

## Explicitly not open (do not re-ask)

- Whether to reproduce Apple social login (BUG-012): **no** — it was never functional historically.
- Whether to reproduce the `auth-login`/`auth-password`/`webapp` three-app split: **no** — purely
  Auth0-hosting plumbing, not a product requirement.
- Whether Nusszopf needs an admin/staff role: **no** — confirmed absent from the entire historical
  system; do not add one without new, explicit, approved evidence.
