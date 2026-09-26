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
sh install.sh https://nusszopf.example.org
# edit .env: MAIL_FROM_ADDRESS (required) and how mail is sent: Resend (MAIL_MAILER=resend, RESEND_API_KEY),
# recommended, or your own SMTP relay (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD)
docker compose up -d
```

There is no published release yet, so that download does not work today (`docs/release/parity/P-08-fresh-install.md`, P8-01).
The first release publishes `install.sh` and the images.

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

## License

Nusszopf is free software, licensed under the **GNU General Public License v3.0 or later**
(`GPL-3.0-or-later`); see [`LICENSE`](LICENSE). Third-party components and their licenses are listed
in [`NOTICE`](NOTICE).

Nusszopf 2 is a reimplementation of the historical Nusszopf (`web-nusszopf`, `be-nusszopf`,
`emails-nusszopf`), which is also licensed under the GPL v3.0. Its design, copy and email templates
are reproduced here as derivative works under the same license; the original authors retain their
copyright in that material.
