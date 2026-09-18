# Nusszopf — Development Guide

This guide is intentionally structured similarly to the LCxHolz developer experience.

## Requirements

- Docker
- Docker Compose
- Git
- Claude Code recommended

No local PHP, Composer, Node, PostgreSQL, Redis, or Meilisearch installation should be required for the standard Docker workflow unless explicitly documented.

## Getting started

The canonical development workflow should be Docker-based. Exact service names and commands should be finalized once the Docker architecture is implemented.

## Common commands

Document one canonical command for each: Artisan, Composer, frontend assets, tests, Playwright, formatting, static analysis, database reset, migrations, queues, scheduler, and logs.

## Testing

Run the complete local quality suite before opening a pull request. See `docs/testing/README.md`.

## Claude Code

Claude Code should start by reading `CLAUDE.md`, `.claude/rules/*`, relevant `docs/*`, and historical evidence under `../historical/`.

Reference projects:

- `../development-reference/lcxholz`
- `../foss-reference/waffle-dashboard`
- `../infrastructure-reference/laravel-docker-examples`

## Development principle

Nusszopf's product behavior comes from historical Nusszopf.

Its developer experience should be familiar to LCxHolz.

Its FOSS lifecycle should learn from Waffle Dashboard.

Its Docker foundation should learn from the official Laravel Docker examples.
