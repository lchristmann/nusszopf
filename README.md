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

Self-hosting (Docker and a domain name; nothing else to install):

```sh
mkdir /opt/nusszopf && cd /opt/nusszopf
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh
sh install.sh https://nusszopf.example.org && docker compose up -d
```

See `docs/deployment/README.md` for the details (reverse proxy, configuration) and `docs/deployment/operations.md` for running it (health, upgrades, recovery).

## Development

The development workflow is Docker-based and designed to be familiar to developers working on LCxHolz.

## Testing

See `docs/testing/README.md`.

## Architecture

See `docs/architecture/README.md`.

## Documentation

See `docs/README.md`.

Important specifications include design, domain, user journeys, search, authentication, email, and release process.

Start with `docs/rewrite/decisions-register.md` (what's decided vs. what needs a human call), `docs/rewrite/bugs.md` (classified historical defects), and `docs/rewrite/first-slice.md`, `docs/rewrite/second-slice.md` and `docs/rewrite/third-slice.md` (the implemented vertical slices: registration/login, search and project detail; the historical project creation wizard and edit screen; the project requests, Gesuche). Later slices (newsletter, password reset, social login, avatars, account deletion, analytics) are not implemented yet; `CHANGELOG.md` lists what has shipped.

## Self-hosting

Nusszopf is intended to be operated by its users. The reference deployment uses Docker Compose.

## Releases

Released versions, changelogs, upgrade instructions, and Docker images are documented under `docs/release/`.

## License

Nusszopf is free software, licensed under the **GNU General Public License v3.0 or later**
(`GPL-3.0-or-later`); see [`LICENSE`](LICENSE). Third-party components and their licenses are listed
in [`NOTICE`](NOTICE).

Nusszopf 2 is a reimplementation of the historical Nusszopf (`web-nusszopf`, `be-nusszopf`,
`emails-nusszopf`), which is also licensed under the GPL v3.0. Its design, copy and email templates
are reproduced here as derivative works under the same license; the original authors retain their
copyright in that material.
