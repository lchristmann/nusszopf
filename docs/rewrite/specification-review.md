# Specification Review — Pre-Implementation Gate

Date: 2026-09-18. This is the adversarial review required before implementation begins. It reads
the complete specification (`CLAUDE.md`, `.claude/rules/`, every file under `docs/`) against itself
for internal contradictions, and re-opens the two flagged security-critical unknowns against the
historical source directly (`../historical/web-nusszopf`, `../historical/be-nusszopf`), rather than
trusting the archaeology pass's own conclusions uncritically. Findings that required new evidence
were resolved by reading the actual historical source in this pass, not by inference.

**Do not treat a prior "Confirmed" as automatically correct.** Two of this review's findings
(BUG-002's real-world exposure, BUG-016's residual risk) revise the *practical* risk level of
already-Confirmed facts without changing the facts themselves — both are called out explicitly
below rather than silently folded into existing documents.

## How to read this document

Every issue below has an ID, is classified, and lists the documents it touches. Classifications:

- **Blocker** — implementation must not start until resolved.
- **Must clarify** — important, should be resolved early (first slice or shortly after), does not
  block starting.
- **Non-blocking** — documentation improvement or minor uncertainty, already handled correctly by
  being marked Unknown rather than invented.
- **Resolved** — investigated and conclusively resolved during this pass; the source documents have
  already been updated in this same change.

No artificial issues were manufactured to pad this list. Several sections below conclude "no
material issue found" rather than inventing one.

---

## 1. Specification consistency audit

