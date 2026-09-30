# Release Process

## Target process for Nusszopf (decided; implemented below)

Nusszopf should keep the same conceptual shape (version → build → tag → publish → release notes) but move steps 2–6 and 9 into CI, and add a required-checks gate before a tag can be treated as releasable:

1. Maintainer decides the next version and updates `CHANGELOG.md` (moves `Unreleased` entries under the new version heading — see [`changelog.md`](changelog.md)).
2. Maintainer opens/merges a PR with that changelog update. Required CI checks (tests, Pest, static analysis/Larastan, Pint formatting, Docker build) must pass on `main` before release — see `docs/testing/README.md` and `docs/handbuch/tests.md` for what "required checks" means concretely.
3. Maintainer pushes an annotated, unprefixed semver tag (`X.Y.Z`) on `main`.
4. A tag-triggered GitHub Actions workflow builds and publishes the Docker image(s) (see [`docker-images.md`](docker-images.md)), runs migrations dry-run/check if feasible, and creates the GitHub Release automatically from the tag, using the corresponding `CHANGELOG.md` section as the release body — removing hand-written duplication.
5. Operators upgrade per [`upgrades.md`](upgrades.md).

Every numbered step above beyond "decide a version and tag it" is Nusszopf's own design. It is decided (`docs/rewrite/architecture-decisions.md`, "Release build/publish automation") and implemented as described next.

## Implemented (operational track O-1, 2026-09-22)

The target process above is what `.github/workflows/release.yml` does, on a pushed tag `X.Y.Z` (or `X.Y.Z-rc.N`, published as a pre-release without moving `latest`):

1. the whole CI workflow runs as a gate (`workflow_call`): Pint, Larastan, Pest, the Playwright suite on three browsers, and the production-stack smoke test (`scripts/smoke-test.sh`);
2. both images are built for `linux/amd64` and `linux/arm64` with the tag baked in (`NUSSZOPF_VERSION`) and pushed to `ghcr.io/lchristmann/nusszopf-php-fpm` and `…/nusszopf-web`, tagged `X.Y.Z` and `latest`;
3. the GitHub Release is created with the tag's `CHANGELOG.md` section as its notes — the workflow fails if there is none — and `docker-compose.yaml`, `env.production.example` (the template, with `NUSSZOPF_VERSION` set to the tag) and `install.sh` attached.

To cut a release: move the `Unreleased` entries under `## [X.Y.Z] - date` in `CHANGELOG.md`, run `sh scripts/upgrade-test.sh <previous tag> --suite` (the upgrade from the last release on populated data, P-9; not part of CI because it builds two sets of images) and `sh scripts/restore-test.sh --rollback-from <previous tag>` (the backup, the restore onto an empty host and the rollback, P-10) and `sh scripts/search-recovery-test.sh` (losing and recovering the search index, P-11) and `sh scripts/queue-scheduler-test.sh` (the worker, Redis and the scheduler under failure, P-12; about an hour) and, with a mailbox you can read, `P13_RECIPIENT=… sh scripts/mail-delivery-test.sh` (real mail, P-13), merge, `git tag -a X.Y.Z -m X.Y.Z && git push origin X.Y.Z`. After the first push, check that the two GHCR packages can be pulled without logging in (`docker manifest inspect ghcr.io/lchristmann/nusszopf-web:<version>` from a machine that is not logged in to GHCR). For this public repository they were public from the first push (P-16); if they are not, make them public (Package settings → visibility).
The template is attached as `env.production.example`, without the leading dot, because GitHub renames an asset that starts with a dot (`.env.production.example` became `default.env.production.example` and could not be downloaded, P16-04); `install.sh` saves it on the host under the dotted name. `tests/Feature/Release/ReleaseAssetsTest.php` keeps the installer's downloads and the workflow's assets in step.
**Before tagging, look at the CI run of the exact commit** (the badge, or the run page on GitHub): a CI that never started (P-16 found seven runs that had, because of a billing block) or that went red is invisible from the terminal, and the release workflow runs the same gate again before it publishes.
The workflow ran on real tags for the first time in P-16 (`1.0.0-rc.1`, `1.0.0-rc.2`), and the real path found what no harness could: a template that GitHub renamed and so could not be downloaded (P16-04). Before a stable tag, install the release candidate as an operator would, from a machine that is not yours.

After the tag: `sh scripts/release-check.sh <tag>` installs the published release as an operator does, and `git tag verify/<tag> <tag>^{} && git push origin verify/<tag>` starts `.github/workflows/release-verify.yml`, which runs that check and the search-recovery, queue/scheduler, upgrade (from the previous tag, with the browser suite) and rollback drills on the pulled images, natively on amd64 and arm64 (`RELEASE_TAG=<tag>` makes the scripts pull instead of build).

Before the first tag, P-16 also owns what the finish-line phases could not do without a release (`docs/release/parity/README.md`, "Carried forward to later phases"): the real-iPhone and real-Android pass, the seven mails in Gmail, Outlook and Apple Mail (`scripts/mail-delivery-test.sh` for the sending, by hand for the reading), the drills on images pulled from GHCR and on arm64, and the first `CHANGELOG.md` section, without which the release workflow refuses to publish.

## Rollback

Nusszopf documents rollback explicitly (previous image tag + reverse migration risk) rather than leaving it implicit — see [`upgrades.md`](upgrades.md). Done in P-9: rollback is restoring the pre-upgrade backup (`docs/handbuch/deployment.md`, "Zurückgehen (Rollback)").

## Status

Nusszopf's process ("Target process" and "Implemented") is decided and implemented; it is untested on a real tag until P-16.
