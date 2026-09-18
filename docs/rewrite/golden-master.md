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
| Screens, navigation, components, visual language, states, responsive behavior | `docs/design/*.md` | Populated, evidence-tagged |
| Domain entities, relationships, permissions, invariants, workflows | `docs/domain/*.md` | Populated, evidence-tagged |
| Authentication | `docs/authentication/README.md` | Populated, evidence-tagged |
| Search | `docs/search/README.md` | Populated, evidence-tagged |
| Email | `docs/email/README.md` | Populated, evidence-tagged |
| Security-relevant historical properties | `docs/security/README.md` | Populated, evidence-tagged |
| User journeys / E2E mapping | `docs/journeys/README.md` | Populated, transcribed from the actual Cypress suite |

Remaining Unknowns (frontend-vs-backend cross-references not yet closed, exact CMS copy, a few unresolved selectors) are tracked individually in `docs/rewrite/open-questions.md` rather than guessed here. Suspected historical bugs are tracked as proposed corrections (not yet accepted) in `docs/rewrite/intentional-changes.md`. This pass did not implement anything; it is a specification baseline only.
