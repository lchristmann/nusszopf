# Rewrite Specification

## Objective

Revive Nusszopf as a modern, maintainable, self-hostable FOSS application while preserving the historical product.

The rewrite replaces technology, not product identity.

## Goals

### Product fidelity
Reproduce historical behavior, domain, UI, navigation, workflows, permissions, search, authentication, and emails.

### Engineering quality
Reach the engineering discipline demonstrated by LCxHolz: testing, automation, CI, static analysis, documentation, reproducible development, and maintainable architecture.

### Operator experience
Make installation, configuration, operation, upgrades, backups, and recovery straightforward.

## Phases

1. Archaeology — done, see `docs/rewrite/golden-master.md`
2. Golden Master specification — done for this pass; see `docs/rewrite/golden-master.md` for status, `docs/rewrite/bugs.md` for classified defects, `docs/security/authorization-matrix.md` for the authorization ruleset, `docs/design/screen-specs.md` for the per-screen checklist, and `docs/rewrite/decisions-register.md` for what still needs a human decision
3. Architecture — proposed, see `docs/architecture/README.md`; module boundaries and several infrastructure choices in `docs/rewrite/decisions-register.md` still need approval
4. Infrastructure foundation — not started
5. Vertical feature implementation — not started; first slice proposed in `docs/rewrite/first-slice.md`, not yet approved or implemented
6. Parity testing — not started
7. Release preparation — not started
8. FOSS release — not started
