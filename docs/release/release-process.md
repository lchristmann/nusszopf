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

## Target process for Nusszopf (Inferred, needs approval)

Nusszopf should keep the same conceptual shape (version → build → tag → publish → release notes) but move steps 2–6 and 9 into CI, and add a required-checks gate before a tag can be treated as releasable:

1. Maintainer decides the next version and updates `CHANGELOG.md` (moves `Unreleased` entries under the new version heading — see [`changelog.md`](changelog.md)).
2. Maintainer opens/merges a PR with that changelog update. Required CI checks (tests, Pest, static analysis/Larastan, Pint formatting, Docker build) must pass on `main` before release — see `docs/testing/README.md` and `docs/development/quality.md` for what "required checks" means concretely.
3. Maintainer pushes an annotated, unprefixed semver tag (`X.Y.Z`) on `main`.
4. A tag-triggered GitHub Actions workflow builds and publishes the Docker image(s) (see [`docker-images.md`](docker-images.md)), runs migrations dry-run/check if feasible, and creates the GitHub Release automatically from the tag, using the corresponding `CHANGELOG.md` section as the release body — removing the hand-written duplication Waffle Dashboard has.
5. Operators upgrade per [`upgrades.md`](upgrades.md).

Every numbered step above beyond "decide a version and tag it" is a **recommendation, not evidence-backed reference behavior** — Waffle Dashboard's own process has no CI at all. This whole automated shape must be confirmed as an architecture decision (`docs/rewrite/architecture-decisions.md`) once the CI architecture is designed, not treated as already decided.

## Rollback

Waffle Dashboard's guide does not document a rollback procedure explicitly; "point the compose file at a previous version tag and `down`/`up`" is implied by the fact that image tags are immutable and version-pinned in the compose file, but this is **Inferred**, not directly demonstrated. Nusszopf should document rollback explicitly (previous image tag + reverse migration risk) rather than leaving it implicit — see [`upgrades.md`](upgrades.md).

## Status

Confirmed: Waffle Dashboard's manual sequence. Everything under "Target process for Nusszopf" is Inferred/recommended and requires approval.
