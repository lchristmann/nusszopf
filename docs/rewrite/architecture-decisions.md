# Architecture Decisions

Decisions about how Nusszopf 2 itself should work — as distinct from `docs/rewrite/open-questions.md` (what the historical product actually did) and `docs/rewrite/intentional-changes.md` (deliberate historical-behavior corrections). Every entry below is **undecided** unless its Status says otherwise. None should be treated as settled by virtue of appearing in this document with a recommendation attached.

---

### Versioning scheme and tag format

- Status: **Decided** (reconciled, pre-implementation review pass 2026-09-18 — this item was already listed as "Already decided" in `docs/rewrite/decisions-register.md`; this Status field was inconsistently still marked "Proposed" and has been corrected to match. See specification-review.md, "Specification consistency" findings.)
- Date: 2026-09-18
- Context: Nusszopf 2 needs a versioning scheme from its first release. Waffle Dashboard (`docs/references/waffle-dashboard.md`) uses unprefixed semver tags and cuts `1.x` releases immediately from a single-maintainer, always-"done-enough" posture.
- Requirement: self-hosting operators need to reason about upgrade risk from the version number alone (`.claude/rules/06-self-hosting.md`).
- Historical evidence: none — historical Nusszopf had no public release/versioning process (it was deployed continuously to Vercel/Heroku, not distributed).
- Reference-project evidence: Waffle Dashboard — unprefixed semver, no version constant in code, version exists only as the Git tag/image tag/Release title.
- Decision: adopt semver with **no `v` prefix** on tags (`1.2.0`, not `v1.2.0`), matching Waffle Dashboard. Start at `0.x.y` while the rewrite has not yet reached historical product parity, cutting `1.0.0` only once the recommended first vertical slice (and subsequent slices) have reached parity for the features they cover — unlike Waffle Dashboard, which was never trying to match a historical baseline.
- Alternatives: `v`-prefixed tags (more common convention, but inconsistent with the FOSS-lifecycle reference this project explicitly follows); jumping straight to `1.0.0`.
- Consequences: `0.x` communicates "not yet at parity" honestly to early self-hosters; requires discipline to actually cut `1.0.0` once parity is real rather than drifting in `0.x` forever.
- Revisit conditions: none expected before the first vertical slice ships.

---

### Version exposure to operators

- Status: Proposed
- Date: 2026-09-18
- Context: unlike Waffle Dashboard (no version constant anywhere in code), Nusszopf's self-hosting operators need to know what they're running without inspecting image digests.
- Requirement: `.claude/rules/06-self-hosting.md` — practical operation without deep source knowledge.
- Historical evidence: none.
- Reference-project evidence: neither reference project exposes a runtime "About/version" surface; this is a deliberate departure from both.
- Decision: bake the Git tag into the Docker image at build time (build arg → a file the app reads, or an OCI image label), and surface it via an in-app health/about endpoint plus `docker inspect`. No hand-maintained `VERSION` file.
- Alternatives: no version exposure (matches both references, rejected — self-hosting operators specifically need this); a hand-maintained `VERSION` file (rejected — drifts from the actual tag).
- Consequences: requires a small CI step to inject the tag at build time.
- Revisit conditions: none.

---

### Changelog format and maintenance

