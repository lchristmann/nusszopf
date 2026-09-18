# LCxHolz Reference

**Scope of this document:** engineering discipline, developer experience, Docker workflow, testing, CI, and documentation structure only. LCxHolz's e-commerce domain (products, cart, checkout, Stripe, withdrawals, complaints, Matomo analytics) is out of scope and must never be copied into Nusszopf. Everything below is **Confirmed** by direct inspection of `../development-reference/lcxholz` unless marked otherwise.

## 1. Stack shape (process-relevant parts only)

PHP 8.5, Laravel 13, Blade, Livewire 4, Tailwind CSS 4, Pest, Playwright, Laravel Pint, Larastan (PHPStan), Docker Compose, GitHub Actions, Laravel Boost. LCxHolz additionally uses MySQL, Filament (admin panel), Stripe, Resend and Matomo — these are domain/product choices Nusszopf does not inherit; Nusszopf's target stack substitutes PostgreSQL, Redis and Meilisearch instead.

## 2. Onboarding flow (clone → running app)

LCxHolz's `README-DEV.md` "Getting Started" is a strictly ordered, copy-pasteable sequence:

1. `git clone ...` / `cd lcxholz`
2. `cp .env.example .env`, align `UID`/`GID` in `.env` with `id -u` / `id -g` (Linux bind-mount permissions)
3. `docker compose -f compose.dev.yaml up -d --build` (first run), `up -d` thereafter
4. Enter the `workspace` container, `composer install`, `php artisan key:generate`
5. `npm install`, `npm run dev` (Vite on `:5173`)
6. `php artisan migrate`, `php artisan db:seed`
7. `php artisan storage:link`
8. Create the first admin/super-user via an Artisan command
9. Open the app and the admin panel URLs

Nusszopf should reproduce this shape exactly: a numbered, linear "Requirements → Getting Started" section in `README-DEV.md` that a Laravel newcomer can follow without deviation, with every command runnable verbatim. No local PHP/Composer/Node/Postgres/Redis/Meilisearch install should ever be required — every one of those tools is only ever invoked inside a container.

## 3. One canonical command per concern (Confirmed set from LCxHolz)

| Concern | LCxHolz command | Notes |
|---|---|---|
| Enter dev container | `docker compose -f compose.dev.yaml exec workspace bash` | All PHP/Composer/Node/npm tooling runs here, never on host |
| Artisan | `docker compose -f compose.dev.yaml exec workspace php artisan <cmd>` | |
| Formatting (apply) | `composer pint` (→ `vendor/bin/pint --parallel`) | |
| Formatting (check, CI) | `composer lint:check` (→ `pint --parallel --test`) | |
| Static analysis | `composer larastan` (→ `composer types:check` → `phpstan analyse --memory-limit=1G`) | Larastan level **7** in this reference (`phpstan.neon`) |
| Feature/unit tests | `composer test` (→ `artisan config:clear` then `artisan test`) | `composer test:parallel` for Paratest |
| Browser/E2E tests | `npm run test:e2e[:chromium\|:firefox\|:webkit\|:mobile-chrome]` → `playwright test --project=<name>` | Run from inside `workspace`, never on host |
| Frontend dev server | `npm run dev` (Vite) | |
| Frontend build | `npm run build` | |
| Migrations | `php artisan migrate` / `migrate:fresh --seed` | |
| Queue worker | `php artisan queue:work` (prod) / `queue:listen` (dev) | |
| Scheduler | `php artisan schedule:work` (no host cron needed) | |
| Logs | `docker compose -f compose.dev.yaml logs -f [service]` | |
| "Everything at once" dev loop | `composer dev` → `concurrently` running serve+queue+scheduler+`pail` (log tailer)+vite | |
| Local pre-commit check | `composer pint && composer larastan && composer test` (documented as three explicit commands, matching CI) | |

**Recommendation for Nusszopf:** adopt this exact table shape (one row per concern, one canonical command) in `README-DEV.md`, substituting Nusszopf's actual services (`meilisearch`, `redis` in place of Matomo/Stripe-specific rows) once the Docker architecture is designed. Do not invent extra commands beyond what each concern needs.