| ID | Documents | Issue | Evidence | Consequence | Status / action taken |
|---|---|---|---|---|---|
| SR-001 | `docs/rewrite/decisions-register.md` vs. `docs/rewrite/architecture-decisions.md` | Seven items decisions-register.md's "Already decided" section explicitly says must not be re-litigated (versioning scheme/tag format, changelog format, release automation, compose file split, reverse proxy/TLS, static analysis level) had their own detailed `architecture-decisions.md` entries still marked `Status: Proposed` — i.e. the two documents disagreed about whether these were settled. | Direct comparison of both files' text. | A future contributor reading `architecture-decisions.md` first (the more detailed document) would reasonably conclude these were still open, and could re-litigate settled decisions — exactly what `CLAUDE.md` forbids. | **Resolved.** All seven `Status` fields corrected to `Decided` in `architecture-decisions.md`, cross-referencing this finding. |
| SR-002 | `docs/deployment/README.md`, `docs/development/README.md` | Both documents' installation/onboarding sequences included a "create the first administrator account" bootstrap step, copied from the shape of other projects' installation guides (which have a real admin role to bootstrap). Nusszopf's own domain archaeology confirms **no admin/staff role exists anywhere in the historical product** (`docs/domain/entities.md`, "Entities confirmed absent"; `docs/rewrite/decisions-register.md`, "Explicitly not open"). | `docs/deployment/README.md` step 6 (pre-fix), `docs/development/README.md` step 9 (pre-fix), cross-referenced against `docs/domain/entities.md`. | This is exactly the reference-project business-logic leakage `.claude/rules/03-reference-projects.md` forbids — a structural installation step assumed from the references without checking it against Nusszopf's own confirmed domain. If unfixed, an implementer would either build a pointless admin-bootstrap command for a role that doesn't exist, or hit a confusing mismatch between the documented step and the actual authorization design. | **Resolved.** Both documents corrected: operators/developers just register a normal account through the ordinary registration screen, like any user — no bootstrap command. This is a genuine self-hosting simplification versus the references, not a loss. |
| SR-003 | `docs/design/screens.md`, `docs/rewrite/open-questions.md`, `docs/search/README.md`, `docs/security/authorization-matrix.md` | The single highest-priority flagged security unknown in the entire spec ("does search leak private content, does `/projects/{id}` leak private projects") was carried as Unknown across four separate documents without ever reading the two source files that actually settle it. | `webapp/src/pages/projects/[id].js`, `webapp/src/utils/libs/apolloClient.js`, `webapp/src/pages/api/events/search.js`, `webapp/src/utils/functions/search.function.js` — none had been opened in the archaeology pass despite being the direct, dispositive evidence for both questions. | Implementation could have proceeded for weeks treating a resolvable Unknown as an open security question requiring a "safe default" workaround, when the actual historical evidence was one `find` command away. | **Resolved**, see §2 below. All four documents updated in this pass with the confirmed findings. |
| SR-004 | `docs/rewrite/bugs.md` BUG-002, `docs/security/README.md` | BUG-002 ("`Request` visibility doesn't inherit from its parent `Project`") was documented purely from the Hasura permission schema, without checking whether the historical frontend ever actually exercised the gap. This matters for prioritization, not for the fix itself. | `search.function.js`'s `_syncProject` (independently gates request indexing on parent visibility via an admin-secret check) and an exhaustive grep confirming no frontend query reads `Request` except nested under `projects_by_pk`. | None that changes the required fix — BUG-002 must still be fixed at the Policy/Scope layer regardless, since a hand-crafted GraphQL-equivalent query could still exploit the gap in principle. But the severity framing ("private project's requests are not actually private" stated as if this were an active leak) was stronger than the evidence supports. | **Resolved.** BUG-002's entry in `bugs.md` now includes the resolved-context note; the fix requirement is unchanged. |
| SR-005 | `docs/rewrite/decisions-register.md` (item 16, pre-fix) | The decision register listed "preserve the 8-hour rolling session duration?" as `Requires human decision`, while `docs/rewrite/open-questions.md`'s own entry for the same question already states "Decision: preserve 8 hours as the Nusszopf 2 default pending a specific reason to change it" — i.e. one document had already effectively decided what the other still listed as blocking. | Direct text comparison. | Minor — would have cost a human one unnecessary decision-register line item, not a real ambiguity. | **Resolved** as part of the broader decision-register reclassification (§6 below) — moved to Category B, adopted. |
| SR-006 | `docs/rewrite/bugs.md` BUG-017, `docs/rewrite/open-questions.md`, `docs/rewrite/decisions-register.md` | `contactRequests` was carried as "Unknown, needs one more targeted read" across three documents, naming the exact file (`ContactDialog.js`) that would resolve it, without that read ever happening. | `ContactDialog.js`, read in full this pass — no GraphQL mutation of any kind in its submit handler. | Would have left a genuinely dead schema column sitting in the decision register indefinitely as a "human decision" that doesn't actually require human judgment once the one missing read happens. | **Resolved.** Confirmed dead; Nusszopf 2 does not reproduce it. All three documents updated. |
| SR-007 | `docs/rewrite/bugs.md` BUG-016 | Classified "Unknown, leaning Replace" based on the CORS pattern alone, without checking whether the frontend's Meilisearch API key was a restricted search key or the admin/master key, and without connecting it to the (also-then-unresolved) indexing-visibility question. | `search.service.js` (`MEILI_PK`, distinct from the indexer's own `MEILI_API_KEY`/admin-style key in `search.function.js`) plus the SR-003 resolution. | None that changes the outcome (Nusszopf 2's architecture eliminates the pattern regardless), but the historical risk assessment was incomplete without this cross-reference. | **Resolved.** Reclassified `Replace (moot)` with the full chain of reasoning now recorded. |
| SR-008 | *(new finding, not previously documented anywhere)* `webapp/src/utils/libs/apolloClient.js` | The SSR Apollo client keeps its bearer-token-resolution state (`let accessToken`) at **module scope**, not per-request, and writes/reads it across an `await` boundary inside the per-request `authLink`. This is a textbook shared-mutable-state race-condition shape. | Full read of `apolloClient.js`, this pass. | A theoretical historical vulnerability (one visitor's session token used for a concurrent visitor's outbound Hasura query) that no prior archaeology pass had surfaced at all. Real-world exploitability on Vercel's specific execution model is Unknown and cannot be established from this repository. | **Resolved / recorded.** Added as BUG-020, classified `Replace (moot)` — Nusszopf 2's per-request Laravel process model has no equivalent hazard, so nothing needs fixing, only not reproducing (e.g. in a long-running queue worker holding shared HTTP-client state across jobs). |

No other cross-document contradictions were found. The specification is, on the whole, unusually
disciplined about marking Unknown rather than inventing — the issues above are places where
*resolvable* Unknowns went unresolved despite the resolving evidence being locatable in minutes,
not places where the specification actively asserts something false.

## 2. Security findings (the two priority questions)

### 2.1 Meilisearch visibility

**Resolved, Confirmed, favorable outcome.** Full detail now lives in `docs/search/README.md`
("Authorization filtering at search time") and `docs/rewrite/bugs.md` (BUG-016). Summary:

- Private projects were **never indexed at all** — the indexing webhook (`search.function.js`,
  `_upsertProject`) only calls Meilisearch when `visibility === public`.
- A project going public → private **actively removes** its own document and every one of its
  requests' documents from the index (`index.deleteDocuments(...)`), not just stops future syncs.
- `Request` documents independently re-check the **parent project's** visibility before indexing
  (`_syncProject` → `getProjectCrop`, an admin-secret call bypassing the Request table's own gapped
  Hasura permission specifically to make this check possible) — so BUG-002's permission gap never
  actually reached the search surface.
- Filtering happened at **indexing time**, not query time — Meilisearch itself held no private
  documents for a leaked/misconfigured query-time filter to fail to exclude.

**The safe target invariant for Nusszopf 2** (unchanged from the pre-existing spec, now backed by
positive historical evidence rather than an open question): *a `Project`/`Request` is written to
the search index if and only if the project is currently public; a visibility change to private
removes it from the index in the same operation that changes visibility.* Nusszopf 2's Scout
integration must reproduce this indexing-time gate exactly (`docs/architecture/README.md`'s Search
section already specifies this correctly) and should add a query-time visibility scope as
defense-in-depth beyond what history did, since Scout is more easily called from more places in a
Livewire app than the historical bespoke single webhook was.

### 2.2 `/projects/{id}` visibility

**Resolved, Confirmed, favorable outcome.** Full detail in `docs/rewrite/open-questions.md`. The
SSR data fetch (`pages/projects/[id].js`'s `getServerSideProps`) uses the **viewer's own session
token** (via `apolloClient.js`'s `authLink`/`requestAccessTokenServer`, which resolves the current
request's actual Auth0 session), never an elevated/admin credential. Hasura's `select_permissions`
filter therefore applies exactly as for any other authenticated or anonymous request:
`projects_by_pk` returns `null` for a private project queried by a non-owner, and
`getServerSideProps` returns a genuine `{ notFound: true }` — a hard 404 at the SSR layer, not a
soft client-side redirect after the shell has rendered (unlike the related, already-documented
BUG-003 client-side-auth-gate pattern for *other* protected pages).

**The invariant to test in Nusszopf 2**: a non-owner (anonymous or authenticated) requesting a
private project's direct URL receives a 404, indistinguishable from a genuinely nonexistent project
id (no information leak about existence via a different status code) — `ProjectPolicy::view()` plus
`Project::visible()` scope is the correct mechanism, already specified in
`docs/security/authorization-matrix.md`.

Both questions resolve in the *safe* direction — this materially improves the pre-implementation
risk posture versus what the prior pass's "Unknown, must resolve before implementation" framing
implied. Neither required inventing a "safe default" workaround; both were directly evidenced.

## 3. Changes made in this pass

Every change is a source-document update, not a new invented requirement:

- `docs/rewrite/open-questions.md` — 2 entries resolved (SSR visibility, `contactRequests`).
- `docs/search/README.md` — indexing-time visibility section resolved; 2 "must still determine" items resolved.
- `docs/design/screens.md` — the flagged-but-unresolved project-creation note resolved.
- `docs/security/authorization-matrix.md` — the search row's "Unknown" status resolved.
- `docs/security/README.md` — decision-items summary updated (3 of 4 items resolved; 1 new item added).
- `docs/rewrite/bugs.md` — BUG-002 (context note), BUG-016 (reclassified), BUG-017 (reclassified), BUG-020 (new).
- `docs/rewrite/intentional-changes.md` — new entry for `contactRequests` non-reproduction.
- `docs/rewrite/decisions-register.md` — restructured into A/B/C (see §6).
- `docs/rewrite/architecture-decisions.md` — 7 `Status` fields reconciled to `Decided`; 8 `Status` fields advanced to `Adopted` with the chosen option recorded as a decision, not just a recommendation.
- `docs/deployment/README.md`, `docs/development/README.md` — admin-bootstrap step corrected (SR-002).
- `docs/rewrite/first-slice.md` — historical wizard mechanics documented in full; explicit "final vs. scaffolding" table added; explicit acceptance criteria added.

## 4. New or resolved uncertainties

Resolved (see above): SSR visibility, Meilisearch visibility, `contactRequests`, BUG-016's residual
risk, the admin-bootstrap contradiction, the decision-register/architecture-decisions status
mismatch. Newly discovered: BUG-020 (SSR shared-state race — moot for the rewrite, recorded for
awareness). No new *blocking* uncertainty was introduced.

One item is elevated from "Non-blocking" to **Must clarify** by this review (it wasn't flagged with
appropriate urgency before): **SR-009 — the `Toast` component has no confirmed visual spec at all**
(positioning, stacking, auto-dismiss timing, per-type coloring) despite being, by the specification's
own description, "the standard, universal pattern for all mutation feedback across the app"
(`docs/design/states.md`). Every write-path screen in the first slice (registration, project
creation) depends on this component's success/error states. Unlike the legal-page-copy and
component-internals gaps elsewhere (correctly Non-blocking, since nothing depends on them this
early), Toast is load-bearing for the very first slice. Recommended action: a short, targeted read
of `Toast.molecule.js`/`Toast.theme.js` in `../historical/web-nusszopf` before or during first-slice
implementation — this is a five-minute archaeology task, not a design decision, and should not be
treated as licence to invent a generic Tailwind toast design.

## 5. First-slice re-audit (wizard and authentication scaffolding)

Full detail now lives in `docs/rewrite/first-slice.md`, substantially expanded in this pass. Summary
of what changed: the historical 4-step wizard's exact mechanics were previously described only at
the level of "4 steps, these fields" — this pass read `create.js` and `useStepper.js` in full and
confirmed the wizard is a **single Formik instance with no server-side persistence between steps,
step navigation via a `?step=N` URL query parameter, and no draft entity** — i.e. there is nothing
being "simplified away" by the first slice's single-form approach that would require later rework;
the wizard is purely an additional UI layer over the same eventual create action. The first slice
was already correctly scoped in this respect; this pass makes the reasoning explicit and evidenced
rather than assumed, and adds a table distinguishing final behavior from scaffolding line-by-line so
a future implementation agent cannot mistake the single form for the target UX. The same treatment
was applied to authentication: `docs/authentication/README.md` remains the complete, unchanged
specification for the full auth surface; the first slice implementing only email/username/password
register+login is a sequencing cut, stated explicitly as such, not a scope reduction of that
document.

## 6. Preserve/Fix/Replace/Unknown classification audit

Full classification table: `docs/rewrite/bugs.md`. This pass re-examined every entry against the
criteria in the task brief:

- **Every `Fix` entry** (BUG-001 through BUG-010) was checked for: evidence of defectiveness (all
  have direct code/schema evidence, not inference), documented intended behavior (all present),
  understood product impact (all present), and a specified regression test (all present, in
  `docs/rewrite/intentional-changes.md`). No `Fix` entry was found to be a disguised modernization
  or an implementation detail mistaken for product behavior — each one has a concrete, named
  security or usability defect with no plausible alternative "this was intentional" reading, except
  where the spec itself already flags genuine ambiguity (see `Unknown` entries below).
- **Every `Unknown` entry** was checked for exhausted evidence. Three were resolvable and are now
  resolved (BUG-016, BUG-017, and the two open-questions.md entries feeding BUG-002's context).
  **BUG-011 (newsletter opt-in asymmetry) and BUG-013/BUG-014/BUG-015 remain genuinely Unknown** —
  each was checked against "has evidence actually been exhausted" and the answer is yes: these are
  product-intent questions (is a UX pattern deliberate or unfinished?) that no amount of further
  code-reading resolves, because the code is unambiguous about *what* happens and silent about
  *why*. BUG-011 alone is elevated to a required human decision (Category A, §7) because it is the
  only one with a plausible compliance/legal dimension (GDPR-style consent); BUG-013/014/015 are
  resolved to Category B (preserve as historically observed, matching the product-fidelity default
  that already governs absent evidence of a defect) rather than left open, since an experienced
  engineer can safely apply `CLAUDE.md`'s own stated default without inventing a UX decision.
- **Every `Replace` entry** (BUG-012, BUG-016, BUG-019, BUG-020) was checked for: is the historical
  implementation genuinely obsolete/inappropriate (yes in all four — Auth0-specific dead UI, a CORS
  pattern the new architecture doesn't need, a library-specific bug tied to a replaced dependency,
  and a Node.js-specific shared-state hazard with no Laravel equivalent), is the replaced observable
  behavior understood (yes), and are equivalence requirements documented (yes — in each case the
  documentation is explicit that *no* equivalent needs to be built, which is itself the correct
  "equivalence requirement" when the historical behavior was itself the defect or the dead code).
- No case was found of an inferred behavior having silently become "Confirmed" without new evidence,
  and no case was found of an important defect lacking a regression-test requirement.

**Conclusion: the classification system itself is sound.** The issues found in this pass are
entirely about *evidence gaps that were resolvable but unresolved*, not about misclassification.

## 7. Decision register reduced to A/B/C

Full detail and reasoning per item: `docs/rewrite/decisions-register.md` (fully restructured in
this pass). Summary: of the 16 items previously listed as "Requires human decision," **1 is a
genuine Category A** (newsletter opt-in asymmetry, BUG-011 — a real product/compliance-intent
question with no engineering-judgment answer), **2 are Category C** (defer — changelog-authorship
process, breached-password-check scope — neither affects the first implementation slices), and
**13 are Category B**, each now given an adopted default directly in
`docs/rewrite/architecture-decisions.md` rather than left open, because in every case a reasonable
engineering choice provably does not alter the historical product (naming, storage-tier defaults,
tooling choices, or "preserve as historically observed" where that is already the specification's
own stated default absent contrary evidence). One additional item (`contactRequests`, BUG-017) was
resolved outright and removed from the register entirely.

**The one decision that remains for the human maintainer before implementation:**

> Should the newsletter signup-checkbox path (at registration) require the same double opt-in
> confirmation as the public newsletter-signup form, or is the registration flow's own consent
> (privacy checkbox + working email, since registration itself proves the address is reachable)
> sufficient? See `docs/rewrite/bugs.md` BUG-011 for the full historical evidence and both
> interpretations. Recommended default if no decision is made: preserve the historical asymmetry
> (product-fidelity default) — but this should be an explicit choice given the GDPR-consent-flavored
> `privacy` column's existence, not a silent default.

## 8. Authorization matrix audit

Full matrix: `docs/security/authorization-matrix.md`, updated in this pass (search row resolved).
Re-audited against every category the task brief names:

- **Anonymous users**: correctly denied write access everywhere; correctly allowed read access to
  public projects/requests, any user's name/picture, and the newsletter subscribe/unsubscribe
  surface. No gap found.
- **Resource owners vs. other authenticated users**: ownership is the sole authorization primitive
  throughout (`docs/domain/invariants.md` confirms no permission anywhere references anything but a
  direct `user_id`/`project_id` FK match) — a clean, auditable model with no role/group indirection
  to create edge cases. No gap found.
- **Child resources belonging to private parents**: this is exactly BUG-002, already the
  highest-severity entry in the matrix, now with its practical-exposure context resolved (§1, SR-004
  above). The Policy/Scope fix specified is correct and sufficient.
- **Deleted resources**: cascade deletes are DB-enforced (`ON DELETE CASCADE` throughout) and
  confirmed to still fire the corresponding search-desync side effect even when triggered by a
  cascade (Postgres row-level triggers fire regardless of whether a `DELETE` originated from a
  direct statement or a cascade) — no orphaned search documents after a cascade delete. No gap
  found, though this specific reasoning (cascade deletes still firing per-row triggers) was not
  previously stated explicitly anywhere in the spec; worth keeping in mind when translating to
  Eloquent's `deleting`/`deleted` model events, which **do not** fire automatically for
  DB-level-cascade-deleted child rows the way Postgres triggers do — **SR-010, Must clarify**: if
  Nusszopf 2's Scout desync-on-delete relies on Eloquent model events (as `docs/architecture/README.md`
  proposes) rather than DB triggers, a project deleted via `$project->delete()` will fire `Request`
  desync correctly only if Eloquent's own cascade (`->cascadeOnDelete()` at the DB level triggers a
  DB-level delete that Eloquent doesn't separately observe per child row) is paired with either (a)
  loading and individually deleting `Request` models in application code so their own `deleted`
  events fire, or (b) a database-level trigger/event mirroring the historical mechanism, or (c) an
  explicit post-delete reindex step for the parent's former child ids. This is an implementation
  detail to get right during the Projects module's build, not a specification gap, but it is exactly
  the kind of thing that silently reproduces a *different* bug (orphaned search documents after a
  cascade-deleted project) if the Eloquent-events-only approach is assumed to behave like Postgres
  triggers did. Recorded here so the first slice's search-sync tests explicitly cover "delete a
  project with requests, assert no orphaned documents remain," not just "delete a project, assert
  its own document is gone."
- **Direct URL access / manipulated IDs**: covered by the resolved `/projects/{id}` finding (§2.2)
  and by UUIDs/opaque ids throughout (no sequential/guessable ids anywhere in the schema). No gap
  found.
- **Search/index access**: covered by the resolved Meilisearch finding (§2.1). No gap found.
- **Contact/account/analytics actions**: all already correctly modeled in the matrix, including the
  explicit "no separate publish permission — visibility is just another field" finding, which is
  correct and should not be "fixed" into a more conventional publish/unpublish workflow (that would
  be an unrequested product change).

**Conclusion**: the authorization matrix is sound and can be expressed completely by Laravel
Policies + Eloquent scopes with no reliance on frontend checks, as required. SR-010 above is the one
new, concrete implementation-planning note this audit adds.

## 9. Visual-fidelity audit

Screen-by-screen: `docs/design/screen-specs.md`. Component-by-component: `docs/design/components.md`.
Cross-checked against every question in the task brief. Findings:

- **Layout, typography, spacing, colors, dimensions**: fully Confirmed for every screen and the
  entire type/color scale (`docs/design/visual-language.md`) — this is the best-evidenced part of
  the whole specification (literal hex values, literal Tailwind class names, literal breakpoints all
  directly read from `tailwind.config.js` and component theme files).
- **Component states (hover/focus/active/disabled)**: Confirmed for every atom (`Button`, `Input`,
  `Select`, `Switch`) via the consistent ring-based treatment. Confirmed as a closed, deliberate
  system, not ad hoc.
- **Loading/empty/error/success states**: Confirmed for every screen **except** two explicitly
  flagged gaps, both already correctly marked Unknown rather than invented: the project-detail
  page's own loading state (not fully traced past `Avatar`'s `loading` prop), and the project-edit
  "Requests tab with zero requests" empty state (`RequestsView` internals never opened). **Non-blocking**
  — confirm both at implementation time against the actual historical DOM/network behavior if still
  reachable, or make an explicit, documented, minimal design decision if not (a bare "no requests
  yet" text matching the pattern already established for the project-detail page's own empty state
  would be a defensible, low-risk default, not an invention of new UI language).
- **Responsive behavior**: fully Confirmed with literal breakpoint values throughout
  (`docs/design/responsive-behavior.md`) — including the specific "duplicated DOM node, not a
  resized element" pattern for the mobile create-project CTA, which is exactly the kind of detail a
  less careful rewrite would flatten into "just make it responsive" and lose.
- **Exact content**: legal-page copy, home-page marketing section copy, and the profile page's
  sponsoring-link copy are explicitly Unknown, correctly not invented, and correctly flagged as
  "pull verbatim at implementation time." **Non-blocking** as long as this discipline holds at
  implementation time — the risk is not the current documentation state but a future implementer
  paraphrasing instead of transcribing under time pressure. Worth a one-line reminder in the
  implementation contract (added, see `docs/rewrite/implementation-contract.md`).
- **Toast component**: elevated to Must-clarify, see SR-009 above — the one genuine visual-fidelity
  gap with first-slice-blocking implications (registration/project-creation success/error feedback).
- **Measured vs. inferred**: the specification is consistently explicit about this distinction
  throughout (Confirmed/Inferred/Unknown tags on nearly every claim) — no screen was found where
  vague description stands in for measured values without being labeled as such.

**No screen was found to be too vague for faithful implementation**, with the Toast exception noted.

## 10. Domain and workflow completeness audit

Cross-checked entities → relationships → permissions → workflows → screens → tests for all five
domain entities (`User`, `Project`, `Request`, `ProjectAnalytics`, `Lead`):

Every entity has a documented lifecycle, ownership rule, visibility rule, validation rule (or
explicit "DB-level only, no further validation Confirmed" where that's the honest answer),
relationships, authorization rule, and at least one screen. No entity was found with a missing link
in this chain beyond what's already correctly marked Unknown (e.g. `Lead`'s exact client-side email
validation library/rule — Non-blocking, standard Yup email validation is a safe, low-risk default
consistent with every other email field in the product). No workflow was found missing entry/
action/validation/persistence/authorization/side-effect/success/failure documentation. **No
completeness gap found** beyond items already correctly flagged elsewhere in this review.

## 11. Search audit

Fully covered by §2.1 above and the updated `docs/search/README.md`. The target invariant — *a user
cannot discover content through search that they are not allowed to discover through the
application* — **held historically** (a favorable, evidenced finding, not an assumption) and is
correctly specified as the enforcement target for Nusszopf 2, with the added defense-in-depth
recommendation (query-time scope in addition to indexing-time gate) appropriately marked as
exceeding historical parity rather than reproducing it.

## 12. Authentication audit

`docs/authentication/README.md` was re-read in full against every item in the task brief
(registration, login, logout, password reset, account deletion, social login, profile/avatar,
sessions, redirects, errors, rate limiting, email, security boundaries). Every item has a documented
historical behavior and a specified Laravel-native translation, with Auth0-specific mechanics
explicitly and consistently separated from the product behavior to preserve — this document does
not confuse infrastructure with UX anywhere. The remaining genuinely Unknown items (exact Auth0
password-policy authoritative source, IP-block thresholds, breached-password-check scope) are
correctly marked Unknown rather than guessed, and are now resolved into Category B/C decisions
(§7) rather than left as standing questions. **No gap found** where the choice of Laravel-native
authentication has been allowed to silently redefine the UX — the document is explicit at every
point about which layer (product behavior vs. infrastructure mechanic) each fact belongs to.

## 13. Email audit

`docs/email/README.md` covers all nine templates with trigger, recipient, subject, Reply-To (or its
absence, correctly flagged as BUG-005), links, variables, branding, and language for each. The two
templates with no discoverable trigger (newsletter issues "Nussig No. 1–3", the generic support
template) are correctly scoped out as historical editorial/operational artifacts, not product
features to rebuild — this is the right call, not a gap. Every email with real product behavior
behind it has an implied test strategy already stated in `docs/testing/README.md` (`Mail::fake()`
for triggers/recipients/content, separate verification of actual rendering against the historical
templates for branding/copy fidelity). **No gap found.**

## 14. Self-hosting audit

Reviewed as a competent-developer-with-no-Nusszopf-knowledge would encounter it. One genuine defect
found and fixed in this pass: SR-002 (the phantom admin-bootstrap step). Beyond that:

- The installation sequence is otherwise coherent and copy-pasteable once the Docker architecture
  exists.
- Backup/restore/upgrade/rollback procedures are concrete and evidence-based (drawn from a
  third-party-tested guide), not invented.
- The health-check, storage-volume, and asset-consistency design (`docs/deployment/README.md`'s
  "why a dedicated nginx image" section) correctly identifies and avoids a real defect present in
  the raw Laravel Docker examples reference, rather than copying it — good adversarial engineering
  already present in the spec before this review.
- No unnecessary operator complexity was found — the design consistently picks the simpler of two
  demonstrated options as the default, with the more complex option
  documented as an upgrade path, matching `.claude/rules/06-self-hosting.md`'s
  instruction not to overengineer.
- Every remaining Undecided item in this area (registry, backup tier, health-check depth, object
  storage) is now resolved to Category B with an adopted default (§7).

**No further self-hosting gap found.**

## 15. Testing and golden-master audit

`docs/testing/README.md` and `docs/journeys/README.md` were checked against every critical
historical behavior identified elsewhere in the spec. Every `Fix`-classified bug has a named
regression test in `docs/rewrite/intentional-changes.md`. Every authorization-matrix row is
specified to need both an allow-case and a deny-case test — a concrete, checkable bar, not a vague
"test authorization" instruction. The historical E2E gap for search (BUG-018) is correctly treated
as a testing-debt item to close in the new suite, not a historical behavior to leave untested. The
newly-resolved search-visibility invariant (§2.1) needs one addition, already reflected in the
first-slice acceptance criteria (`docs/rewrite/first-slice.md`): a Feature test asserting a project
flipped back to private is actually removed from the index, not just never added (the historical
webhook's delete-on-unpublish behavior, easy to under-test if only "publish → appears in search" is
checked). Visual parity now has an adopted tool (Playwright `toHaveScreenshot`, §7) rather than an
open tooling question. **No further testing gap found.**

## 16. Architecture complexity audit

`docs/architecture/README.md` and `docs/architecture/mapping.md` were reviewed for unnecessary
abstraction. The proposed shape (Eloquent models directly from Livewire components/controllers, no
GraphQL layer, no repository layer, Policies for authorization, Scout for search, Notifications for
mail, queued jobs replacing event-trigger webhooks) is appropriately minimal for a five-entity
domain — no premature interfaces, no service layer imposed where a Policy or a model method
suffices, no speculative "module" package boundaries beyond namespacing. The "modular monolith"
framing is explicit that this means directory/namespace organization, not separately-versioned
packages, which is the right level of restraint for this product's size. **No architecture
complexity issue found** — the proposal already reflects the "do not add complexity merely because
another project has it" instruction.

## 17. FOSS/release audit

`docs/release/*` was checked for SaaS-shaped assumptions (none found — no tenants, no billing, no
metering anywhere) and for coherence as a third-party self-hosted FOSS process. Versioning,
changelog, breaking-change documentation, Docker image tagging, and the release-automation shape are
all specified with reference-project evidence and explicit "go beyond the reference" reasoning where
Nusszopf's engineering-quality bar exceeds a manual release process. All previously-Undecided
items in this area are now resolved (registry → GHCR, §7). **No gap found.**

---

## Final pre-implementation status

### READY WITH EXPLICIT DECISIONS

Not READY unconditionally, because one genuine product/compliance decision remains open (§7). Not
NOT READY, because that single decision does not block the first implementation slice at all (the
newsletter domain is entirely out of the first slice's scope, per `docs/rewrite/first-slice.md`) and
every other previously-open item has been resolved or given an adopted default in this pass. No
product, security, or foundational-architecture uncertainty remains that blocks starting work on the
first vertical slice specifically.

### The exact human decision required before implementation touches the newsletter domain

> **BUG-011** — should the newsletter signup-checkbox path (at registration) require the same
> double opt-in confirmation as the public newsletter-signup form? See `docs/rewrite/bugs.md` for
> full evidence and both interpretations. This does not need to be answered before starting the
> first vertical slice (`docs/rewrite/first-slice.md`), only before any slice that touches
> registration's newsletter checkbox or the `Lead` domain.

Everything else previously requiring human input has either been resolved with direct historical
evidence (§2, §6) or reduced to an adopted Category B engineering decision (§7) that a human may
still review but that does not block starting work.
