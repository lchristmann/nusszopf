# Release Process

## Reference finding (Waffle Dashboard, Confirmed)

The maintainer's documented workflow (`DEVELOPER-DOCS.md` → "How to Release") is entirely manual and sequential:

1. Decide the next `VERSION` (semver) by hand.
2. `docker login` to Docker Hub.
3. Build the PHP-FPM image (version tag + `latest`).
4. Build the Nginx image (version tag + `latest`), which depends on the PHP-FPM image via a build arg.
5. Manually smoke-test: point the production `docker-compose.yaml` at the new tags, `docker compose up -d`, click around.
6. Push both images, both tags.
7. Commit the `docker-compose.yaml` version bump using the combined `type+release: vX.Y.Z, description` commit message convention.
8. Annotated `git tag`, `git push origin <tag>`.
9. Manually create the GitHub Release from the tag via the web UI, with a hand-written `## What's Changed` bullet list.

There are no automated checks gating any of this — no CI run, no required tests, no lint. Release quality depends entirely on the maintainer's manual smoke test.

## Target process for Nusszopf (decided; implemented below)

Nusszopf should keep the same conceptual shape (version → build → tag → publish → release notes) but move steps 2–6 and 9 into CI, and add a required-checks gate before a tag can be treated as releasable:

1. Maintainer decides the next version and updates `CHANGELOG.md` (moves `Unreleased` entries under the new version heading — see [`changelog.md`](changelog.md)).
2. Maintainer opens/merges a PR with that changelog update. Required CI checks (tests, Pest, static analysis/Larastan, Pint formatting, Docker build) must pass on `main` before release — see `docs/testing/README.md` and `docs/development/quality.md` for what "required checks" means concretely.
3. Maintainer pushes an annotated, unprefixed semver tag (`X.Y.Z`) on `main`.
4. A tag-triggered GitHub Actions workflow builds and publishes the Docker image(s) (see [`docker-images.md`](docker-images.md)), runs migrations dry-run/check if feasible, and creates the GitHub Release automatically from the tag, using the corresponding `CHANGELOG.md` section as the release body — removing the hand-written duplication Waffle Dashboard has.
5. Operators upgrade per [`upgrades.md`](upgrades.md).

Every numbered step above beyond "decide a version and tag it" is Nusszopf's own design, not evidence-backed reference behavior — Waffle Dashboard's own process has no CI at all. It is decided (`docs/rewrite/architecture-decisions.md`, "Release build/publish automation") and implemented as described next.

## Implemented (operational track O-1, 2026-09-22)

The target process above is what `.github/workflows/release.yml` does, on a pushed tag `X.Y.Z` (or `X.Y.Z-rc.N`, published as a pre-release without moving `latest`):

1. the whole CI workflow runs as a gate (`workflow_call`): Pint, Larastan, Pest, the Playwright suite on three browsers, and the production-stack smoke test (`scripts/smoke-test.sh`);
2. both images are built for `linux/amd64` and `linux/arm64` with the tag baked in (`NUSSZOPF_VERSION`) and pushed to `ghcr.io/lchristmann/nusszopf-php-fpm` and `…/nusszopf-web`, tagged `X.Y.Z` and `latest`;
3. the GitHub Release is created with the tag's `CHANGELOG.md` section as its notes — the workflow fails if there is none — and `docker-compose.yaml`, `.env.production.example` (with `NUSSZOPF_VERSION` set to the tag) and `install.sh` attached.

To cut a release: move the `Unreleased` entries under `## [X.Y.Z] - date` in `CHANGELOG.md`, run `sh scripts/upgrade-test.sh <previous tag> --suite` (the upgrade from the last release on populated data, P-9; not part of CI because it builds two sets of images) and `sh scripts/restore-test.sh --rollback-from <previous tag>` (the backup, the restore onto an empty host and the rollback, P-10) and `sh scripts/search-recovery-test.sh` (losing and recovering the search index, P-11) and `sh scripts/queue-scheduler-test.sh` (the worker, Redis and the scheduler under failure, P-12; about an hour) and, with a mailbox you can read, `P13_RECIPIENT=… sh scripts/mail-delivery-test.sh` (real mail, P-13), merge, `git tag -a X.Y.Z -m X.Y.Z && git push origin X.Y.Z`. After the first push, make the two GHCR packages public (Package settings → visibility) so operators can pull without logging in.
The workflow has never run on a real tag: treat the first release as its test (use `0.1.0-rc.1` first), and check that `docker pull` and `install.sh` work from a machine that is not yours.

Before the first tag, P-16 also owns what the finish-line phases could not do without a release (`docs/release/parity/README.md`, "Carried forward to later phases"): the real-iPhone and real-Android pass, the seven mails in Gmail, Outlook and Apple Mail (`scripts/mail-delivery-test.sh` for the sending, by hand for the reading), the drills on images pulled from GHCR and on arm64, and the first `CHANGELOG.md` section, without which the release workflow refuses to publish.

## Rollback

Waffle Dashboard's guide does not document a rollback procedure explicitly; "point the compose file at a previous version tag and `down`/`up`" is implied by the fact that image tags are immutable and version-pinned in the compose file, but this is **Inferred**, not directly demonstrated. Nusszopf should document rollback explicitly (previous image tag + reverse migration risk) rather than leaving it implicit — see [`upgrades.md`](upgrades.md). Done in P-9: rollback is restoring the pre-upgrade backup (`docs/deployment/operations.md`, "Rollback").

## Status

Confirmed: Waffle Dashboard's manual sequence. Nusszopf's process ("Target process" and "Implemented") is decided and implemented; it is untested on a real tag until P-16.
