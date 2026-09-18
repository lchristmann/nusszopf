# Breaking Changes

## Reference finding (Waffle Dashboard, Confirmed)

No documented breaking-change procedure exists in the reference project. Across ten releases (`1.0.0` through `2.5.0`), including at least one MAJOR bump (`1.3.0` → `2.1.0`), there is no dedicated migration note, upgrade warning, or breaking-change section in the GitHub Release notes format described in `DEVELOPER-DOCS.md` (a plain `## What's Changed` bullet list). Whatever communication happened around the `1.x` → `2.x` jump is not captured in the reference materials available here (**Unknown** what changed or how it was communicated to existing operators, if at all).

This is a gap, not a pattern to imitate: Waffle Dashboard is a single-maintainer project where the maintainer is also typically the only operator, so undocumented breaking changes carry low real-world risk. Nusszopf explicitly targets third-party self-hosting operators (`CLAUDE.md` → Self-hosting), so this gap must be closed rather than inherited.

## Recommendation for Nusszopf (Inferred, needs approval)

- A MAJOR version bump is the only place a breaking change may occur (see [`versioning.md`](versioning.md)).
- Every breaking change must have a `CHANGELOG.md` entry tagged `**Breaking:**` (see [`changelog.md`](changelog.md)) stating, at minimum:
  - what changed
  - who is affected (which configurations/features)
  - the required operator action, as literal copy-pasteable commands where possible
  - whether the change has database implications (a migration that cannot be reversed, a column/table removal, a changed default)
  - whether the change has configuration implications (a renamed/removed/required environment variable)
  - the exact upgrade procedure for that version, if it differs from the standard procedure in [`upgrades.md`](upgrades.md)
  - rollback considerations specific to that change (e.g. "this migration is not reversible; restore from backup to roll back")
- The GitHub Release for a MAJOR version should surface these entries prominently rather than burying them in a flat bullet list, so an operator skimming release notes cannot miss them.

## Status

Confirmed: no breaking-change documentation practice exists in Waffle Dashboard to adopt. Everything above is a recommendation (Inferred) and must be confirmed as an architecture/process decision — see `docs/rewrite/architecture-decisions.md`.
