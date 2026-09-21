# Docker Images

## Reference finding (Waffle Dashboard, Confirmed)

- Two images per release, built in a fixed dependency order: a PHP-FPM application image first, then an Nginx web image second (the web image's build takes `--build-arg VERSION=${VERSION}` and is only built once the app image exists).
- Registry: Docker Hub, personal namespace: `leanderchristmann/waffle-dashboard` and `leanderchristmann/waffle-dashboard-nginx`.
- Tagging: every release pushes **two tags per image** — the immutable version (`2.5.0`) and the floating `latest`.
- Build/push is entirely manual (`docker login`, `docker build`, `docker push`, run by the maintainer locally). There is no CI/CD.
- The production `docker-compose.yaml` pins both services to an explicit version tag (never `latest`), so operators control their own upgrade timing — `latest` exists only as a convenience default for people setting up for the first time without picking a version.

## Recommendation for Nusszopf (Inferred, needs approval)

- **Automate build and publish in CI** (GitHub Actions), triggered on pushing a version tag, rather than the maintainer running `docker build`/`docker push` by hand. This is the concrete instance of "engineering discipline" (`CLAUDE.md`) that should improve on Waffle Dashboard's manual process. Confirm the exact workflow shape against `docs/references/lcxholz.md`'s CI findings.
- **Registry**: **decided 2026-09-21 — GHCR, `ghcr.io/lchristmann/nusszopf-*`** (see `docs/rewrite/decisions-register.md`). Original open question: Docker Hub (matching the reference) vs. GitHub Container Registry (`ghcr.io`, ties images to the repo/CI provenance with no extra credential to manage). Needs an explicit architecture decision — see `docs/rewrite/architecture-decisions.md`.
- **Image layout**: whether Nusszopf ships two images (app + web server, mirroring Waffle Dashboard and the Laravel Docker examples split) or a single combined image depends on the Docker architecture decided in `docs/references/laravel-docker-examples.md` and the LCxHolz comparison in `docs/references/lcxholz.md`. Do not decide this here in isolation.
- **Tagging**: adopt immutable version tag + floating `latest` (matches the reference and is the de facto standard). Additionally consider a floating major-version tag (e.g. `1`) once Nusszopf has a stable `1.x` line, so operators can pin to "any 1.x" without manually bumping patch releases — this is an enhancement over the reference, not something it demonstrates, so mark it as a recommendation.
- **Multi-arch**: Waffle Dashboard's guide gives no evidence of multi-platform builds (`linux/amd64` vs `linux/arm64`). Given self-hosters increasingly run on ARM (e.g. Hetzner ARM instances, Raspberry Pi home servers), building multi-arch images in CI (`docker buildx`) is a recommended improvement, not a copy of reference behavior.
- **Provenance/security metadata**: no evidence of SBOM/provenance attestation in the reference. Recommended as a "nice to have" if CI tooling makes it low-friction (e.g. `docker/build-push-action` with `provenance: true`), not a hard requirement.

## Status

Confirmed: two-image split, registry+tag pattern, and manual process as practiced by Waffle Dashboard. All CI/registry/multi-arch recommendations are Inferred and require approval before being treated as Nusszopf's actual architecture.
