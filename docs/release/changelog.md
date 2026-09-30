# Changelog

## Decision for Nusszopf (decided; see `docs/rewrite/decisions-register.md`)

This is a case where Nusszopf goes beyond the reference rather than copying it: self-hosting operators upgrading across versions need a changelog they can scan *before* upgrading, without reading commit history or opening every GitHub Release. The rules:

- Maintain `CHANGELOG.md` at the repository root in [Keep a Changelog](https://keepachangelog.com/) format, with an `Unreleased` section that accumulates entries as PRs merge.
- Categories: `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`, `Removed`, mirroring Keep a Changelog's own categories rather than inventing new ones.
- Every entry that has operator-facing impact (config changes, migrations requiring action, removed env vars) must be tagged inline, e.g. `**Breaking:**` or `**Migration required:**`, so it is scannable without reading prose. Cross-reference [`breaking-changes.md`](breaking-changes.md).
- On release, the `Unreleased` section is retitled to the version number and date, and a fresh empty `Unreleased` section is added above it.
- The GitHub Release notes for a version are that version's `CHANGELOG.md` section: `release.yml` extracts it and refuses to publish a release without one, so the two cannot drift apart.
- `CHANGELOG.md` at the repository root has been maintained since `1.0.0-rc.1`; the release workflow refuses to publish a version whose section is missing.

## Open questions

- Whether changelog entries are required to be added by the PR author (checked in CI, e.g. via a "changed files must include CHANGELOG.md" check) or curated by the maintainer at release time. **Deferred** (register C1, `docs/rewrite/architecture-decisions.md`): revisit once there is more than one regular contributor.

## Status

No historical Nusszopf evidence establishes a changelog convention to copy; the format above is Nusszopf's own, decided in `docs/rewrite/architecture-decisions.md`, "Changelog format and maintenance".
