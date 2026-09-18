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
