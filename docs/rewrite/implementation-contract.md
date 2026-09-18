# Implementation Contract

This is the concise contract every implementation agent must follow, from the first vertical slice
onward. It summarizes rules stated at length elsewhere (`CLAUDE.md`, `.claude/rules/`,
`docs/rewrite/specification-review.md`) into a form short enough to actually be read before every
change. When this document and a longer source document disagree, the longer document governs —
this is a summary, not a replacement.

## Product rule

**Nusszopf is a faithful revival, not a redesign.** The historical Nusszopf application defines
product behavior; the new stack defines implementation only. If a change makes the product simpler,
prettier, or more "modern" than the historical product without being a demonstrated bug fix, it is
out of scope, however small.

## Source-of-truth rule

Historical evidence beats inference beats convenience. Priority order for any product/UI/domain
question: (1) E2E tests and observable behavior, (2) actual frontend implementation, (3) historical
UI library, (4) frontend queries/mutations, (5) backend/domain logic, (6) GraphQL/Hasura metadata,
(7) search configuration, (8) email templates, (9) documentation, (10) inference — inference is last
resort, always labeled as such, never silently promoted to fact. Before implementing any feature,
read the relevant `docs/<topic>/` file and, if it cites a specific historical file not yet read,
read that file — do not implement from a doc summary alone if the doc says the underlying evidence
is still Unknown/Inferred.

## Change rule (Preserve / Fix / Replace / Unknown)

Every suspected historical defect is classified in `docs/rewrite/bugs.md` **before** it is touched:

- **Fix** — demonstrably defective. The corrected behavior, evidence, and required regression test
  must already be recorded in `docs/rewrite/intentional-changes.md` before you implement it.
- **Preserve** — historical behavior stands, including behavior that looks surprising but has no
  evidence of being unintended. Surprising is not the same as broken.
- **Unknown** — cannot be classified without a human product decision. Recorded in
  `docs/rewrite/open-questions.md`. **Never resolve one of these by guessing** — either do the
  recommended investigation against `../historical/` yourself and update the entry with real
  evidence, or escalate to the user. A guess dressed up as an investigation is the single most
  damaging failure mode for this project.
- **Replace** — obsolete infrastructure only; product behavior is unchanged. See
  `docs/architecture/mapping.md`.

Do not fix a suspected bug with no `docs/rewrite/bugs.md`/`intentional-changes.md` entry yet — add
the entry first (as Proposed), then implement.

## Architecture rule

One Git repository, one modular Laravel monolith (PHP 8.5, Laravel 13, PostgreSQL, Blade, Livewire
4, Tailwind CSS 4, Redis, Meilisearch, Pest, Playwright, Laravel Pint, Larastan, Docker Compose,
GitHub Actions). No microservices, no separate frontend deployment, no GraphQL layer, no SaaS
control plane. Eloquent + Laravel Policies replace Hasura's permission model directly — preserve the
*allow/deny matrix* (`docs/security/authorization-matrix.md`), not the historical mechanism.
Historical event-trigger webhooks become Eloquent model events + queued jobs. Do not add a
repository layer, service layer, or interface where a Policy method, a model scope, or a plain
Eloquent call already expresses the rule completely — see
`docs/rewrite/specification-review.md` §16 for the standing "no unnecessary abstraction" bar.
Module boundaries: Projects (`Project`, `ProjectRequest`, `ProjectAnalytics`), Accounts (`User`,
auth), Newsletter (`Lead`), Content pages (static), Platform/shared (cross-cutting) —
`docs/architecture/README.md`.

## UX rule

Historical UX is authoritative unless explicitly classified `Fix` in `docs/rewrite/bugs.md` with an
approved `docs/rewrite/intentional-changes.md` entry. This includes UX that looks unfinished or
inconsistent (native `window.confirm()` on every destructive action despite a styled `Dialog`
existing; login always landing on `/user/projects` regardless of referring page) — per
`docs/rewrite/decisions-register.md`, these are adopted as **preserve-by-default** precisely because
there is no evidence they are defective, not because they were re-approved as good design. Do not
"clean up" a pattern like this on your own initiative.

