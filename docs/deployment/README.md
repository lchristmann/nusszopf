# Deployment and Self-Hosting

> **Status: implemented and verified** (operational track O-1/O-2, 2026-09-22, then the finish-line phases). The install path, the images, the
> release workflow and the health checks below exist and are exercised by `scripts/smoke-test.sh` (run by CI on every
> change). Evidence: the whole Playwright suite on these images (P-7), a fresh install on a clean host from this page
> alone (P-8), upgrades on populated installations (P-9), the backup script and the restore onto an empty host, also as the
> rollback of an upgrade (P-10), the search index recovery (P-11), the queue, Redis and scheduler under failure (P-12) and
> real mail delivery through Resend (P-13). Not yet done, by roadmap: a real tag has never been published, so the release
> workflow, the GitHub download, the GHCR pull and arm64 are untested until the first one (P-16, with the repository
> going public in P-15).

## Table of contents

- [Design goal](#design-goal)
- [The files](#the-files)
- [Services](#services)
- [Why a dedicated nginx image, not a shared assets volume](#why-a-dedicated-nginx-image-not-a-shared-assets-volume)
- [Required configuration](#required-configuration)
- [Installation (operator path)](#installation-operator-path)
- [Reverse proxy and TLS](#reverse-proxy-and-tls)
- [Persistent storage](#persistent-storage)
- [Health checks](#health-checks)
- [Backups, upgrades, recovery](#backups-upgrades-recovery)
- [Decisions and what is still open](#decisions-and-what-is-still-open)

## Design goal

Per `CLAUDE.md` and `.claude/rules/06-self-hosting.md`: a person who has never seen the Nusszopf source code should be able to obtain a release, configure a small number of environment variables, start it, initialize it, verify it's healthy, back it up, and upgrade it — using documented, copyable shell commands, without installing PHP, Composer, Node, PostgreSQL, Redis, or Meilisearch on their own machine.

Docker Compose is the reference deployment. No Kubernetes, no orchestration platform, no SaaS control plane.

## The files

| File | Audience | What it is |
|---|---|---|
| `docker-compose.yaml` (repository root, attached to every release) | Operators | The whole stack, `image:`-only: no build context, nothing to compile. Which release runs is `NUSSZOPF_VERSION` in `.env` |
| `.env.production.example` (attached to every release with its version filled in) | Operators | Every setting of a production installation, the ones that must be set marked `REQUIRED` |
| `scripts/install.sh` (attached to every release) | Operators | Downloads the two files above, generates `APP_KEY` and the database, search and health secrets, writes `.env`. `install.sh --upgrade <version>` moves an existing installation to that release: it replaces the two files and sets `NUSSZOPF_VERSION`, keeping the rest of `.env` (`docs/deployment/operations.md`, "Upgrades") |
| `compose.prod.yaml` | Contributors / CI | An *override* that only adds `build:` to `web` and `php-fpm`: `docker compose -f docker-compose.yaml -f compose.prod.yaml up -d --build` runs the working copy's own images in the operator's stack |
| `compose.dev.yaml` | Contributors | Bind-mounted source, Xdebug, a `workspace` sidecar for Composer/Node/Artisan, the Vite dev server, Playwright |
| `scripts/smoke-test.sh` | Contributors / CI | Builds both images, installs into a clean directory with `install.sh`, starts the stack and checks it end to end |
| `scripts/prod-e2e.sh` | Contributors / CI | Runs the whole Playwright suite against the production images (P-7) |
| `scripts/upgrade-test.sh`, `restore-test.sh`, `search-recovery-test.sh`, `queue-scheduler-test.sh` | Contributors / release | Drills of the procedures in `operations.md`, run on populated data in separate Docker daemons: the upgrade (P-9), backup, restore and rollback (P-10), search index recovery (P-11), the queue, Redis and scheduler under failure (P-12). Release steps, not CI (`docs/testing/README.md`) |
| `scripts/mail-delivery-test.sh` | Contributors / release | Sends one mail of every type through the production stack to a mailbox you name (P-13). Sends real mail |

Following Waffle Dashboard, the operator file is a separate, curl-able, image-only artifact; unlike Waffle, a single `.env`
value pins the release. An upgrade still replaces `docker-compose.yaml` with the new release's, because a release can change
it; `install.sh --upgrade` does both (see `docs/deployment/operations.md`, "Upgrades").

## Services

| Service | Image | Role |
|---|---|---|
| `web` | `ghcr.io/lchristmann/nusszopf-web` — nginx built `FROM` the application image's own assets | HTTP; serves static assets, passes PHP to `php-fpm`. The only published port (`APP_BIND`:`APP_PORT`) |
| `php-fpm` | `ghcr.io/lchristmann/nusszopf-php-fpm` (PHP 8.5-FPM, Laravel 13, Livewire 4, non-root) | Request handling. Its entrypoint refuses to start without `APP_KEY`, runs `migrate --force --isolated`, warms the config, route, view and event caches, and applies the search index settings (`scout:sync-index-settings`; only a warning if Meilisearch is unreachable) |
| `queue-worker` | same application image | `queue:work --tries=5 --backoff=10,30,60,120 --max-time=3600`: background jobs (search indexing, every mail). Healthy while it processes the scheduler's heartbeat job |
| `scheduler` | same application image | `schedule:work`. Its tasks (`routes/console.php`): the two heartbeats, and `newsletter:purge-unconfirmed` daily at 03:30 UTC (decision A-1) — the historical product had no periodic work, so nothing else runs. Healthy while its heartbeat is fresh |
| `postgres` | `postgres:16-alpine` | Primary datastore |
| `redis` | `redis:8-alpine`, started with `--appendonly yes` | Sessions, cache, queue. The append-only file keeps waiting mails and index updates through a crash (P-12) |
| `meilisearch` | `getmeili/meilisearch:v1.11`, `MEILI_ENV=production`, master key from `MEILISEARCH_KEY` | Search index — derived data, rebuilt with `search:reindex` (`docs/deployment/operations.md`, "Search index recovery", also for a Meilisearch whose data no longer opens) |
| `workspace` (dev only) | Node + Composer + CLI tools | Contributor shell |

`queue-worker` and `scheduler` wait for `php-fpm` to be healthy, i.e. for the migrations to have finished; nothing else migrates.
There is deliberately no bundled reverse proxy or mail server — see [Reverse proxy and TLS](#reverse-proxy-and-tls) and [Sending mail](#sending-mail-mail_-resend_api_key).

## Why a dedicated nginx image, not a shared assets volume

`laravel-docker-examples`' production Compose file shares a named volume between `web` and `php-fpm` to keep Vite's `manifest.json` and hashed filenames consistent between the two containers. This has a real, demonstrated flaw: Docker only seeds a new named volume from an image the first time it's created, so every redeploy after the first leaves stale assets in place unless something actively re-syncs the volume on every boot (full evidence in `docs/references/laravel-docker-examples.md` §4).

LCxHolz papers over this with a custom nginx entrypoint that deletes and re-copies assets into the volume on every boot. Nusszopf instead follows **Waffle Dashboard's** structurally simpler approach: build Vite assets once, inside the application image's own multi-stage `Dockerfile` (no separate Node install in the nginx build), then build the nginx image `FROM` the application stage and `COPY --from=php-fpm /var/www/public /var/www/public`. Because both images are produced from the same source tree at the same version, the manifest can never drift, and no shared volume or runtime re-sync step is needed at all. The smoke test checks that a built stylesheet is served.

This reasoning is specific to *build* assets, which are always fully reproducible from the image itself. It does not apply to `laravel-storage` (uploaded avatars, slice 8): that is genuine runtime data with no image to reseed it from, so it is a real named volume shared read-write with `php-fpm` and read-only with `web` — see [Persistent storage](#persistent-storage) and "Avatar storage and serving" below.

### Avatar storage and serving

Avatars (`docs/design/screen-specs.md`, "Profile / account settings") live on the local disk (register B4/B8 — no object storage in v1), under `storage/app/public/avatars`, one versioned file per upload. `docker/php/Dockerfile` bakes a `public/storage → ../storage/app/public` symlink into both images at build time; because it is a *relative* symlink, it resolves correctly in any container that mounts `laravel-storage` at `storage/app`, which is why `web` — unlike the Vite-assets case above — does mount that volume (read-only: `php-fpm` is the only writer). `docker/nginx/default.conf` serves `/storage/...` directly as a static file with a long, immutable cache lifetime (the filename is versioned, so a replaced avatar always gets a new URL) — no PHP round trip for the common case of loading someone's avatar. Deleting the volume deletes every avatar; back it up with the rest of `laravel-storage` (see [Backups, upgrades, recovery](#backups-upgrades-recovery)).

## Required configuration

`.env.production.example` is the source of truth — every variable with its default or a `REQUIRED` marker (release, `APP_KEY`, `APP_URL`, `DB_PASSWORD`, `MEILISEARCH_KEY`, `MAIL_FROM_ADDRESS`).
`install.sh` generates the secrets and writes the release and `APP_URL`; `MAIL_FROM_ADDRESS`, your own sender address, is the one required value only you can fill in (there is deliberately no default: it used to be the historical project's mailbox, P-8 finding P8-03). Notable choices: `APP_ENV=production`, `APP_DEBUG=false`; sessions, cache and queue on Redis; `SCOUT_QUEUE=true` so search sync is a retried
job, never fire-and-forget (BUG-009); `LOG_CHANNEL=stderr` so `docker compose logs` shows the application's log; `SESSION_LIFETIME=480`, the historical 8-hour rolling session;
`TRUSTED_PROXIES`, `APP_BIND`, `APP_PORT` for the proxy setup below; `HEALTH_TOKEN` for `/health` details; the mail settings, an operator-supplied provider for every mail — contact form, account mails, newsletter confirmations ([Sending mail](#sending-mail-mail_-resend_api_key); the dev/CI stack uses a bundled Mailpit catcher instead, never production).

### Configuration reference

Every setting in `.env.production.example`, in the order of the file. **Bold** ones you must set; `install.sh` fills in the ones marked
"generated". A change takes effect with `docker compose up -d` (not `restart`).

| Setting | Default | What it does |
|---|---|---|
| **`NUSSZOPF_VERSION`** | written by `install.sh` | The release that runs; `install.sh --upgrade` changes it |
| `APP_NAME` | `Nusszopf` | The application name; `MAIL_FROM_NAME`, the sender name of every mail, defaults to it |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` | Leave as they are; `APP_DEBUG=true` would show internals in error pages |
| **`APP_KEY`** | generated | Signs sessions, cookies and every mailed link. Keep it with your backups (it is in `installation.tar.gz`); a new one invalidates links already sent |
| **`APP_URL`** | `https://nusszopf.example.org` (`install.sh` writes yours) | The exact public address: every link in pages and mails starts with it |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `BCRYPT_ROUNDS` | `de`, `en`, `12` | Language and password hashing cost; the product is German |
| `APP_BIND`, `APP_PORT` | `0.0.0.0`, `8080` | Where `web` listens on the host; `127.0.0.1` with a proxy on the same machine |
| `TRUSTED_PROXIES` | loopback and private networks | Which proxies are believed about the client address and `https`; never `*` on a reachable port |
| `SESSION_SECURE_COOKIE` | `true` | Cookies only over https; keep `true` behind TLS |
| `LOG_CHANNEL`, `LOG_LEVEL` | `stderr`, `warning` | `docker compose logs` shows the application's log |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` | `pgsql`, `postgres`, `5432`, `nusszopf`, `nusszopf` | The bundled PostgreSQL; leave as they are |
| **`DB_PASSWORD`** | generated | The database password; the database is created with it, so changing it later needs a change in PostgreSQL too |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_HOST` | `redis`, `480`, `redis`, `redis`, `redis` | Sessions (8 hours, rolling, as historically), cache and queue on the bundled Redis |
| `SCOUT_DRIVER`, `SCOUT_QUEUE`, `MEILISEARCH_HOST` | `meilisearch`, `true`, `http://meilisearch:7700` | Search on the bundled Meilisearch; index updates are queued and retried (BUG-009) |
| **`MEILISEARCH_KEY`** | generated | Master key of the bundled Meilisearch, at least 16 characters |
| `LOCATIONIQ_KEY` | empty | Place search for projects with a fixed location ([below](#location-search-locationiq_key)) |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | empty | Google login, hidden without them ([below](#google-login-google_client_idgoogle_client_secret)) |
| `MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_NAME` | `smtp`, empty, empty, `587`, empty, empty, `${APP_NAME}` | How mail is sent ([below](#sending-mail-mail_-resend_api_key)); `MAIL_SCHEME` (`smtps` for port 465) is also read but not in the template |
| **`MAIL_FROM_ADDRESS`** | empty, you set it | The sender of every mail, on a domain your provider verified |
| `NUSSZOPF_CONTACT_EMAIL` | empty (uses `MAIL_FROM_ADDRESS`) | The address shown wherever the app says "write to us" |
| `NUSSZOPF_LEGAL_PATH` | empty (uses `./legal`) | The folder of the three legal texts |
| `NUSSZOPF_REGISTER_LIMIT` | empty (10) | New accounts one IP address may create per 15 minutes |
| `NEWSLETTER_CONSENT_VERSION` | `1` | Version of your Datenschutz text, stored with each consent |
| `HEALTH_TOKEN` | generated | Bearer token that makes `/health` show version and check details |

`LOCATIONIQ_URL` (default the LocationIQ endpoint) is read too and is not in the template; it is only for a compatible service.

### Location search (`LOCATIONIQ_KEY`)

Choosing a fixed location for a project (the wizard's and edit screen's "Ortsgebunden") uses the
[LocationIQ](https://locationiq.com) autocomplete API, as the historical product did (German
cities, towns and villages only). LocationIQ has a free tier; create a key and set
`LOCATIONIQ_KEY`. The request is made by the server, so the key is never sent to visitors' browsers.
Without a key the place search shows no suggestions and a project cannot be given a fixed
location — "Ortsunabhängig" projects are unaffected. `LOCATIONIQ_URL` (default the LocationIQ
endpoint) exists only to point at a compatible service; the development/CI Compose stack points it at
its bundled `locationiq-stub`.

### Google login (`GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`)

Google login (docs/authentication/README.md §3) needs an OAuth 2.0 client from the
[Google Cloud Console](https://console.cloud.google.com/apis/credentials) with an authorized redirect
URI of `<APP_URL>/auth/google/callback`. Without both variables set, the "Google" button on the login
screen is hidden and its routes 404 — password/username login is completely unaffected.
`GOOGLE_REDIRECT_URI` only needs setting if the app is reachable at a different URL than `APP_URL`
(e.g. behind a path-rewriting proxy).

### Newsletter (`NEWSLETTER_CONSENT_VERSION`)

Stored with every newsletter subscription's consent record (decision A-1): name the version of your
Datenschutz text and change it whenever that text changes. Default `1`. Exporting subscribers for an
external sender, retention and the unsubscribe link every issue must carry: `docs/deployment/operations.md`,
"Newsletter subscribers". Newsletter mails go out through the same mail provider as every other mail.

### Sending mail (`MAIL_*`, `RESEND_API_KEY`)

Every mail (account, contact, newsletter) is queued and sent by the `queue-worker`. Nusszopf uses Laravel's own mail
abstraction and adds none of its own: you choose a mailer with `MAIL_MAILER` and give it that mailer's settings.
**Resend is the recommended provider.** It is the one that was tested with real delivery, on the production stack
(P-13), and it needs one setting. A plain SMTP relay is supported through the same abstraction. Set one of the two in
`.env` and run `docker compose up -d` (the containers read `.env` when they are created, so `docker compose restart` is
not enough).

| | Resend API (recommended) | SMTP relay |
|---|---|---|
| `MAIL_MAILER` | `resend` | `smtp` (the default) |
| Set | `RESEND_API_KEY` (a key that may only send is enough); leave `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` empty | `MAIL_HOST`, `MAIL_PORT` (587 with STARTTLS, 465 with implicit TLS), `MAIL_USERNAME`, `MAIL_PASSWORD`, optionally `MAIL_SCHEME` (`smtps` for 465) |
| Verified | Delivered for real to a mailbox at a third party, on the production images (P-13) | Only against a Mailpit that does not check certificates (P-7, P-12). **Not yet** against a real relay with TLS |

Both need `MAIL_FROM_ADDRESS`, an address on a domain **your provider has verified you may send for**. With Resend,
sending from any other domain is refused (`The <domain> domain is not verified`), and the mail ends in `failed_jobs`
after five tries. TLS certificates are always verified; there is no setting to switch that off.

Every mail Nusszopf sends is HTML only, with no plain-text part: that is the intended format, not a misconfiguration
(decision P13-03, `docs/rewrite/decisions-register.md`). How each client displays them is verified for Proton Mail
only; Gmail, Outlook and Apple Mail are still to be checked before the first release (`docs/release/parity/P-13-email-delivery.md`, section 9).

The DNS records found (with `dig`) on the domain P-13 sent from through Resend. They are what that one setup had, not
a checklist derived from the provider's documentation, and another provider needs its own records
(`docs/release/parity/P-13-email-delivery.md`, section 6):

- a **DKIM** `TXT` record at `resend._domainkey.<your domain>`;
- a **return-path** subdomain `send.<your domain>`: an `MX` record (priority 10) and an SPF `TXT` record
  (`v=spf1 include:amazonses.com ~all`). The SPF of the bare domain is untouched by it (there it named the mailbox
  provider), because SPF is checked against the return-path domain;
- a **DMARC** `TXT` record at `_dmarc.<your domain>`. Nusszopf does not require one; the domain observed had
  `v=DMARC1; p=quarantine`.

Two addresses are worth a thought. `MAIL_FROM_ADDRESS` is the sender of every mail. When it is a `no-reply@` address,
set `NUSSZOPF_CONTACT_EMAIL` to a mailbox you read: without it, every mail footer, the error page and the contact card
tell people to write to the no-reply address. The contact form's mails carry the visitor's address as `Reply-To`, so
"Reply" reaches the visitor whatever the sender is.

### Your identity (`NUSSZOPF_CONTACT_EMAIL`)

The address shown wherever Nusszopf says "write to us": the error page, Home's "Partner:in werden"/"Feedback
senden" buttons, the newsletter and Profile pages, every e-mail's footer, the "Projekt melden" link and the
downloadable contact card (`/contact/nusszopf-vcard.vcf`, built from this address, `MAIL_FROM_ADDRESS` and
`APP_URL`). Empty means `MAIL_FROM_ADDRESS`. Historically this was `mail@nusszopf.org`; it is your mailbox now
(decision A-5). Home's copy, logos and the Instagram/Steady links are the historical Nusszopf's, reproduced
verbatim (decision A-5).

### Legal pages (`./legal`, `NUSSZOPF_LEGAL_PATH`)

Impressum (`/legalNotice`), Rechtliches (`/legalPolicy`) and Datenschutz (`/privacy`) show **your** text
(decision A-4 — Nusszopf ships none). Put three Markdown files into the `legal/` folder next to
`docker-compose.yaml` (`install.sh` creates it; the stack mounts it read-only):

| File | Page |
|---|---|
| `legal/legal-notice.md` | Impressum |
| `legal/legal-policy.md` | Rechtliches (terms of use) |
| `legal/privacy.md` | Datenschutz |

The page heading is fixed; the file is the body — `##` headings, paragraphs, lists, links, a line ending in
two spaces for a line break. Raw HTML is shown as text. Changes appear on the next page load, no restart.
A missing or empty file makes its page say "Dieser Text wurde von den Betreiber:innen dieser
Nusszopf-Instanz noch nicht hinterlegt." `docs/deployment/legal-examples/` holds the original operators'
2021 texts, labelled as examples — do not publish them as they are. Your Datenschutz text should describe
what your instance actually does: the services you configure (your mail provider, LocationIQ, Google login) and
the newsletter's double opt-in; it must not name Auth0, SendGrid or Visitor Analytics, which Nusszopf 2 does
not use. When it changes, raise `NEWSLETTER_CONSENT_VERSION`. `NUSSZOPF_LEGAL_PATH` points elsewhere only
if you mount the files at another path. Back the folder up with `.env`.

Every variable keeps a default or an explicit `REQUIRED` note. Object storage (S3) is not part of v1: avatars live on the `laravel-storage` volume.

## Installation (operator path)

What you need: a Linux host with Docker Engine and its Compose plugin (`docker compose`, not the old `docker-compose`; install both from [Docker's instructions for your distribution](https://docs.docker.com/engine/install/)), `curl` and `openssl`; a domain name pointing at it if the site is public; a mail provider that may send for your sender address (Resend recommended, or an SMTP relay). Nothing else is installed on the host. The commands below are run as root (or with `sudo`, or as a user in the `docker` group, in a directory that user owns).

Verified on a freshly installed Ubuntu 24.04 host with Docker Engine 29.8 and Compose 5.5, following only this section (`docs/release/parity/P-08-fresh-install.md`).

```sh
mkdir /opt/nusszopf && cd /opt/nusszopf
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh
sh install.sh https://nusszopf.example.org          # or: sh install.sh https://nusszopf.example.org 0.1.0
```

`install.sh` downloads the release's `docker-compose.yaml` and `.env.production.example`, writes `.env` with freshly generated secrets
(`APP_KEY`, `DB_PASSWORD`, `MEILISEARCH_KEY`, `HEALTH_TOKEN`; mode 600), and refuses to overwrite an existing `.env`. Then:

1. Edit `.env`: set `MAIL_FROM_ADDRESS` (**required**: your sender address, also shown as the contact address unless `NUSSZOPF_CONTACT_EMAIL` is set) and how mail is sent: `MAIL_MAILER=resend` with `RESEND_API_KEY` (recommended), or your relay in `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` ([Sending mail](#sending-mail-mail_-resend_api_key)); `APP_BIND=127.0.0.1` when a reverse proxy runs on this host. Optional: `NUSSZOPF_CONTACT_EMAIL`, `LOCATIONIQ_KEY`, `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`. Everything a first-time operator *must* set is marked `REQUIRED` in the file, and Compose refuses to start with a clear message if one is missing.
2. `docker compose up -d` — the first start pulls the images, waits for PostgreSQL, Redis and Meilisearch, migrates the database, applies the search index settings and starts everything.
3. `docker compose ps` — every service `healthy` (the queue worker and scheduler need up to a few minutes, they prove themselves with a heartbeat per minute; about 40 seconds after `php-fpm` in P-8). Until then step 4 reports them `FAILED` with "no heartbeat yet" — wait, it is not a fault.
4. `docker compose exec php-fpm php artisan nusszopf:health` — the version and every dependency `ok`.
5. Put your legal texts into `legal/` ([Legal pages](#legal-pages-legal-nusszopf_legal_path)); until then those three pages say they are not configured.
6. Open the app and register a normal account through the ordinary registration screen — **there is
   no separate "first administrator" bootstrap step and no Artisan command for this.** Nusszopf's domain
   archaeology **confirms no admin/staff role exists anywhere in the historical product**
   (`docs/domain/entities.md`, "Entities confirmed absent"; `docs/rewrite/decisions-register.md`,
   "Explicitly not open") — every account is an ordinary equal-privilege user.

Doing it by hand instead of `install.sh`: download `docker-compose.yaml` and `.env.production.example` (renamed `.env`) from the
release, set the `REQUIRED` lines, and generate the key with
`docker compose run --rm --no-deps --entrypoint php php-fpm artisan key:generate --show` (`.env` must contain `NUSSZOPF_VERSION`
and the database/search passwords first, Compose reads them).

## Reverse proxy and TLS

**Decided** (`docs/rewrite/architecture-decisions.md`): no reverse proxy is bundled. The stack publishes one port (`web`, default `8080`);
put whatever terminates TLS for your other services in front of it — Nginx Proxy Manager, Caddy or Traefik — and proxy your domain to
`http://<host>:8080`. For a proxy on the same host set `APP_BIND=127.0.0.1` so only it can reach the port.

The application must be told to believe the proxy about the original scheme and address: `TRUSTED_PROXIES` in `.env`, `APP_URL` with
`https://`, and `SESSION_SECURE_COOKIE=true` — all preset in `.env.production.example`. The preset `TRUSTED_PROXIES` lists loopback and the
private networks (`127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,fc00::/7`), which covers a proxy on the same host (it reaches the
container through Docker's private bridge) or on the local network. A proxy with a public address elsewhere must be added to the list. Do not
set `*` while the port is reachable from the internet: every client could then name its own address in `X-Forwarded-For` and slip past the
per-address limits on login, registration and the public forms (`docs/release/parity/P-04-security.md`, SEC-02). An untrusted proxy also makes
the signed links in e-mails (verification, unblock) fail, because the application then sees `http://` where the link says `https://`.
Caddy needs only `nusszopf.example.org { reverse_proxy 127.0.0.1:8080 }`.

Every link the application writes — in pages and above all in e-mails — starts with `APP_URL`, whatever address the visitor used, so
`APP_URL` must be the exact public address.

The application sends its own security headers, including a Content-Security-Policy (`docs/security/README.md`). HSTS
(`Strict-Transport-Security`) is left to your TLS proxy, which knows whether the site is HTTPS-only for good. Once it is,
enable HSTS there; Caddy, Traefik and Nginx Proxy Manager each have a setting for it.

## Persistent storage

Named volumes, one per stateful concern (never one shared "data" volume, so that restoring or wiping one never risks another — this separation is explicit in LCxHolz's backup documentation):

- `postgres-data` — database
- `laravel-storage` — uploaded files: the avatars (slice 8) and Livewire's temporary uploads. Backed up by the backup script; a build never puts files into it (`.dockerignore`, P10-04), because Docker fills a new volume from the image
- `./legal` (a bind mount, not a volume) — your legal texts; back it up with `.env`
- `meilisearch-data` — search index. Derived from PostgreSQL: never backed up; a restore rebuilds it with `search:reindex` (`docs/deployment/operations.md`)
- `redis-data` — sessions, queue, cache, with its append-only file. Not backed up: after a restore everyone signs in again (`docs/deployment/operations.md`, "Backups")

No shared assets volume — see [above](#why-a-dedicated-nginx-image-not-a-shared-assets-volume).

## Health checks

Three layers, all verified by the smoke test:

- **Container health** (`docker compose ps`): `php-fpm` answers PHP-FPM's ping; `web` fetches `/up`; `postgres` `pg_isready`; `redis` `redis-cli ping`; `meilisearch` `/health`;
  `queue-worker` and `scheduler` run `php artisan nusszopf:health --only=queue|scheduler`, which passes while the heartbeat (written by a scheduled task every minute; for the queue by a job
  the worker must process) is at most three minutes old. `depends_on: condition: service_healthy` orders the start (databases → `php-fpm` → `web`, `queue-worker`, `scheduler`).
- **`/up`**: Laravel's liveness probe — 200 while the application boots. Touches no dependency.
- **`/health`**: 200 `{"status":"ok"}` or 503 `{"status":"degraded"}`; it starts no session, so it still answers while Redis is down. With `HEALTH_TOKEN` set, a request with
  `Authorization: Bearer <token>` also gets the version and each check (`database`, `redis`, `search`, `scheduler`, `queue`, `failed_jobs`) with its reason. `search` passes only while Meilisearch answers *and* the `items` index exists with the settings of `config/scout.php`; a lost index, or one recreated without its settings, fails it with "run php artisan search:reindex" (P-11). `failed_jobs` fails while a job that ran out of its tries waits in the `failed_jobs` table, and names the commands that clear it (P-12); the container health of `queue-worker` ignores it, since a worker that fails a job is not a dead worker. Point an uptime monitor at it.
- **`php artisan nusszopf:health`** — the same from the shell, with the running version; exit code 1 when a check fails. `php artisan about` shows the version too, and `docker image inspect` the `org.opencontainers.image.version` label.

The version is the Git tag: the release workflow passes it as the `NUSSZOPF_VERSION` build argument (there is no hand-maintained version file); an image built from a working copy reports `dev`.

## Backups, upgrades, recovery

See `docs/deployment/operations.md` for the full procedures. Summary of what each reference contributes:

- **Waffle Dashboard**: the only reference actually written as an operator-facing guide — `pg_dump`/`tar` via a cron-scheduled shell script, with a tested restore procedure. This is the baseline UX bar for Nusszopf.
- **LCxHolz**: a materially more mature mechanism (`spatie/laravel-backup`: encrypted archives, tiered retention, health-check monitoring, a restore procedure actually run end-to-end while implementing it) — the engineering-quality bar per `.claude/rules/05-engineering-quality.md`, but tied to MySQL/spatie's dumper in that project; Nusszopf would need the PostgreSQL equivalent.
- **Neither reference covers Meilisearch backup.** Nusszopf does not back up the index; the restore rebuilds it from PostgreSQL with `search:reindex`. P-10 verified that the rebuilt index equals the original document for document.

Nusszopf v1 uses tier 1 (decision B2): the script in `operations.md`, "Backups", run by cron. It backs up the database, the storage volume and the installation directory (`.env`, `docker-compose.yaml`, `legal/`, overrides). Restoring it onto an empty host, and rolling an upgrade back with it, were drilled in P-10 (`scripts/restore-test.sh`).

## Decisions and what is still open

Decided (`docs/rewrite/architecture-decisions.md`, `docs/rewrite/decisions-register.md`): container registry (GHCR, `ghcr.io/lchristmann/nusszopf-*`), reverse proxy (operator-owned), health depth
(own checks, no extra dependency), environment variables (`.env.production.example`), first administrator (none exists), mail (Laravel's abstraction; Resend recommended, SMTP supported), the search index is rebuilt and never backed up (`search:reindex`), and the backup script is the tier-1 script in `operations.md`, "Backups".

Still open, and owned by later phases: everything that needs a published release (the GitHub download, the GHCR pull, arm64, an install by a second person on a real host with an ACME certificate — P-16), and the rendering of the mails in Gmail, Outlook and Apple Mail (P-16). The parity report tracks them (`docs/release/parity/README.md`, "Carried forward to later phases").
