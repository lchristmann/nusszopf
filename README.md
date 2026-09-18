# Nusszopf 2

> Revival and faithful reimplementation of the Nusszopf project.

<!-- Add technology badges here after confirming the final stack and CI/release targets. -->

Nusszopf is a free and open-source, self-hostable application.

The Nusszopf 2 rewrite aims to preserve the historical Nusszopf product — its functionality, domain, workflows, information architecture, and visual design — while replacing its obsolete implementation and infrastructure.

## Technology

- PHP 8.5
- Laravel 13
- PostgreSQL
- Blade
- Livewire 4
- Tailwind CSS 4
- Redis
- Meilisearch
- Pest
- Playwright
- Docker Compose
- GitHub Actions

## Quick start

See `README-DEV.md` for development setup.

See `docs/deployment/README.md` for self-hosting.

## Development

The development workflow is Docker-based and designed to be familiar to developers working on LCxHolz.

## Testing

See `docs/testing/README.md`.

## Architecture

See `docs/architecture/README.md`.

## Documentation

See `docs/README.md`.

Important specifications include design, domain, user journeys, search, authentication, email, and release process.

Start with `docs/rewrite/decisions-register.md` (what's decided vs. what needs a human call), `docs/rewrite/bugs.md` (classified historical defects), and `docs/rewrite/first-slice.md` (the proposed first implementation slice) — nothing described in this repository has been implemented yet; this is a specification and architecture baseline for the rewrite.

## Self-hosting

Nusszopf is intended to be operated by its users. The reference deployment uses Docker Compose.

## Releases

Released versions, changelogs, upgrade instructions, and Docker images are documented under `docs/release/`.

## License

See the repository license file.
