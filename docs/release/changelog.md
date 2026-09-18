# Changelog

## Reference finding

**Confirmed**: Waffle Dashboard has **no** `CHANGELOG.md`. Its only change record is (a) release commit messages that combine a Conventional-Commits-style type prefix with `+release: vX.Y.Z, <description>`, and (b) hand-written "## What's Changed" bullet lists pasted into each GitHub Release when it is created via the GitHub UI. There is no automation tying commits to changelog entries.

## Recommendation for Nusszopf (Inferred, needs approval)

This is a case where Nusszopf should go beyond the reference rather than copy it: self-hosting operators upgrading across versions need a changelog they can scan *before* upgrading, without reading commit history or opening every GitHub Release. Recommended:

- Maintain `CHANGELOG.md` at the repository root in [Keep a Changelog](https://keepachangelog.com/) format, with an `Unreleased` section that accumulates entries as PRs merge.
- Categories: `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`, `Removed`, mirroring Keep a Changelog's own categories rather than inventing new ones.
- Every entry that has operator-facing impact (config changes, migrations requiring action, removed env vars) must be tagged inline, e.g. `**Breaking:**` or `**Migration required:**`, so it is scannable without reading prose. Cross-reference [`breaking-changes.md`](breaking-changes.md).
- On release, the `Unreleased` section is retitled to the version number and date, and a fresh empty `Unreleased` section is added above it.
- The GitHub Release notes for a version should be generated from (or at minimum kept consistent with) that version's `CHANGELOG.md` section, not written separately by hand as in Waffle Dashboard — avoids the two documents drifting apart.

## Open questions

- Whether changelog entries are required to be added by the PR author (checked in CI, e.g. via a "changed files must include CHANGELOG.md" check) or curated by the maintainer at release time. Waffle Dashboard gives no evidence either way since it has no changelog file at all. This is an **architecture/process decision requiring approval**.

## Status

No historical Nusszopf or Waffle Dashboard evidence establishes a changelog convention to copy. Everything above is a recommendation, marked Inferred, pending approval — see `docs/rewrite/architecture-decisions.md`.