## 4. Docker dev/prod architecture (Confirmed)

- Built on the official Laravel Docker Examples as a foundation (see `docs/references/laravel-docker-examples.md`), not reinvented.
- **Same Dockerfiles, different targets.** `docker/common/php-fpm/Dockerfile` has a multi-stage build with `development` and `production` targets; `compose.dev.yaml` builds the `development` target, `compose.prod.yaml` pulls/builds the `production` target. Both environments therefore share the same base image layers — dev only adds Xdebug and writable bind mounts on top.
- **Dev services:** `web` (nginx, bind-mounted config, live code mount), `php-fpm` (dev target, live code mount, host UID/GID matched via build args to avoid permission issues), `workspace` (Composer+Node+npm+Artisan, the only container a developer execs into), `mysql`, plus dev-only conveniences (`dbgate` for DB browsing, `mailpit` for mail capture). Nusszopf's dev compose should follow the same pattern but substitute PostgreSQL for the DB service and add `redis` and `meilisearch` as dev-only conveniences with a web UI or CLI for search inspection where practical.
- **Prod services:** `web` (built image, no bind mounts, no host port — reachable only through a shared reverse-proxy network via `external: true`), `php-fpm` (built image, health-checked via `php-fpm-healthcheck`), `queue-worker` and `scheduler` (same image as `php-fpm`, different `command:`, so no separate Dockerfile needed), `mysql`.
- **Image build & tagging:** production images (nginx and php-fpm) are built in CI, pushed to GHCR under two tag schemes simultaneously — `latest` (default branch only), `sha-<short-sha>`, and `vX.Y.Z` (on tag push) — so a single tag string unambiguously identifies one deployable version of the whole stack.
- **"Build Once, Deploy Many."** The server (`compose.prod.yaml`) only ever runs `docker compose pull` + `up -d`; it never runs `composer install`, `npm run build`, or `docker compose build`. `deploy.sh <tag>` is the one manual, SSH-run command that performs a deploy or a rollback (rollback = redeploying the previous tag). Migrations run automatically via the php-fpm production entrypoint on container start, not as a separate manual step.
- **Named volumes**, not bind mounts, hold production persistent data (`storage`, DB data, built public assets synced from the image on each boot so nginx and php-fpm always see the same asset hashes).
- **Health checks** gate startup ordering everywhere (`depends_on: condition: service_healthy`), not just informationally — e.g. `web` waits on `php-fpm`, `php-fpm`/queue/scheduler wait on the DB.

**Recommendation for Nusszopf:** adopt the multi-stage single-Dockerfile-per-service, dev/prod-share-the-same-image, "Build Once Deploy Many", health-check-gated-startup pattern wholesale — it is stack-agnostic engineering discipline, not LCxHolz product logic. Swap MySQL→PostgreSQL and add Redis+Meilisearch as first-class services in both compose files (Meilisearch is part of the deploy target, not dev-only, since search is a real product feature).

## 5. CI pipeline (Confirmed, `.github/workflows/ci.yml`)

Single workflow, four independent jobs plus one dependent job, triggered on push to `main`, on tags `v*`, and on PRs to `main`:

1. **`pint`** — `composer lint:check`. PHP setup, Composer cache, install, run. No DB needed.
2. **`larastan`** — `composer types:check`. Same setup pattern.
3. **`pest`** — PHP + Node setup, installs, builds frontend, runs `vendor/bin/pest --parallel --coverage --coverage-cobertura=... --log-junit=...`. Uses **SQLite in-memory**, no MySQL service container, because the Feature suite is written against SQLite. Publishes JUnit results, uploads coverage to Codecov, generates a coverage badge into the job summary.
4. **`playwright`** — matrix job, one leg per browser engine (`chromium`, `firefox`, `webkit`, `mobile-chrome`), `fail-fast: false`, each with its **own MySQL service container** (real DB needed because it drives a real running app server via `php artisan serve`). Installs only the browser binaries that leg needs (plus chromium always, since global setup's sign-in step hardcodes it). Runs migrations, boots the server, waits for a health endpoint via `curl` polling with a timeout, then runs `playwright test --project=<leg>`. Uploads the HTML report as an artifact per leg.
5. **`docker-publish`** — `needs: [pint, larastan, pest, playwright]`, gated on `github.event_name == 'push'` (never runs for PRs, so no image is ever built from unreviewed code). Builds and pushes both production images to GHCR with the tag scheme from §4.

**Design points worth adopting:**
- Splitting Playwright into one matrix job per browser (not one serial job looping over browsers) keeps wall-clock CI time close to a single-browser run.
- Static-analysis and lint jobs need no database and run fastest — they should fail fast, in parallel with everything else, not serially before it.
- `webkit`'s CI headless build is documented (via an inline comment, not silently) as more CPU-hungry per instance on constrained CI runners, so its matrix leg intentionally runs fewer Playwright workers than the other engines — a concrete example of tuning parallelism per engine rather than using one blanket setting.
- The image-publish job is the only one gated behind all quality jobs passing **and** behind "this is a push, not a PR" — both conditions together are what makes "Build Once" trustworthy.

**Recommendation for Nusszopf:** mirror this job shape (lint / static-analysis / unit+feature / browser-matrix / publish-gated-on-all), but do not assume MySQL-vs-SQLite as the split rationale — decide fresh once Nusszopf's own Postgres-only stance is settled (Postgres has an in-memory-equivalent test story via a disposable test database or SQLite-compatible schema choices; this needs its own decision, not a copy of LCxHolz's SQLite shortcut, since Nusszopf's target DB is PostgreSQL specifically and Postgres-only features must be tested against Postgres, not SQLite).

## 6. Testing pyramid (Confirmed, `docs/testing/README.md` + `phpunit.xml` + `playwright.config.ts`)

- **Unit tests** (`tests/Unit/`) — isolated, sufficiently complex business logic only (enums, policies, small support classes). Not the bulk of the suite.
- **Feature tests** (`tests/Feature/`) — the bulk of coverage: CRUD, authorization, checkout/business logic, validation, persistence, jobs, mail, notifications. Explicitly the level where "business rules should primarily be covered", not duplicated into E2E.
- **Playwright E2E tests** (`tests/E2E/`) — "a small number of important customer journeys through the real UI", organized by actor (`specs/{guest,customer,admin,mobile}/`), using a Page Object Model (`pages/{storefront,admin}/`) and shared fixture/env constants (`support/env.ts`) so specs never hardcode IDs inline. A `global-setup.ts` resets the DB (`migrate:fresh --seed` + a dedicated `E2ESeeder`) once before the whole run, and one-time signs in each role, saving Playwright `storageState` so individual specs don't repeat login.
- Both suites are written against a shared source of truth: `docs/journeys/` — the acceptance journeys are not duplicated in each test file's head comment, they're referenced.
- Coverage is measured (`pcov`, Cobertura + HTML output, Codecov upload, a generated markdown badge) but not gated on a hard threshold in this reference (`fail_below_min: false`) — coverage is observed, not enforced by a hard gate.
- E2E fixture identifiers are declared in exactly one file (`tests/E2E/support/env.ts`) and imported everywhere else, so seed data drift is caught in one place.
- Critical interactive elements carry stable `data-testid` attributes for Playwright to target; where a UI library doesn't expose those (LCxHolz's Filament admin), specs fall back to accessible roles/labels instead of brittle CSS selectors.

**Recommendation for Nusszopf:** adopt the Unit/Feature/Browser split with the same weighting (Feature-heavy, E2E-thin-and-targeted), the Page Object Model + single fixture-constants file pattern for Playwright, `data-testid` on interactive elements matched against the historical UI wherever the historical DOM doesn't already give a stable, semantic hook, and a `docs/journeys/` file that both suites cite rather than restate. Treat coverage as observed/reported, not a hard merge gate, unless Nusszopf's own quality bar decides otherwise later (an open decision, not inherited automatically).