- Status: **Decided** (reconciled, pre-implementation review pass 2026-09-18 — already listed as "Already decided" in `docs/rewrite/decisions-register.md`; Status field corrected to match.)
- Date: 2026-09-18
- Context: Waffle Dashboard has no `CHANGELOG.md` at all — its only change record is release-commit messages and hand-written GitHub Release notes.
- Requirement: `.claude/rules/06-self-hosting.md` — operators upgrading across versions need a scannable, pre-upgrade-readable history.
- Historical evidence: none.
- Reference-project evidence: Waffle Dashboard — no changelog file; LCxHolz — not yet confirmed whether it maintains one (`docs/references/lcxholz.md` doesn't record this; needs a follow-up check before finalizing).
- Decision: maintain `CHANGELOG.md` in Keep a Changelog format, with an `Unreleased` section, categories (Added/Changed/Fixed/Security/Deprecated/Removed), and explicit `**Breaking:**`/`**Migration required:**` inline tags for operator-impacting entries. This is a deliberate improvement over both references, not a port.
- Alternatives: no changelog file (Waffle Dashboard's approach — rejected, insufficient for third-party operators); auto-generated changelog from commit messages only (rejected — requires strict commit discipline this project hasn't committed to).
- Consequences: requires either PR-author discipline or maintainer curation at release time (see next decision).
- Revisit conditions: none.

---

### Who writes changelog entries — PR author or release-time curation

- Status: Undecided — **deferred** (Category C, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`). Does not block the first implementation slices; revisit once there is more than one regular contributor.
- Date: 2026-09-18
- Context: follows directly from the changelog decision above.
- Requirement: `docs/release/changelog.md`'s open question.
- Historical evidence: none.
- Reference-project evidence: neither reference resolves this (Waffle Dashboard has no changelog to derive a convention from).
- Decision: **not yet made.**
- Alternatives: (a) CI-enforced "PR must touch `CHANGELOG.md`" check; (b) maintainer curates the `Unreleased` section from merged PR titles at release time.
- Consequences: (a) adds friction per-PR but keeps the changelog current continuously; (b) is lower-friction per-PR but risks the changelog falling behind between releases.
- Revisit conditions: revisit once there's more than one regular contributor — a single maintainer can reasonably do (b) alone.

---

### Container registry

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: Docker images need a home. Waffle Dashboard publishes to Docker Hub under a personal namespace.
- Requirement: `.claude/rules/06-self-hosting.md` — operators need a documented, reliable place to pull images from.
- Historical evidence: none.
- Reference-project evidence: Waffle Dashboard — Docker Hub, personal namespace, immutable version tag + floating convenience tag per image (two images: PHP-FPM + web-server).
- Decision: **GHCR** — ties image provenance directly to the GitHub repo, no separate registry credential to manage in CI, consistent with the already-decided GitHub-Actions-driven release automation. Purely an operational choice with no product impact; a light human sanity check before the first tagged release is still worthwhile only because switching registries later breaks every operator's pull command, not because the choice itself needs product judgment.
- Alternatives: (a) Docker Hub, matching Waffle Dashboard; (b) GitHub Container Registry (`ghcr.io`) — ties image provenance directly to the GitHub repo, no separate registry credential needed in CI, common for GitHub-Actions-driven FOSS projects.
- Consequences: (b) is generally the more standard choice for GitHub-Actions-native release automation (the direction Nusszopf is already choosing over Waffle Dashboard's manual process, see next decision) but has less name recognition for casual self-hosters used to `docker pull` from Docker Hub by default.
- Revisit conditions: none — pick once, before the first tagged release, since changing registries later breaks every existing operator's pull command.

---

### Release build/publish automation

- Status: **Decided** (reconciled, pre-implementation review pass 2026-09-18 — already listed as "Already decided" in `docs/rewrite/decisions-register.md`; Status field corrected to match.)
- Date: 2026-09-18
- Context: Waffle Dashboard's entire release process is manual (maintainer builds, tags, pushes, and writes GitHub Release notes by hand) with no CI/CD gating any of it.
- Requirement: `CLAUDE.md` / `.claude/rules/05-engineering-quality.md` — LCxHolz-level engineering discipline, which this manual process does not meet.
- Historical evidence: none.
- Reference-project evidence: Waffle Dashboard (what to go beyond); LCxHolz (`docs/references/lcxholz.md`) for what CI-driven automation should look like generally.
- Decision: automate build/push/tag/GitHub-Release-creation via GitHub Actions, triggered on pushing a version tag, running the full quality-gate suite (`docs/development/quality.md`) before any image is published.
- Alternatives: keep it manual like Waffle Dashboard (rejected — explicitly a case where Nusszopf should exceed the reference, per `CLAUDE.md`).
- Consequences: more CI complexity to build and maintain; removes human error from the release step and guarantees released images actually pass the quality gates.
- Revisit conditions: none.

---

### Backup tier for the first release

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: `docs/deployment/operations.md` demonstrates two backup tiers from the reference projects (evidence there; not duplicated here).
- Requirement: `.claude/rules/06-self-hosting.md` — "back it up" is an explicit required operator capability.
- Historical evidence: none (historical Nusszopf, being Vercel/Heroku-hosted, relied on those platforms' own backup posture, which doesn't translate to self-hosting).
- Reference-project evidence: see `docs/deployment/operations.md`/`docs/references/waffle-dashboard.md`/`docs/references/lcxholz.md` for the two demonstrated tiers.
- Decision: **Tier 1 (Waffle Dashboard's `pg_dump`+`tar`+cron) for the first release**, with Tier 2 (`spatie/laravel-backup`-equivalent) documented explicitly as a later upgrade path rather than built from day one. Purely an ops-maturity tradeoff with no product-behavior implication either way, and reversible — adopting the advanced tier later doesn't require undoing anything about the simple tier's data.
- Alternatives: see `docs/deployment/operations.md`.
- Consequences: the simpler tier ships faster but may under-serve operators with larger datasets; the more advanced tier takes longer to document/script but is more production-appropriate.
- Revisit conditions: can start with the simpler tier and document the advanced one as a documented upgrade path, if that's judged acceptable for the first vertical slice's scope.

---

### Health-check depth: bare Laravel `/up` vs. a richer status page

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: LCxHolz uses `spatie/laravel-health` for a richer status page beyond Laravel's bare built-in `/up` route.
- Requirement: `.claude/rules/06-self-hosting.md` — "verify it's healthy."
- Historical evidence: none.
- Reference-project evidence: `docs/references/lcxholz.md` (confirmed `spatie/laravel-health` usage pattern).
- Decision: **`spatie/laravel-health`-equivalent** — a small dependency that directly serves the self-hosting operator-experience goal (a dependency-by-dependency status view — DB, Redis, Meilisearch, queue — is materially more useful for a self-hosting operator diagnosing "why is search broken" than a bare 200 OK). No product impact either way.
- Alternatives: (a) bare `/up`, sufficient for container orchestration health checks; (b) `spatie/laravel-health`, giving operators a real dependency-by-dependency status view (DB, Redis, Meilisearch, queue) — more useful for a self-hosting operator diagnosing "why is search broken" than a bare 200 OK.
- Consequences: (b) is a small dependency addition but directly serves the self-hosting operator-experience goal better than (a).
- Revisit conditions: none — should be decided before `docs/deployment/README.md`'s health-check section is finalized as non-proposal.

---

### Compose file naming and split (operator-facing vs. developer-facing)

- Status: **Decided** (reconciled, pre-implementation review pass 2026-09-18 — already listed as "Already decided" in `docs/rewrite/decisions-register.md`; Status field corrected to match.)
- Date: 2026-09-18
- Context: LCxHolz has no separate operator-facing Compose file (it's not distributed to third-party self-hosters); Waffle Dashboard does, since it is.
- Requirement: Nusszopf is explicitly FOSS and third-party self-hosted, closer to Waffle Dashboard's situation than LCxHolz's.
- Historical evidence: none.
- Reference-project evidence: `docs/references/waffle-dashboard.md`, `docs/references/laravel-docker-examples.md`.
- Decision: follow Waffle Dashboard's naming — a root-level `docker-compose.yaml` that is `image:`-only (no build context, pinned to a released version tag) for operators to download standalone, distinct from `compose.dev.yaml`/`compose.prod.yaml` used inside the repository for development and for building the images that get published.
- Alternatives: LCxHolz's single `compose.prod.yaml` with no separate operator artifact (rejected — doesn't fit Nusszopf's third-party-operator audience).
- Consequences: one more file to keep in sync with each release (the operator-facing compose file's image tags must be bumped per release, ideally automated as part of the release-automation decision above).
- Revisit conditions: none.

---

### Required environment variable list (exact set)

- Status: **Decided** — finalized during the first vertical slice's implementation (2026-09-18); see `.env.example` and `docs/deployment/README.md`'s "Required configuration".
- Date: 2026-09-18
- Context: `docs/deployment/README.md` has a draft minimum `.env` shape but marks the exact list "(needs approval — exact variable list)."
- Requirement: `.claude/rules/06-self-hosting.md` — "a small number of environment variables."
- Historical evidence: `be-nusszopf/docker-compose.yml`, `.env.example` files across all three reference projects establish the shape of what's typically needed (DB, Redis, Meilisearch, app key, mail, storage credentials) but not Nusszopf's own final list, which depends on decisions still pending elsewhere in this document (object storage in v1? which mail provider default? Scout/Meilisearch key handling?).
- Reference-project evidence: see `docs/deployment/README.md`.
- Decision: **not yet made** — but its two blockers are now resolved (object storage: local disk in v1; mail provider: SMTP-universal with documented provider options — both Adopted below), so the exact variable list can be finalized as an ordinary implementation task rather than a standing open decision. No further human input is needed to unblock this.
- Alternatives: n/a yet.
- Consequences: n/a yet.
- Revisit conditions: none — ready to finalize during implementation of the deployment/Docker foundation.

---

### Reverse proxy / TLS termination

- Status: **Decided** (reconciled, pre-implementation review pass 2026-09-18 — already listed as "Already decided" in `docs/rewrite/decisions-register.md`; Status field corrected to match.)
- Date: 2026-09-18
- Context: both LCxHolz and Waffle Dashboard document Nginx Proxy Manager as an external, pre-existing/operator-installed TLS-terminating layer, reached over a shared Docker network, rather than bundling a reverse proxy into their own Compose file.
- Requirement: `.claude/rules/06-self-hosting.md` — practical for an operator who has never seen the source, but also "avoid unnecessary operational dependencies."
- Historical evidence: none (historical Nusszopf used Vercel's built-in TLS).
- Reference-project evidence: `docs/references/lcxholz.md`, `docs/references/waffle-dashboard.md`.
- Decision: document the shared-external-network + Nginx Proxy Manager pattern as the primary documented option, with Caddy/Traefik documented as alternatives, without bundling any reverse proxy into Nusszopf's own Compose files — matching both references.
- Alternatives: bundle a reverse proxy (e.g. Caddy) directly in `docker-compose.yaml` for a truly one-command experience (rejected as a default — most self-hosters running multiple services already have a reverse-proxy layer they don't want a second one competing with; can be offered as an optional "single-app, no existing proxy" alternate compose profile instead if demand emerges).
- Consequences: operators without an existing reverse proxy need to set one up themselves first, following documentation — a real (if standard, well-precedented) onboarding step.
- Revisit conditions: reconsider if user feedback shows this is a significant onboarding blocker.

---

### Object storage: required for v1 or deferred

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: historical Nusszopf used DigitalOcean Spaces for avatar images. `docs/architecture/mapping.md` flags this as a decision: is S3-compatible object storage a first-vertical-slice requirement, or can avatars start on local disk with S3 as a documented later upgrade?
- Requirement: self-hosting simplicity (`.claude/rules/06-self-hosting.md`) vs. product completeness (avatars are a confirmed historical feature, `docs/domain/entities.md`'s `User.picture`).
- Historical evidence: `docs/domain/entities.md`, `docs/domain/workflows.md` ("Workflow: profile picture replacement").
- Reference-project evidence: not yet checked against LCxHolz/Waffle Dashboard's own object-storage approach — needs a follow-up read before this decision is finalized.
- Decision: **(a) local disk storage for avatars in v1**, S3-compatible storage as a documented, optional upgrade. Reduces v1's required environment variables and container count (serves the "small number of environment variables" self-hosting goal); avatars are the only affected historical feature and their observable behavior is identical either way. Operators who need S3 from day one can still configure it via Laravel's filesystem abstraction without a code change — this is a default, not a hard architectural constraint.
- Alternatives: (a) local disk storage for avatars in v1, S3-compatible storage as a documented, optional upgrade; (b) require S3-compatible storage (even a bundled MinIO container) from v1.
- Consequences: (a) reduces v1's required environment variables and container count, aligning with the "small number of environment variables" self-hosting goal, at the cost of avatars not surviving a naive container-only backup/restore unless the app's storage volume is included; (b) is more production-correct immediately but adds setup burden for the smallest self-hosting operators.
- Revisit conditions: revisit once `docs/deployment/README.md`'s backup-tier decision is made, since the answer there affects whether local-disk avatar storage is actually safe to recommend as a default.

---

### Mail provider default recommendation

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: historical Nusszopf hard-coupled to SendGrid for transactional/marketing email.
- Requirement: `.claude/rules/06-self-hosting.md` — "configure mail... where applicable," without assuming a specific paid third-party service.
- Historical evidence: `docs/email/README.md`.
- Reference-project evidence: not yet checked against LCxHolz's/Waffle Dashboard's own mail-provider documentation — needs a follow-up read before finalizing.
- Decision: document Laravel's standard mail-driver configuration (SMTP as the universal fallback, with common transactional-provider drivers as documented options) rather than hard-coupling setup instructions to SendGrid specifically.
- Alternatives: keep SendGrid as the documented default (rejected as the *only* documented path — self-hosters should not be forced into one paid vendor); support only SMTP (simplest, but loses provider-specific features like unsubscribe-list management that the historical product relied on for the newsletter — see the Newsletter-sync intentional change).
- Consequences: the newsletter list-sync mechanic (`docs/domain/workflows.md`'s `sync_leads_sendgrid`) needs its own explicit decision about what (if anything) replaces "sync to an external marketing list" when SendGrid isn't assumed — not yet made, follow-up needed.
- Revisit conditions: revisit once the newsletter-sync replacement mechanism is decided.

---

### Rich-text editor replacement for Slate

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: historical `description`/`team` fields use `slate`/`slate-react`, with a known, documented upstream bug (`docs/rewrite/open-questions.md`).
- Requirement: Livewire-compatible, per `CLAUDE.md`'s "smallest client-side solution" instruction; must still support whatever rich-text capability the historical editor actually offered.
- Historical evidence: `docs/design/screens.md`, `docs/design/components.md`. **Toolbar capability now Confirmed** (`RichTextEditor.organism.js`): exactly six tools — `MarkButton`s for **bold**, *italic*, and underline (character-level marks); `BlockButton`s for unordered list and ordered list; one `LinkButton`. That is the complete toolbar — **no headings, no blockquote, no code block, no text alignment, no color, no tables, no image embedding**. This is a deliberately minimal rich-text capability, not a full word-processor-style editor, and the replacement should match this exact capability ceiling rather than either under- or over-shooting it (e.g. adding a heading tool would be new functionality beyond what the historical product offered, which needs its own approval, not a default "since we're replacing the editor anyway" upgrade).
- Reference-project evidence: not yet checked — neither reference project's editor choice (if any) has been surveyed.
- Decision: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`). **Any Livewire-compatible rich-text package supporting exactly bold/italic/underline/lists/link, explicitly configured down to that six-tool toolbar** — not used with its own defaults. The blocking unknown (toolbar capability) was already resolved with hard evidence; the specific package is a pure technical substitution with zero product-visible difference as long as the toolbar match is enforced, so it does not need to block implementation. A Markdown-based editor remains explicitly rejected as the default (it would be a product simplification requiring its own approval, not a neutral technical swap) unless separately proposed and approved later.
- Alternatives: a Livewire-native rich-text package supporting exactly bold/italic/underline/lists/link (avoids taking on unused capability); a deliberately simpler Markdown-based editor (would be a product simplification requiring explicit approval, not a neutral technical swap, since it changes what content authors can express — though notably a minimal Markdown toolbar could cover this exact feature set almost 1:1).
- Consequences: because the required capability is so narrow, most modern Livewire-compatible rich-text packages will over-provide (a common risk: shipping a heavier toolbar than the product ever had, which is itself a drift from product fidelity) — whichever package is chosen should have its toolbar explicitly configured down to this six-tool set, not used with its own defaults.
- Revisit conditions: none.

---

### `Request` model naming (avoid clashing with `Illuminate\Http\Request`)

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: the historical domain entity is literally named "Request" (a Gesuch/ask attached to a project), which collides with Laravel's own ubiquitous `Illuminate\Http\Request` class.
- Requirement: engineering-quality clarity (`.claude/rules/05-engineering-quality.md`) — ambiguous imports/autocomplete collisions are a real maintainability cost, but per `CLAUDE.md` domain naming should reflect the reconstructed domain, not arbitrary technical convenience.
- Historical evidence: `docs/domain/entities.md`.
- Reference-project evidence: n/a (naming convention, not a behavior).
- Decision: **(b) `App\Models\ProjectRequest`** — mirrors the entity's exclusive parent relationship (a `Request` never exists without a `Project`), avoids permanent import friction with `Illuminate\Http\Request`, and has no product-visible effect since German UI copy never references the class/table name.
- Alternatives: (a) `App\Models\Request`, fully qualifying every usage of Laravel's HTTP request class instead (viable but adds friction everywhere, forever); (b) `App\Models\ProjectRequest` (mirrors the entity's exclusive parent relationship, per `docs/domain/relationships.md` — a `Request` never exists without a `Project`) — deviates from the exact historical name but is an implementation-naming choice, not a product/domain behavior change, and doesn't need to appear in user-facing copy (German UI copy is unaffected either way).
- Consequences: (b) is very likely the pragmatic choice but should be recorded as a deliberate decision rather than picked silently mid-implementation.
- Revisit conditions: none — decide once, before the first migration/model is written, to avoid a rename later.

