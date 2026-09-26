# Nusszopf — Development Guide

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

## Requirements

- Docker
- Docker Compose
- Git

No local PHP, Composer, Node, PostgreSQL, Redis, or Meilisearch installation is required. Every one of
these runs inside a container; PHP/Composer/Artisan/Node/npm commands always run via
`docker compose -f compose.dev.yaml exec workspace ...`.

## Getting started (first run)

```shell
git clone <repo> && cd nusszopf
cp .env.example .env               # set UID/GID to `id -u`/`id -g` if they are not 1000:1000
sed -i "s|^MEILISEARCH_KEY=.*|MEILISEARCH_KEY=nusszopf-dev-master-key-change-me|" .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(head -c 32 /dev/urandom | base64)|" .env
docker compose -f compose.dev.yaml up -d --build
docker compose -f compose.dev.yaml exec -u root workspace chown "$(id -u):$(id -g)" /var/www/vendor /var/www/node_modules
docker compose -f compose.dev.yaml exec workspace composer install
docker compose -f compose.dev.yaml exec workspace npm install
docker compose -f compose.dev.yaml exec workspace php artisan storage:link
docker compose -f compose.dev.yaml exec workspace php artisan migrate --seed
docker compose -f compose.dev.yaml exec workspace php artisan search:reindex
docker compose -f compose.dev.yaml exec workspace npm run build
```

Three of these lines are there because of how the development stack is set up, not because of Nusszopf:

- **`APP_KEY`, before the first `up`.** Compose hands `.env` to every container as its environment, and an empty `APP_KEY`
  there wins over a key that `php artisan key:generate` writes into the file afterwards: the containers would keep
  running without a key until they are recreated. So the key is generated first.
- **`MEILISEARCH_KEY`.** `.env.example` leaves it empty. The `meilisearch` service then falls back to the master key
  `nusszopf-dev-master-key-change-me`, but the application reads the empty value from `.env` and sends no key, so
  every search command is refused for a missing `Authorization` header. Setting the key in `.env` gives both the same one.
  The `meilisearch`-group tests and the Playwright search specs need it too.
- **`chown`.** `vendor` and `node_modules` are named volumes, and a new Docker volume belongs to root. `composer install`
  then stops with `Permission denied`. The `chown` (as root, once) hands both to your user. If you skip it, run
  `composer install` and `npm install` with `exec -u root`, as CI does, and expect to need root again later.

`search:reindex` applies the index settings and fills the index in one step (`scout:sync-index-settings` alone applies
only the settings).

Open <http://localhost:8080> (or your `APP_PORT`) and register a normal account through the
registration screen — there is no separate admin/first-user bootstrap step. Nusszopf has no
admin/staff role anywhere in the historical product; every account is an ordinary equal-privilege
user, in development exactly as in production. Mail sent by the application (welcome, verification, password
reset, contact, newsletter) does not leave the stack: the Mailpit catcher shows it at <http://localhost:8025>.

If your host UID/GID aren't `1000:1000`, set `UID`/`GID` in `.env` to match `id -u`/`id -g` before the
first `up`, so files the containers create are owned by you, not root.

## Common commands

| Concern | Command |
|---|---|
| Enter dev container | `docker compose -f compose.dev.yaml exec workspace bash` |
| Artisan | `docker compose -f compose.dev.yaml exec workspace php artisan <command>` |
| Composer | `docker compose -f compose.dev.yaml exec workspace composer <command>` |
| Frontend dev server | `docker compose -f compose.dev.yaml exec workspace npm run dev` |
| Frontend build | `docker compose -f compose.dev.yaml exec workspace npm run build` |
| Formatting (apply) | `composer pint` |
| Formatting (check, matches CI) | `composer lint:check` |
| Static analysis | `composer larastan` |
| Unit/Feature tests | `composer test` |
| Browser/E2E tests | `docker compose -f compose.dev.yaml exec playwright npx playwright test` (or `npm run test:e2e:chromium`/`:firefox`/`:webkit` for one engine, `npm run test:e2e:devices` for the emulated phones and tablet) |
| Production images: operator checks | `sh scripts/smoke-test.sh` (builds the images, installs, starts, checks) |
| Production images: whole browser suite | `sh scripts/prod-e2e.sh` (the same stack plus Mailpit and the LocationIQ stub; `PROD_E2E_KEEP=1` keeps it running) |
| Upgrade from an earlier release, on populated data | `sh scripts/upgrade-test.sh <previous tag> [--suite]` (a release step, not CI; `docs/testing/README.md`) |
| Backup/restore drill onto an empty host, and the rollback of an upgrade | `sh scripts/restore-test.sh [--suite] [--rollback-from <previous tag>]` (a release step, not CI; `docs/testing/README.md`) |
| Search index recovery drill | `sh scripts/search-recovery-test.sh` (a release step, not CI; `docs/testing/README.md`) |
| Queue and scheduler drill (worker, Redis, scheduler and `search:reindex` under failure; about an hour) | `sh scripts/queue-scheduler-test.sh` (a release step, not CI; `docs/testing/README.md`) |
| Real mail through the production stack (sends seven real messages to a mailbox you name; a release step, not CI) | `P13_RECIPIENT=you@example.org sh scripts/mail-delivery-test.sh` (`docs/testing/README.md`) |
| Migrations | `php artisan migrate` |
| Fresh DB + seed | `php artisan migrate:fresh --seed` |
| Sync search index settings | `php artisan scout:sync-index-settings` |
| Rebuild the search index (settings + all documents) | `php artisan search:reindex` |
| Queue worker (dev) | already running as the `queue-worker` Compose service |
| Scheduler (dev) | already running as the `scheduler` Compose service |
| Logs | `docker compose -f compose.dev.yaml logs -f [service]` |