## Visual rule

The historical design (`docs/design/visual-language.md`, `docs/design/components.md`,
`docs/design/screen-specs.md`) is the visual specification. Exact hex values, the exact 11-item type
scale, exact breakpoints, exact component variants — reproduce these, not Tailwind's stock defaults
under the same class names (the whole default palette is overridden; `bg-blue-500` does not mean
what it means in a stock Tailwind project). Where a component's internals are marked "Listed, not
Read" or content is marked Unknown (legal copy, marketing copy), stop and read the actual historical
source file or transcribe the actual copy verbatim before implementing — never invent copy or a
plausible-looking layout to fill a documented gap. The `Toast` component specifically has no
confirmed visual spec yet (`docs/rewrite/specification-review.md` §4) — read
`Toast.molecule.js`/`Toast.theme.js` before building it, since it's load-bearing for the first
slice's success/error feedback.

## Security rule

Backend authorization is mandatory; a frontend/UI restriction is never sufficient on its own.
Every ability in `docs/security/authorization-matrix.md` needs a Policy method (or an
Eloquent scope applied to every read path, not just `show`) — copy the exact rule from that table
into the Policy body. Private content must never leak via a different query shape than the one the
matrix was checked against — this is precisely how BUG-002 exists historically (right rule on one
path, no rule on a nearby path) and precisely why the historical search indexer's independent
visibility gate mattered (`docs/rewrite/specification-review.md` §2.1). When adding a new read path
(a new Livewire component, a new Scout query, a new export), ask explicitly whether it needs the
same visibility scope as every other read path for that resource — do not assume the model's global
scope alone is enough without checking it's actually attached.

## Testing rule

Every historical behavior with product-fidelity weight needs regression protection: every
`Fix`-classified bug ships with the specific test named in its `intentional-changes.md` entry, in
the same change as the fix. Every authorization-matrix row needs both an allow-case and a deny-case
Feature test — one without the other is incomplete, not merely undertested. A passing happy-path
test is never sufficient on its own (`CLAUDE.md`). Visual parity uses Playwright's
`toHaveScreenshot` against historical reference captures where available.

## AI rule

Before modifying behavior in an area, read: (1) `docs/rewrite/decisions-register.md` (is this
already decided?), (2) `docs/rewrite/bugs.md` (is there a classification for this area?), (3) the
relevant `docs/domain/`, `docs/design/`, `docs/authentication/`, `docs/search/`, `docs/email/`,
`docs/security/` topic file, (4) `docs/rewrite/open-questions.md` (is what you need listed as
Unknown? if so, investigate or escalate — never guess). If implementation surfaces a fact
contradicting a document's "Confirmed" claim, stop and reconcile the document against the historical
source before proceeding — this happened three times during the pre-implementation review
(`docs/rewrite/specification-review.md` §1) and each time the fix was a five-to-fifteen-minute
targeted read, not a large investigation.

## Documentation rule

A behavioral or architectural change is not complete until the relevant `docs/` topic file reflects
it, in the same change — not as follow-up work. This includes updating a decision's `Status` field
in `docs/rewrite/architecture-decisions.md`, adding the regression-test requirement to
`docs/rewrite/intentional-changes.md` before implementing a fix, and updating
`docs/rewrite/bugs.md`'s status column.

## Scope rule

No SaaS abstractions (tenants, subscriptions, billing, metering, hosted-only features) unless
historical evidence proves the concept is part of the product — none currently does. No admin/staff
role — confirmed absent from the entire historical system; do not add one without new, explicit,
approved evidence (this was already miscopied once from the reference projects into the self-hosting
installation guide and has been corrected — see `docs/rewrite/specification-review.md` SR-002; do
not reintroduce it). Do not copy LCxHolz's or Waffle Dashboard's product/domain behavior, only their
engineering-discipline and FOSS-lifecycle patterns respectively.
