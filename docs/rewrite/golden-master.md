# Nusszopf 1 → Nusszopf 2 Golden Master

## Core rule

> Nusszopf 2 reproduces the documented Nusszopf 1 product unless the historical behavior is demonstrably broken/incomplete or an explicit intentional change is recorded.

## Product contract

The Golden Master covers screens, routes, navigation, visual design, components, responsive behavior, forms, validation, dialogs, loading/empty/error/success states, domain entities, relationships, workflows, permissions, authentication, search, emails, side effects, and important edge cases.

## Bug handling

Historical bugs should not be preserved merely because they existed.

For a suspected bug:

1. establish historical behavior
2. establish why it is defective
3. determine intended behavior from surrounding evidence
4. implement the correction
5. document the deviation
6. add regression coverage

Do not use “bug fix” as a pretext for redesign.

## Completion

The Golden Master is complete only when historical repositories have been systematically inspected and remaining Unknowns are explicitly documented.

## Status (2026-09-18 archaeology pass)

A first complete pass across all three historical repositories is done. See `docs/rewrite/source-map.md` for exact commit revisions studied. The product contract above is now populated as follows:

| Area | Document | Status |
|---|---|---|
| Screens, navigation, components, visual language, states, responsive behavior | `docs/design/*.md`, `docs/design/screen-specs.md` | Populated, evidence-tagged; screen-specs.md adds a per-screen implementation checklist |
| Domain entities, relationships, permissions, invariants, workflows | `docs/domain/*.md` | Populated, evidence-tagged; several open questions resolved in the second pass (Lead creation route, location/period JSON shape, category labels, ProjectAnalytics auto-creation) |
| Authentication | `docs/authentication/README.md` | Populated, evidence-tagged |
| Search | `docs/search/README.md` | Populated, evidence-tagged; visibility-filtering-at-query-time remains the highest-priority open question |
| Email | `docs/email/README.md` | Populated, evidence-tagged |
| Security-relevant historical properties + authorization matrix | `docs/security/README.md`, `docs/security/authorization-matrix.md` | Populated, evidence-tagged |
| User journeys / E2E mapping | `docs/journeys/README.md` | Populated: Journeys 1–4 transcribed from the actual Cypress suite; Journeys 6–8 (newsletter, contact, avatar) added as Inferred specifications since no historical E2E coverage exists for them |
| Historical bugs / defect register | `docs/rewrite/bugs.md` | Populated — every suspected defect classified (Fix/Preserve/Unknown/Replace) with an ID, severity, and required regression test |
| Decision register | `docs/rewrite/decisions-register.md` | Populated — already-decided vs. requires-human-decision, concise index |
| First vertical slice | `docs/rewrite/first-slice.md` | Three candidates proposed, one selected (not yet approved for implementation) |

## Second pass (specification hardening, 2026-09-18)

Following the archaeology pass above, a second pass critically reviewed the findings, resolved a targeted set of previously-Unknown items against additional historical source (see `docs/rewrite/open-questions.md` for each item's resolution and evidence), and produced the remaining Golden Master deliverables (authorization matrix, screen-by-screen checklist, consolidated bugs register, decision register, first-slice proposal). Remaining Unknowns (a few unresolved frontend-vs-backend cross-references, exact CMS copy, the Meilisearch visibility-filtering question, several product-intent questions that only a human can settle) are tracked individually in `docs/rewrite/open-questions.md` and `docs/rewrite/decisions-register.md` rather than guessed here. Suspected historical bugs are tracked as proposed corrections (not yet accepted) in `docs/rewrite/intentional-changes.md`, indexed by `docs/rewrite/bugs.md`. This pass did not implement anything; it is a specification and architecture baseline only.