---

### `ProjectAnalytics` as its own model/table vs. columns on `Project`

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: historically a separate 1:1 extension table, created in a later migration than `Project` itself for schema-evolution reasons that don't apply to a fresh Nusszopf 2 schema (`docs/domain/entities.md`).
- Requirement: n/a — purely an implementation-shape choice, no product-behavior implication either way.
- Historical evidence: `docs/domain/entities.md`, `docs/domain/relationships.md`.
- Reference-project evidence: n/a.
- Decision: **(a) keep it a separate table/model** — preserves hot-write/content-write physical isolation (a real Postgres performance consideration), and is low-stakes/reversible via a later migration if it turns out not to matter. Note: `contactRequests` itself is not reproduced at all — see BUG-017's resolution (`docs/rewrite/bugs.md`) — so this model has a `views` counter only.
- Alternatives: (a) keep it a separate table/model (matches history exactly, keeps hot-write counter columns physically separate from the main `projects` row, which can be a real Postgres performance consideration if `projects` rows are updated less often than their view counts); (b) merge `views`/`contactRequests` directly onto `Project` (simpler domain model, one fewer relationship to reason about).
- Consequences: (a) preserves a real (if originally accidental) performance-isolation property; (b) is simpler but couples frequent counter writes to the same row as infrequent content edits.
- Revisit conditions: none — low-stakes, reversible via a later migration if wrong.