## 7. Documentation structure (Confirmed)

Top-level: `README.md` (product/stack/quick pointers, badges, short), `README-DEV.md` (the only place with actual dev commands, itself with a table of contents and split into "Getting Started" — read once, top to bottom — vs. everything else — reference material, jump to what you need). Deep-dive docs live under `docs/<topic>/README.md`, one topic per directory (`architecture`, `domain`, `admin`, `testing`, `journeys`, `deployment`, `backups`, `seo`, `matomo`, etc.), cross-linked from README-DEV's "Further Documentation" table rather than inlined. `docs/BASE-PROJECT-README.md` preserves the original scaffold-generated documentation verbatim instead of deleting it, once the project's own docs supersede it.

**Recommendation for Nusszopf:** the `docs/<topic>/README.md` one-topic-per-directory convention is exactly what Nusszopf's own `docs/` tree (see `docs/README.md`) already follows — keep it. Add a "Further Documentation" table to `README-DEV.md` once enough topic docs exist, linking each.

## 8. AI-assisted development configuration (Confirmed)

`.claude/`, `.mcp.json`, `CLAUDE.md`, `boost.json`, and `.ai/rules/` are all committed to the repository (not gitignored) so the AI development environment is identical for every contributor, not just the original author. `boost.json` declares exactly which Boost packages/skills are enabled (scoped to packages actually installed — Filament, Spatie Media Library, etc.); Boost is explicitly told not to install additional skills or packages beyond what the project actually uses. `.mcp.json` runs the Boost MCP server *inside* the `workspace` container via `docker compose exec`, so MCP tooling sees the same PHP/Composer/vendor state as manual commands, never a divergent host-side PHP. `.ai/rules/` holds narrow, path-scoped rules (one file per app area, each mapped to a glob in `.ai/rules/index.md`) recording settled decisions and non-obvious traps — a mechanism for accumulating project-specific knowledge over time rather than re-deriving it every session, distinct from `CLAUDE.md`'s repo-wide instructions.

**Recommendation for Nusszopf:** adopt Laravel Boost (already in Nusszopf's target stack per `CLAUDE.md`) the same way — committed config, MCP server proxied through the dev container, and an explicit "don't add skills/packages beyond what's installed" rule. Adopt `.ai/rules/`-style narrow rule files once Nusszopf's own app areas exist and start accumulating non-obvious, settled decisions worth recording per-path.

## 9. What Nusszopf should NOT copy from LCxHolz

- Filament as an admin-panel framework, Spatie Laravel Permission/Filament Shield as the authorization mechanism, Stripe Checkout, Resend, Matomo, MySQL — these are LCxHolz's own product/infrastructure choices, not engineering-discipline requirements. Nusszopf's authorization model, admin surface (if any), search engine (Meilisearch per `CLAUDE.md`), mail provider, and database (PostgreSQL per `CLAUDE.md`) must be decided from Nusszopf's own historical evidence and target stack, never defaulted to LCxHolz's picks.
- The specific business-domain `.ai/rules/*.md` files (`cartproducts.md`, `withdrawals.md`, `products.md`, etc.) — these encode LCxHolz's commerce domain and have no Nusszopf equivalent yet; only the *mechanism* (narrow, glob-scoped rule files) is worth reusing, not their content.
- SQLite-for-Pest-CI-speed as a rationale — worth re-deciding for Nusszopf specifically because the target database is PostgreSQL, and Postgres-specific behavior (JSON operators, full-text search, constraints) does not reproduce faithfully under SQLite.

## 10. Open questions this reference cannot answer

- Whether Nusszopf needs an admin/back-office surface at all is a historical-product question (see `be-nusszopf`/`web-nusszopf` archaeology), not something to infer from LCxHolz having a Filament panel.
- The exact CI/test split for a Postgres-only target stack (in-memory-equivalent test DB strategy) is an open architecture decision — record it in `docs/rewrite/architecture-decisions.md` once made, do not silently default to LCxHolz's SQLite approach.
