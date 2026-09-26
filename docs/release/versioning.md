# Versioning

## Scheme

Semantic versioning: `MAJOR.MINOR.PATCH`.

- **MAJOR**: breaking changes for operators — required manual configuration/migration steps beyond "pull new image, run migrations", removed environment variables, removed features, or incompatible data migrations. See [`breaking-changes.md`](breaking-changes.md).
- **MINOR**: new user-visible functionality, backward compatible.
- **PATCH**: bug fixes, security fixes, and other backward-compatible corrections.

**Decided** (`docs/rewrite/decisions-register.md`): unlike Waffle Dashboard, Nusszopf starts at `0.x.y` while the rewrite is reaching product parity with historical Nusszopf, so that "1.0 stable" is not implied before parity is proven. All ten feature slices are implemented and the finish-line phases are in progress. **Not yet settled, and owned by P-16:** the number of the first tag. The register says `0.x` until parity, `release-process.md` suggests `0.1.0-rc.1` for the first tag, and roadmap phase P-16 names `1.0.0-rc.N` tags; the maintainer chooses when P-16 starts (`docs/release/parity/README.md`, "Carried forward to later phases").

## Tag format

**Adopted from Waffle Dashboard (Confirmed reference behavior)**: Git tags carry no `v` prefix — `1.2.0`, not `v1.2.0` — for consistency with the reference project. This is a convention choice, not a technical constraint; it is recorded as a decision (`docs/rewrite/architecture-decisions.md`) so it isn't silently reversed later by a contributor used to `v`-prefixed tags. The release workflow triggers on unprefixed tags only.

## Where the version is stored

Unlike Waffle Dashboard (which has no version constant anywhere in the codebase), Nusszopf exposes its running version to operators, since self-hosters need to know what they're running without inspecting Docker image digests. **Adopted and implemented** (`docs/rewrite/architecture-decisions.md`, "Version exposure to operators"):

- The Git tag is the single source of truth for the version number.
- The version is baked into the Docker image at build time (the `NUSSZOPF_VERSION` build argument becomes an environment variable and the `org.opencontainers.image.version` label), so `docker image inspect`, `php artisan about` and `php artisan nusszopf:health` (and `/health` with the token) all report it without a database round-trip. An image built from a working copy reports `dev`.
- There is no hand-maintained `VERSION` file: CI derives the version from the Git tag at build time.

## Compatibility policy

What is defined now, from P-9 and P-10 (`upgrades.md`, "Compatibility rules for release authors"); it is refined once real minor releases exist:

- Migrations must be additive and forward-only within a MAJOR version; a MAJOR bump is the only place a migration may require operator action beyond running it.
- Docker image tags must be immutable per version (never re-pushed under the same tag) — see [`docker-images.md`](docker-images.md).

## Status

Decided: scheme, tag format, `0.x` until parity, and version exposure (`docs/rewrite/architecture-decisions.md`). The compatibility policy above is the working rule; it has been exercised on populated data (P-9) but not yet across two real tags (P-16).