Composer/Artisan commands in the table run inside `workspace` — prefix with
`docker compose -f compose.dev.yaml exec workspace` if not already inside the container shell.

## Services (`compose.dev.yaml`)

`web` (nginx) · `php-fpm` · `workspace` (contributor shell) · `queue-worker` (`queue:listen`, so code changes apply
without a restart) · `scheduler` · `postgres` · `redis` · `meilisearch` · `mailpit` (catches every mail; web UI on
port 8025, `MAIL_HOST=mailpit`; development and CI only, never production) · `locationiq-stub` (dev/CI stand-in for the
project-location autocomplete API; set `LOCATIONIQ_KEY`/`LOCATIONIQ_URL` in `.env` to use the real one) · `playwright`
(Debian-based, dev/CI-only — the app's own Alpine-based images can't run Playwright's bundled browsers reliably).

The operator's stack (`docker-compose.yaml`, images only) is a different file with the same services minus `workspace`,
`mailpit`, `locationiq-stub` and `playwright`; `docs/deployment/README.md` describes it, and the scripts in the table above run it.

## Testing

Run the complete local quality suite before opening a pull request, in this order:

```shell
docker compose -f compose.dev.yaml exec workspace composer lint:check
docker compose -f compose.dev.yaml exec workspace composer larastan
docker compose -f compose.dev.yaml exec workspace composer test
docker compose -f compose.dev.yaml exec playwright npx playwright test
```

See `docs/testing/README.md` for the full testing strategy and `docs/development/quality.md` for
what each gate checks. The Pest suite runs against its own database, queue and search index
(`nusszopf_testing`, `testing_items`), never the development ones. The full Playwright run needs some settings
(`SEARCH_PAGE_SIZE=5` in `.env`, and `E2E_*` variables) and shares a per-address newsletter budget across engines; both
are in `docs/testing/README.md`.

## Troubleshooting

Only problems that were actually hit.

| Symptom | Cause and fix |
|---|---|
| `composer install` or `npm install` fails with `Permission denied` in `vendor` or `node_modules` | The named volumes are root-owned: `docker compose -f compose.dev.yaml exec -u root workspace chown "$(id -u):$(id -g)" /var/www/vendor /var/www/node_modules` |
| Search commands or the `meilisearch`-group tests are refused for a missing `Authorization` header | `MEILISEARCH_KEY` in `.env` is empty; set it as in "Getting started", then `docker compose -f compose.dev.yaml up -d` (a container keeps the environment it was created with) |
| `No application encryption key has been specified` after a first run that generated the key late | The containers were created with an empty `APP_KEY`; `docker compose -f compose.dev.yaml up -d --force-recreate` (with the key already in `.env`) |
| A changed `.env` has no effect | Recreate the containers: `docker compose -f compose.dev.yaml up -d`. `restart` keeps the old environment |
| Avatars answer 404 | `php artisan storage:link` (once; `public/storage` is git-ignored) |
| The newsletter or password-reset specs fail on a second full run within 15 minutes | The per-address rate-limit budget is spent; `php artisan cache:clear`, then run again (`docs/testing/README.md`) |
| A search spec fails only in a parallel run of several engines | The index-recovery spec wipes the one shared index; run engines one at a time locally (CI runs one engine per job) |
| The visual suite, or `PerformanceDatasetSeeder`, replaced your development data | Both reset the development database on purpose; `php artisan migrate:fresh --seed` returns to the ordinary seed |

## Claude Code

Claude Code should start by reading `CLAUDE.md`, `.claude/rules/*`, relevant `docs/*`, and historical
evidence under `../historical/`.

Reference projects:

- `../development-reference/lcxholz`
- `../foss-reference/waffle-dashboard`
- `../infrastructure-reference/laravel-docker-examples`

## Development principle

Nusszopf's product behavior comes from historical Nusszopf.

Its developer experience should be familiar to LCxHolz.

Its FOSS lifecycle should learn from Waffle Dashboard.

Its Docker foundation should learn from the official Laravel Docker examples.
