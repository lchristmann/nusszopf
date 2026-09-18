# Waffle Dashboard Reference

Waffle Dashboard (`../foss-reference/waffle-dashboard`) is the reference for Nusszopf's FOSS project lifecycle: versioning, releases, Docker image publishing, contribution workflow, and self-hosting/operator documentation.

It is a single-maintainer Laravel + Filament application ("track waffle-eating achievements"). Its **product/domain behavior must never be copied** — only its release, packaging, and operator-facing process. Where noted below, Nusszopf should go beyond what Waffle Dashboard does, because Nusszopf targets LCxHolz-level engineering discipline (`.claude/rules/05-engineering-quality.md`), and Waffle Dashboard's release process is intentionally manual.

## Confirmed findings

### Versioning

- **Confirmed**: Semantic versioning (`MAJOR.MINOR.PATCH`), e.g. `1.0.0`, `1.1.0`, `1.2.0`, `1.3.0`, `2.1.0`, `2.1.1`, `2.2.0`, `2.3.0`, `2.4.0`, `2.5.0` (`git tag -l` in the reference repo).
- **Confirmed**: Git tags carry **no `v` prefix** (`1.0.0`, not `v1.0.0`).
- **Confirmed**: There is no version constant anywhere in the codebase (no `config/app.php` version key, no `VERSION` file). The version exists only as: (a) the Git tag, (b) the Docker image tag, (c) the GitHub Release title.
- **Confirmed**: No `CHANGELOG.md` file exists in the repository at all.

### Release commit convention

- **Confirmed**: Releases are bundled into a single commit that combines a Conventional-Commits-style type prefix with a `+release: vX.Y.Z, <human description>` suffix, e.g.:
  - `feat+release: v2.5.0, add custom user profile pictures`
  - `fix+release: v2.1.1, large image file uploads gracefully failing (validation by Filament shown in UI)`
  - `setup+release: v2.4.0, replace nginx with angie`
  - `d70d587 feat(lang)+docs+release: v2.3.0, add German localization and a language switch, document how to add new languages`
- The commit message's free-text description is the de facto changelog entry (there is no separate changelog file to keep in sync).

### Release process (fully manual, no CI/CD)

- **Confirmed**: There is no `.github/workflows` directory and no CI/CD automation of any kind in this repository. Every release step is a manual command run by the maintainer, documented in `DEVELOPER-DOCS.md` under "How to Release":
  1. `docker login` to Docker Hub.
  2. Set `VERSION=X.Y.Z` locally.
  3. Build the `php-fpm` image first: `docker build -f ./docker/deployment/php-fpm/Dockerfile -t leanderchristmann/waffle-dashboard:${VERSION} -t leanderchristmann/waffle-dashboard:latest .`
  4. Build the `nginx` image second (it depends on the php-fpm image via `--build-arg VERSION=${VERSION}`): `docker build --build-arg VERSION=${VERSION} -f ./docker/deployment/nginx/Dockerfile -t leanderchristmann/waffle-dashboard-nginx:${VERSION} -t leanderchristmann/waffle-dashboard-nginx:latest .`
  5. Manually smoke-test the new images locally by pointing the production `docker-compose.yaml` at the new version tag and running `docker compose up -d`.
  6. `docker push` both images, both tags (`${VERSION}` and `latest`).
  7. Commit the `docker-compose.yaml` version bump (using the release commit convention above).
  8. `git tag -a "${VERSION}" -m "Release ${VERSION}"` and `git push origin "${VERSION}"`.
  9. Manually create a GitHub Release from the tag via the GitHub UI, titled `X.Y.Z`, with a `## What's Changed` heading and a hand-written bullet list.
- **Confirmed**: Both images are tagged with the immutable version **and** a floating `latest` tag on every release.
- **Confirmed**: Registry is **Docker Hub**, under the maintainer's **personal namespace** (`leanderchristmann/...`), not an organization.

### Self-hosting / operator experience (`docs/WAFFLE-INSTALLATION-GUIDE.md`)

