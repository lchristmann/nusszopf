# Documentation

This documentation describes Nusszopf 2 as a product, system, development project, and FOSS release.

- `architecture/` — technical architecture
- `authentication/` — authentication behavior
- `design/` — exact historical UI specification (`screens.md` for narrative evidence, `screen-specs.md` for the per-screen implementation checklist)
- `development/` — local development
- `deployment/` — self-hosting and operations
- `domain/` — domain model and business rules
- `email/` — historical email behavior
- `journeys/` — end-to-end acceptance journeys
- `references/` — reference-project findings
- `release/` — FOSS releases and upgrades
- `rewrite/` — rewrite methodology and decisions, including `bugs.md` (the historical-defect register), `decisions-register.md` (the concise already-decided vs. needs-a-human-decision index), `first-slice.md` (the first implementation slice), `second-slice.md` (the project wizard and edit screen) and `third-slice.md` (the project requests)
- `search/` — search behavior
- `security/` — security requirements, including `authorization-matrix.md` (the action-by-action authorization ruleset)
- `testing/` — testing strategy

The historical Nusszopf repositories are the product reference. LCxHolz, Waffle Dashboard, and the Laravel Docker examples are implementation/process references.

## Where to start

1. `CLAUDE.md` and `.claude/rules/` — the constraints every change must respect.
2. `docs/rewrite/source-map.md` — which historical repository revision every other document is a snapshot of.
3. `docs/rewrite/decisions-register.md` — what's already decided vs. what still needs a human call.
4. `docs/rewrite/bugs.md` — every classified historical defect, with severity and required regression test.
5. `docs/rewrite/first-slice.md`, `docs/rewrite/second-slice.md` and `docs/rewrite/third-slice.md` — what the implemented slices contain, and why.
6. Topic directories above, as needed for the area you're touching.
