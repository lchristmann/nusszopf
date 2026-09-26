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
- `legal/` — where the code, fonts and images come from, and under which licenses (`provenance.md`)
- `references/` — reference-project findings
- `release/` — FOSS releases and upgrades, and `release/parity/` (the finish-line parity report: one evidence page per phase)
- `rewrite/` — rewrite methodology and decisions, including `master-roadmap.md` (the plan and the finish-line phases), `bugs.md` (the historical-defect register), `intentional-changes.md` (every deliberate difference from the historical product), `decisions-register.md` (the concise already-decided vs. needs-a-human-decision index), `architecture-decisions.md`, and one page per implemented slice (`first-slice.md` … `tenth-slice.md`)
- `search/` — search behavior
- `security/` — security requirements, including `authorization-matrix.md` (the action-by-action authorization ruleset)
- `testing/` — testing strategy, accessibility and visual regression

For an operator: start with `deployment/README.md` (install and configure) and `deployment/operations.md` (run, back up, upgrade, recover, troubleshoot). For a contributor: `../README-DEV.md`, then `development/`, `testing/` and `architecture/`.

The historical Nusszopf repositories are the product reference. LCxHolz, Waffle Dashboard, and the Laravel Docker examples are implementation/process references.

## Where to start

1. `CLAUDE.md` and `.claude/rules/` — the constraints every change must respect.
2. `docs/rewrite/source-map.md` — which historical repository revision every other document is a snapshot of.
3. `docs/rewrite/decisions-register.md` — what's already decided vs. what still needs a human call.
4. `docs/rewrite/bugs.md` — every classified historical defect, with severity and required regression test.
5. `docs/rewrite/README.md` — the slice pages (`first-slice.md` … `tenth-slice.md`) say what each implemented slice contains and why; `docs/rewrite/master-roadmap.md` is the plan they followed.
6. Topic directories above, as needed for the area you're touching.