---

### Static analysis level and coverage policy

- Status: **Decided** (reconciled, pre-implementation review pass 2026-09-18 — already listed as "Already decided" in `docs/rewrite/decisions-register.md`; Status field corrected to match.)
- Date: 2026-09-18
- Context: LCxHolz runs Larastan at level 7 and does not hard-gate merges on a coverage percentage.
- Requirement: `.claude/rules/05-engineering-quality.md` — LCxHolz is the explicit engineering-quality reference.
- Historical evidence: n/a.
- Reference-project evidence: `docs/references/lcxholz.md`, `docs/development/quality.md`.
- Decision: adopt Larastan level 7 as the starting default; observe coverage (reported, not merge-blocking) rather than enforcing an arbitrary threshold — both directly matching LCxHolz.
- Alternatives: a stricter level 8/9 (rejected as a default — would need a specific, documented reason per `docs/development/quality.md`, not adopted preemptively); a hard coverage gate (rejected — matches neither reference and risks incentivizing low-value tests written purely to hit a number).
- Consequences: either can be tightened later with a documented, specific reason.
- Revisit conditions: revisit if either project's actual level/policy is later found to differ from what's recorded in `docs/references/lcxholz.md` (should be re-verified, not assumed permanent).

---

### Visual parity testing mechanism

