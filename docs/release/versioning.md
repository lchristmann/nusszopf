# Versioning

## Scheme

Semantic versioning: `MAJOR.MINOR.PATCH`.

- **MAJOR**: breaking changes for operators — required manual configuration/migration steps beyond "pull new image, run migrations", removed environment variables, removed features, or incompatible data migrations. See [`breaking-changes.md`](breaking-changes.md).
- **MINOR**: new user-visible functionality, backward compatible.
- **PATCH**: bug fixes, security fixes, and other backward-compatible corrections.

**Recommended (Inferred, needs approval)**: unlike Waffle Dashboard, Nusszopf should start at `0.x.y` while the rewrite is still reaching product parity with historical Nusszopf, and only cut `1.0.0` once the recommended first vertical slice (and subsequent slices) have reached parity for the features they cover. This avoids implying "1.0 stable" before historical parity is real. This must be confirmed as an architecture decision, not assumed silently.

## Tag format

**Adopted from Waffle Dashboard (Confirmed reference behavior)**: Git tags carry no `v` prefix — `1.2.0`, not `v1.2.0` — for consistency with the reference project. This is a convention choice, not a technical constraint; record it as a decision (`docs/rewrite/architecture-decisions.md`) so it isn't silently reversed later by a contributor used to `v`-prefixed tags.

## Where the version is stored

Unlike Waffle Dashboard (which has no version constant anywhere in the codebase), Nusszopf should expose its running version to operators, since self-hosters need to know what they're running without inspecting Docker image digests. Recommended:

- The Git tag is the single source of truth for the version number.
- The version is baked into the Docker image at build time (e.g. as a build arg written to a file read by the application, or as an OCI image label) so `docker inspect` and an in-app "About"/health page can both report it without a database round-trip.
- No separate `VERSION` file needs to be hand-maintained if CI derives the version from the Git tag at build time.

This is a **recommendation requiring approval** — see `docs/rewrite/architecture-decisions.md`.

## Compatibility policy

To be defined once the first minor releases exist. At minimum:

- Migrations must be additive and forward-only within a MAJOR version; a MAJOR bump is the only place a migration may require operator action beyond running it.
- Docker image tags must be immutable per version (never re-pushed under the same tag) — see [`docker-images.md`](docker-images.md).

## Status

Confirmed: scheme and tag format follow Waffle Dashboard's precedent. Everything else on this page is a recommendation pending approval — see `docs/rewrite/architecture-decisions.md`.
