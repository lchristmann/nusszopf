# Nusszopf 2

> Revival and faithful reimplementation of the Nusszopf project.

[![CI](https://github.com/lchristmann/nusszopf/actions/workflows/ci.yml/badge.svg)](https://github.com/lchristmann/nusszopf/actions/workflows/ci.yml)
[![Security](https://github.com/lchristmann/nusszopf/actions/workflows/security.yml/badge.svg)](https://github.com/lchristmann/nusszopf/actions/workflows/security.yml)
[![License: GPL v3 or later](https://img.shields.io/badge/License-GPL--3.0--or--later-blue.svg)](LICENSE)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Blade](https://img.shields.io/badge/Blade-Template-F05340?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-Cache_%26_Queue-FF4438?logo=redis&logoColor=white)
![Meilisearch](https://img.shields.io/badge/Meilisearch-1.11-FF5CAA?logo=meilisearch&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-5-8A4182?logo=php&logoColor=white)
![Playwright](https://img.shields.io/badge/Playwright-E2E_Testing-2EAD33?logo=playwright&logoColor=white)
![Larastan](https://img.shields.io/badge/Larastan-Level_7-4F5B93?logo=php&logoColor=white)
![Laravel Pint](https://img.shields.io/badge/Laravel_Pint-Code_Style-FF2D20?logo=laravel&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-CI/CD-2088FF?logo=githubactions&logoColor=white)

Nusszopf is a free and open-source, self-hostable application.

The Nusszopf 2 rewrite aims to preserve the historical Nusszopf product — its functionality, domain, workflows, information architecture, and visual design — while replacing its obsolete implementation and infrastructure.

## Quick start

See `README-DEV.md` for development setup.

Self-hosting (Docker and a domain name; nothing else to install):

<!-- quickstart:start -->
<!-- Transcluded verbatim into site/index.html at build time (site/vite.config.js) — edit only here. -->
```sh
mkdir /opt/nusszopf && cd /opt/nusszopf
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh
sh install.sh https://nusszopf.example.org
# edit .env: MAIL_FROM_ADDRESS (required) and how mail is sent: Resend (MAIL_MAILER=resend, RESEND_API_KEY),
# recommended, or your own SMTP relay (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD)
docker compose up -d
```
<!-- quickstart:end -->

Until `1.0.0` is tagged, only release candidates exist, and GitHub's `releases/latest` does not point at a pre-release.
Install a candidate by naming it: `curl -fsSLO https://github.com/lchristmann/nusszopf/releases/download/1.0.0-rc.2/install.sh`,
then `sh install.sh https://nusszopf.example.org 1.0.0-rc.2` (`docs/deployment/README.md`).

See `docs/deployment/README.md` for the details (reverse proxy, mail, configuration) and `docs/deployment/operations.md` for running it (health, backups, upgrades, recovery, troubleshooting).

## Development

The development workflow is Docker-based and designed to be familiar to developers working on LCxHolz. Everything
runs in containers; the host needs Docker, Docker Compose and Git. See `README-DEV.md`.

## Testing

See `docs/testing/README.md`.

## Architecture

See `docs/architecture/README.md`.

## Documentation

See `docs/README.md`.

Important specifications include design, domain, user journeys, search, authentication, email, and release process.

Start with `docs/rewrite/decisions-register.md` (what's decided vs. what needs a human call), `docs/rewrite/bugs.md` (classified historical defects) and `docs/rewrite/README.md`, which lists the ten implemented vertical slices (registration and login through the public shell: Home, legal pages, error pages, SEO). All feature slices are done. The remaining work before the first release is the finish-line phases, whose evidence is in `docs/release/parity/README.md`.

## Self-hosting

Nusszopf is intended to be operated by its users. The reference deployment uses Docker Compose; `docs/deployment/README.md`
is the installation guide and `docs/deployment/operations.md` the operator's handbook (health checks, backups and restore,
upgrades and rollback, search recovery, troubleshooting).

## Releases

Released versions, changelogs, upgrade instructions, and Docker images are documented under `docs/release/`.

## Contributing, security and conduct

Contributions are welcome: see [`CONTRIBUTING.md`](CONTRIBUTING.md). Please report vulnerabilities privately as described
in [`SECURITY.md`](SECURITY.md). Everyone taking part is expected to follow the
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

Nusszopf is free software, licensed under the **GNU General Public License v3.0 or later**
(`GPL-3.0-or-later`); see [`LICENSE`](LICENSE). Third-party components and their licenses are listed
in [`NOTICE`](NOTICE); where the assets come from is in [`docs/legal/provenance.md`](docs/legal/provenance.md).

Nusszopf 2 is a reimplementation of the historical Nusszopf (`web-nusszopf`, `be-nusszopf`,
`emails-nusszopf`), which is also licensed under the GPL v3.0. Its design, copy and email templates
are reproduced here as derivative works under the same license; the original authors retain their
copyright in that material.