- Status: **Adopted** (Category B — Claude decision, pre-implementation review pass 2026-09-18; see `docs/rewrite/decisions-register.md`)
- Date: 2026-09-18
- Context: visual fidelity is a hard requirement (`CLAUDE.md`, `.claude/rules/02-visual-fidelity.md`); `docs/testing/README.md` flags that *how* this gets verified automatically (screenshot-diff tool, baseline management, pixel/threshold tolerance) is still open.
- Requirement: automated verification that screens actually match the historical design, not just that they render without error.
- Historical evidence: n/a (this is about the new test suite, not historical behavior).
- Reference-project evidence: not yet checked whether LCxHolz uses Playwright's built-in screenshot comparison or a dedicated visual-regression tool — needs a follow-up read.
- Decision: **Playwright's built-in `toHaveScreenshot`** — lowest setup cost, ties directly into the same E2E suite already adopted, no new infrastructure. Purely a tooling choice with no product impact.
- Alternatives: Playwright's built-in `toHaveScreenshot` (lowest setup cost, ties directly into the same E2E suite); a dedicated visual-regression service/tool (more powerful diffing/review UX, more infrastructure).
- Consequences: none beyond the tool choice itself.
- Revisit conditions: revisit only if `toHaveScreenshot`'s baseline-management ergonomics prove insufficient once the test suite exists.