- **Confirmed**: The self-hoster never clones the repository. They download exactly two files directly from GitHub raw URLs: `docker-compose.yaml` and `.env.example` (renamed to `.env`). This keeps the operator surface tiny and independent of the source tree.
- **Confirmed**: Setup sequence: create a dedicated external Docker network, `docker compose up -d`, generate `APP_KEY` from inside the running `php-fpm` container, restart, then create the first admin user via an Artisan command (`php artisan make:filament-admin`).
- **Confirmed**: HTTPS is explicitly **not** built into the app's own Docker Compose file. The guide documents fronting it with a separate, independently-run reverse proxy (Nginx Proxy Manager recommended; Caddy/Traefik/manual Nginx mentioned as alternatives) that joins the same external Docker network. The app's own `web` service exposes a host port only until the reverse proxy takes over, after which the port mapping is commented out so the app is reachable only inside the Docker network.
- **Confirmed**: Backup strategy is documented as a two-part backup: `pg_dump`/`pg_restore` (via `docker compose exec postgres`) for the database, plus `tar` of the Laravel storage Docker volume for uploaded files. A ready-made `backup.sh` script (`docs/code/backup.sh`) timestamps each backup folder to the minute, and the guide shows how to schedule it with a root crontab entry and how to prune backups older than 30 days.
- **Confirmed**: Restore procedure is documented step-by-step: `pg_restore --clean --if-exists`, stop the app, wipe and re-extract the storage volume via a throwaway `ubuntu` container bind-mounting the named volume, restart, then run `php artisan optimize` to rebuild caches.
- **Confirmed**: Upgrade procedure is described as: edit the image version tag in `docker-compose.yaml`, then `docker compose down && docker compose up -d`. Data persistence across upgrades relies entirely on named Docker volumes. Database migrations are implied to run automatically on container start (this is an image/entrypoint concern, not documented explicitly in the guide — **Unknown** exactly where migrations are triggered from in this reference; Nusszopf should not assume this detail without independently designing its own entrypoint/migration strategy).

### Contribution (`docs/CONTRIBUTION-GUIDE.md`)

- **Confirmed**: Standard fork + pull request model. No CI gate is described for PRs (consistent with there being no GitHub Actions workflows at all). Contribution guidance is mostly about following Laravel/Filament conventions and the project's i18n process, plus how to edit the draw.io architecture diagrams.
- **Confirmed**: The project used a waterfall-style, document-driven process for its initial build (`1_REQUIREMENTS.md` → `2_DESIGN.md` → `3_ARCHITECTURE.md` → `4_IMPLEMENTATION.md`, based on the Fraunhofer IESE "Architecture Decomposition Framework" template) and switched to an agile, GitHub-Issues-driven process for feature work after the initial implementation. This document-then-issues split is a personal working style, not something to replicate for Nusszopf.

## What Nusszopf should adopt vs. go beyond (recommendations — Inferred, not copied)

Waffle Dashboard is a **useful minimum viable FOSS release process for a single-maintainer hobby project**, but several parts are intentionally manual in a way that does not meet the LCxHolz-level engineering bar Nusszopf targets (`CLAUDE.md` → Engineering quality). Recommendations, to be confirmed as architecture decisions rather than assumed silently:

- **Adopt**: unprefixed semantic version tags (`X.Y.Z`), two-image split for a PHP-FPM + web-server pair, immutable version tag + floating convenience tag per image, "download two files, no git clone" self-hosting onboarding, documented `pg_dump`/volume-`tar` backup+restore with a ready-made script and a cron recipe, "bump tag, `down`/`up`" upgrade flow, and documenting HTTPS/reverse-proxy as an explicit separate concern rather than baking a proxy into the shipped Compose file.
- **Go beyond (recommend, needs approval)**: automate the release build/push/tag/GitHub-Release steps with GitHub Actions instead of the maintainer running them by hand — this is exactly the kind of CI/CD automation `CLAUDE.md` calls for and that LCxHolz should be checked against (`docs/references/lcxholz.md`).
- **Go beyond (recommend, needs approval)**: maintain a real `CHANGELOG.md` (e.g. Keep a Changelog format) rather than relying solely on commit messages and hand-written GitHub Release notes, since Nusszopf's userbase (self-hosting operators upgrading across versions) needs a scannable upgrade-relevant history, not just a commit log.
- **Open question**: registry choice. Waffle Dashboard uses Docker Hub under a personal namespace. For a FOSS project built with GitHub Actions, GitHub Container Registry (`ghcr.io`) is a common alternative that avoids a separate Docker Hub credential and ties image provenance to the GitHub repo. This is an **architecture decision requiring approval** — see `docs/rewrite/architecture-decisions.md`.
- **Open question**: whether Nusszopf ships one combined image or Waffle Dashboard's two-image (app + web-server) split — depends on the final Docker architecture decided against `docs/references/laravel-docker-examples.md` and LCxHolz.

## Unknown

- Exactly how/where Waffle Dashboard's production image triggers database migrations on startup (not documented in the installation guide; would require inspecting the Dockerfile/entrypoint, which is out of scope for the FOSS-lifecycle archaeology — infrastructure archaeology owns this).
- Whether Waffle Dashboard has ever shipped a breaking change requiring a documented manual migration step beyond "bump the tag" (no evidence found of a documented breaking-change procedure).
