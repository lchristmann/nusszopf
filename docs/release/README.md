# Releases

Nusszopf is released as a self-hostable FOSS application. The release process must be predictable, reproducible, and safe for operators who are not Laravel developers to follow.

This directory documents the full lifecycle:

- [`versioning.md`](versioning.md) — version scheme and where the version lives
- [`changelog.md`](changelog.md) — how changes are recorded and communicated
- [`release-process.md`](release-process.md) — the maintainer's end-to-end release workflow
- [`docker-images.md`](docker-images.md) — image naming, tagging, registry, build/publish
- [`upgrades.md`](upgrades.md) — the operator-facing upgrade procedure
- [`breaking-changes.md`](breaking-changes.md) — how breaking changes are flagged and handled
- [`parity/`](parity/README.md) — the finish-line parity report (roadmap §7.7): one page per phase P-1…P-16, then the maintainer sign-off

## Process reference

[Waffle Dashboard](../references/waffle-dashboard.md) is the FOSS lifecycle reference: semantic versioning, unprefixed tags, Docker image publishing, and operator-facing backup/upgrade documentation. Its release *mechanics* (build/push/tag by hand) are a single-maintainer minimum, not the target — Nusszopf targets LCxHolz-level engineering discipline (`CLAUDE.md`), so the release build/publish/tag steps are automated in CI rather than run by hand. See `docs/references/lcxholz.md` for the CI/automation reference and `docs/rewrite/architecture-decisions.md` for the decisions this implied (registry choice, image layout), all now decided.

## Status

The scheme, tag format, changelog format, registry (GHCR), release automation and version exposure are decided (`docs/rewrite/decisions-register.md`, `docs/rewrite/architecture-decisions.md`) and implemented (`.github/workflows/release.yml`, `docs/deployment/`). Verified on release-equivalent builds (P-8…P-10) and then on real tags in P-16: the workflow ran for `1.0.0-rc.1` and `1.0.0-rc.2`, and the GitHub download, the GHCR pull, arm64 and the upgrade from one published release to the next were checked on `1.0.0-rc.2` (`docs/release/parity/P-16-release.md`). Still open by decision: who writes changelog entries (register C1).
