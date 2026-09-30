# Docker Images

## Recommendation for Nusszopf (reasoning; decided and implemented below)

- **Automate build and publish in CI** (GitHub Actions), triggered on pushing a version tag, rather than the maintainer running `docker build`/`docker push` by hand. This is the concrete instance of "engineering discipline" (`CLAUDE.md`) that a manual process does not meet.
- **Registry**: **decided 2026-09-21 — GHCR, `ghcr.io/lchristmann/nusszopf-*`** (see `docs/rewrite/decisions-register.md`). Original open question: Docker Hub (matching the reference) vs. GitHub Container Registry (`ghcr.io`, ties images to the repo/CI provenance with no extra credential to manage). Needs an explicit architecture decision — see `docs/rewrite/architecture-decisions.md`.
- **Image layout**: whether Nusszopf ships two images (app + web server, as in the Laravel Docker examples) or a single combined image depends on the Docker architecture decided in `docs/references/laravel-docker-examples.md`. Do not decide this here in isolation.
- **Tagging**: adopt immutable version tag + floating `latest` (matches the reference and is the de facto standard). Additionally consider a floating major-version tag (e.g. `1`) once Nusszopf has a stable `1.x` line, so operators can pin to "any 1.x" without manually bumping patch releases — this is an enhancement over the reference, not something it demonstrates, so mark it as a recommendation.
- **Multi-arch**: Given self-hosters increasingly run on ARM (e.g. Hetzner ARM instances, Raspberry Pi home servers), building multi-arch images in CI (`docker buildx`) is a recommended improvement.
- **Provenance/security metadata**: no evidence of SBOM/provenance attestation in the reference. Recommended as a "nice to have" if CI tooling makes it low-friction (e.g. `docker/build-push-action` with `provenance: true`), not a hard requirement.

## Implemented (operational track O-1, 2026-09-22)

Two images, built in CI on a release tag for `linux/amd64` and `linux/arm64`: `ghcr.io/lchristmann/nusszopf-php-fpm` (also the queue worker and scheduler) and `ghcr.io/lchristmann/nusszopf-web` (nginx with the same
build's assets). Tags: the immutable version and `latest` (not for pre-releases). Labels: the version, source repository, `GPL-3.0-or-later`. The version is also baked in as `NUSSZOPF_VERSION`.
No floating major tag yet (nothing is `1.x`), no SBOM/provenance attestation (nice to have, not required). See `release-process.md` for the workflow.

## Status

Nusszopf's choices are decided (GHCR, two images, immutable tag plus `latest`, CI-built multi-arch; `docs/rewrite/decisions-register.md`, `docs/rewrite/architecture-decisions.md`) and implemented in `.github/workflows/release.yml`. The workflow has run on `1.0.0-rc.1` and `1.0.0-rc.2` (P-16): both images exist for `linux/amd64` and `linux/arm64`, carry the version label, can be pulled without logging in (the repository is public) and start, natively on both architectures; a pre-release tag does not move `latest`. The multi-arch build takes about 40 minutes on GitHub's runners. The images were also built and exercised locally throughout P-7…P-13.
