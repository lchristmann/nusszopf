# P-8 Fresh install (2026-09-25)

Exit evidence (`master-roadmap.md` §4): a fresh install by someone who has not seen the code, using only the
documentation. The run is timed, and every stumble becomes a documentation fix.

The maintainer set these conditions:
- a genuinely clean environment;
- only the published installation instructions and the release files;
- no knowledge from development and no undocumented shortcuts.

## Who ran it, and what they were allowed to read

Claude ran the install. Claude has seen the source, so this is not the "second person" install. That install stays an
exit criterion of P-16 ("an RC installed by a second person").

To keep to the operator's view, the run used only:
- the root `README.md`;
- `docs/deployment/README.md` and `docs/deployment/operations.md`;
- the three files a release attaches: `install.sh`, `docker-compose.yaml` and `.env.production.example`;
- the release's two images.

The clean host never had a copy of the repository: `/opt/nusszopf` held only those files, `.env` and `legal/`, and
the host had no PHP, Composer or Node. `git` was present only because Docker's installer pulls it in; nothing was
cloned.

## Environment

| | |
|---|---|
| Host | A fresh `ubuntu:24.04` system (24.04.5 LTS) with its own, empty Docker daemon: no images, volumes or source code. It ran as a privileged container on the maintainer's workstation (Ubuntu 24.04, 4 cores, 31 GB), because no VM tool is installed there |
| Docker | Docker Engine **29.8.1** and Compose **v5.5.1**, installed with Docker's own installer (`get.docker.com`, from [docs.docker.com/engine/install](https://docs.docker.com/engine/install/)). CI uses older versions; nothing needed a change |
| Release | `0.1.0-p8`, built from `4de0194` the way `release.yml` builds a release: both images built with `NUSSZOPF_VERSION=0.1.0-p8`, and the three operator files prepared exactly as the workflow's "Operator files" step does. amd64 only |
| TLS proxy | Caddy 2.6.2 from Ubuntu's archive, on the same host, with the documentation's one-line site block plus `tls internal` |
| SMTP relay | Mailpit at `smtp.test:1025` on the same network, standing in for the operator's relay |
| Browser | Chromium (Playwright `v1.63.0-noble`) on the same network, opening `https://nusszopf.test`, with the internal certificate accepted |

### What stood in for missing pieces

No release has been published yet, so four pieces were simulated. P-16 owns the real ones.

1. **The release download.** The documented URL
   `https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh` answers **404**: the repository is
   not public and no tag exists (P8-01). The three files were served from a local mirror with the same path layout
   (`http://releases.test/0.1.0-p8/`). `install.sh` was pointed there with `NUSSZOPF_BASE_URL`, an override for
   testing that the operator documentation does not mention.
2. **The image pull.** `ghcr.io/lchristmann/nusszopf-*` does not exist yet. The two images, under their GHCR names,
   were copied into the host with `docker load` before `docker compose up -d`. The third-party images (`postgres`,
   `redis`, `meilisearch`) were pulled from Docker Hub as usual during `up -d`.
3. **Host services.** The host has no systemd, so `dockerd` and `caddy` were started by hand. On a VM, Docker's and
   Caddy's packages start them as services. The minimal container image also lacks `curl` and `openssl`, which a
   cloud or server image ships with. The documentation lists all three prerequisites, so this was not a stumble.
4. **Certificates.** There is no public DNS name, so Caddy issued an internal certificate instead of an ACME one.

## Procedure followed

Run 1 used the documentation as it stood at `4de0194`. Run 2 was a second fresh host after the fixes below, and it
followed the corrected documentation. The steps (run 2 wording):

```sh
# prerequisites (Ubuntu): curl, openssl, then Docker Engine + Compose plugin from Docker's instructions
apt-get install -y curl openssl ca-certificates
curl -fsSL https://get.docker.com | sh

# docs/deployment/README.md, "Installation (operator path)"
mkdir /opt/nusszopf && cd /opt/nusszopf
curl -fsSLO https://github.com/lchristmann/nusszopf/releases/latest/download/install.sh   # 404 → mirror, see above
sh install.sh https://nusszopf.test 0.1.0-p8
# step 1: edit .env
#   MAIL_FROM_ADDRESS=nusszopf@nusszopf.test  MAIL_HOST=smtp.test  MAIL_PORT=1025  APP_BIND=127.0.0.1
#   (run 1 also set NUSSZOPF_CONTACT_EMAIL)
docker compose up -d                                   # step 2
docker compose ps                                      # step 3: wait for 7 × healthy
docker compose exec php-fpm php artisan nusszopf:health  # step 4
# "Reverse proxy and TLS": Caddy  nusszopf.test { reverse_proxy 127.0.0.1:8080 }  (+ tls internal)
# step 6: open the site and register
```

A third, minimal run on host 1 ran only `install.sh` and `docker compose up -d`, with none of the optional edits. It
used the documentation as it stood at `4de0194` and showed P8-03.

## Elapsed time

| Run | From a bare host to… | Time |
|---|---|---|
| 1 (docs at `4de0194`) | Docker installed, `install.sh` done | about 1 min 10 s |
| 1 | `docker compose up -d` returned (it pulls the three Docker Hub images) | 51.7 s after it started |
| 1 | all 7 services `healthy` | **at most 3 min 32 s** from the bare host. This is an upper bound: the first poll came late |
| 2 (corrected docs) | Docker ready | 63 s |
| 2 | images loaded, `install.sh` done, `.env` edited, `up -d` started | 1 min 37 s |
| 2 | `up -d` returned; all 7 `healthy` | 51 s; **62 s** after `up -d` (**2 min 39 s** from the bare host) |
| 2 | HTTPS through Caddy answers `/health` 200 | 2 min 45 s from the bare host |
| 3 (minimal, images cached) | `up -d` to all 7 `healthy` | 66 s |

These are machine times. They do not include a person's time to read the documentation or to choose a sender address
and relay, which will take longer than the install itself. They also do not include pulling the two Nusszopf images
from GHCR (about 700 MB as a `docker save` archive), because the images were loaded locally (P-16).

Inside `up -d`, the start order worked as documented:
1. `postgres`, `redis` and `meilisearch` became healthy;
2. `php-fpm` migrated, warmed the caches and applied the search index settings, then became healthy;
3. `web`, `queue-worker` and `scheduler` started;
4. the queue worker and scheduler became healthy with their first heartbeat, about 40 s after `php-fpm`.

## Verification on the fresh host

| Check | How | Result |
|---|---|---|
| Health | `docker compose ps`; `nusszopf:health` | 7 of 7 `healthy`. `database`, `redis`, `search`, `scheduler` and `queue` all `ok`, exit 0 |
| `/health` through TLS | `curl https://nusszopf.test/health`, without and with `HEALTH_TOKEN` | `{"status":"ok"}` 200. With the token: `"version":"0.1.0-p8"` and all five checks |
| Version | `nusszopf:health`, `/health` with the token, the image label, `artisan about` | `0.1.0-p8` everywhere; `production`, debug `OFF`; Laravel 13.32.0, PHP 8.5.10 |
| Port binding | `ss -ltn`; a request to the host's address on port 8080 | With `APP_BIND=127.0.0.1`, 8080 answers only on loopback (connection refused from outside). With the default (run 3) it listens on `0.0.0.0`, as documented |
| First login (browser, through Caddy) | Home → register → My Projects | Lands on `https://nusszopf.test/user/projects`. The session cookie is `Secure`. There is no administrator step, as documented |
| Mail wiring | Mailpit | "Willkommen beim Nusszopf!" and "Bestätige deine E-Mail-Adresse" arrived from `MAIL_FROM_ADDRESS` |
| Signed link behind the proxy | open the verification link from the e-mail | The link starts with `APP_URL`; opening it returns 200 and redirects to My Projects. So the default `TRUSTED_PROXIES` covers a proxy on the same host |
| Log out, log in | nav menu; login form | back on My Projects |
| Basic use | create a public project with one request (the wizard); search for it | The project is found through `/search`. The queue worker ran the `MakeSearchable` jobs |
| Search readiness | Meilisearch `items` settings and stats | Present from the first start, before any `search:reindex`: `req_type` filterable, the `updated_at:desc` rule (the P7-01 fix holds on a fresh install). After the project was created, 1 document |
| Queue readiness | `queue:failed`; the logs | No failed jobs, no `ERROR`/`CRITICAL` log line |
| Scheduler readiness | `schedule:list` | Both heartbeats every minute, and `newsletter:purge-unconfirmed` daily at 03:30 **UTC** (P8-05) |
| Legal pages | `/legalNotice`, `/legalPolicy`, `/privacy` | Each shows its "not configured" notice, as `install.sh` says |
| Contact identity | Home | Run 1: `NUSSZOPF_CONTACT_EMAIL`. Run 2: `MAIL_FROM_ADDRESS`. Run 3, with the old defaults: `mail@nusszopf.org` (P8-03) |
| Changing `.env` | change `NUSSZOPF_CONTACT_EMAIL`, then `restart` or `up -d` | `restart`: old value still shown. `up -d`: new value (P8-06) |

## Stumbles and findings

| # | What happened | Kind | Resolution |
|---|---|---|---|
| P8-01 | The documented download (`…/releases/latest/download/install.sh`) answers 404. The repository is not public, no tag exists, and there are no GHCR images, so a real operator cannot start at all today | Missing release, expected before P-16 | **Deferred to P-16** (first tag, GitHub Release, GHCR pull) and P-15 (repository published). The procedure itself was run against release-equivalent files (see "What stood in for missing pieces") |
| P8-02 | The operator files point to `docs/deployment/README.md` and similar paths. An operator who has only the release files cannot open them: `install.sh`'s last lines, the comments in `.env.production.example` and in `docker-compose.yaml` | Documentation | **Fixed.** `install.sh` ends with absolute links to the docs of the installed release (`…/blob/<version>/docs/deployment/…`, or `main` for "latest"). Both other files name the repository URL in their header |
| P8-03 | **Installation defect.** With the shipped defaults, a fresh instance sends every e-mail from `mail@nusszopf.org`, the historical project's mailbox, and shows that address as its own contact: Home, error page, e-mail footers, report link and vCard. Only the optional `.env` edits avoided it, and neither the docs nor `install.sh` said to make them. Decision A-5 made that mailbox the operator's configuration, and a sender on a domain the relay does not serve also fails SPF | Defect in the operator template (not a historical-behaviour change; nothing in `bugs.md` applies) | **Fixed.** `MAIL_FROM_ADDRESS` has no default in `.env.production.example` and is marked `REQUIRED`. `docker-compose.yaml` refuses to start without it and says `Set MAIL_FROM_ADDRESS in .env to your own sender address` (run 2 shows this). `install.sh`, the README quick start and the install steps now say to set it. **Regression:** `scripts/smoke-test.sh` checks that a fresh `.env` names no `@nusszopf.org` address and that Compose refuses to start without the value; it passed. `smoke-test.sh` and `prod-e2e.sh` set a test sender. The dev `.env.example` keeps its historical default |
| P8-04 | `install.sh`'s last lines said `docker compose up -d`, then `nusszopf:health`. Run straight away, the health command reported `scheduler`/`queue` `FAILED — no heartbeat yet` and exited 1 | Documentation | **Fixed.** `install.sh` lists `docker compose ps` ("wait until every service is healthy") between the two. Install step 3 says that `FAILED — no heartbeat yet` before then is expected |
| P8-05 | `.env.production.example` offered `APP_TIMEZONE=Europe/Berlin`, but `config/app.php` hardcodes `UTC`, so the setting did nothing. The newsletter purge runs at 03:30 UTC, not Berlin time | Documentation (dead setting) | **Fixed.** The line is gone from both env templates. `operations.md` says "03:30 UTC (the application runs in UTC; there is no timezone setting)". Nothing about the application's timezone changed |
| P8-06 | The troubleshooting entry "A changed `.env` has no effect" said `docker compose restart` applies the change. It does not: `restart` keeps the environment the container was created with (tested above) | Documentation (wrong claim) | **Fixed.** It now says to use `docker compose up -d`, and that `restart` is not enough |
| P8-07 | The prerequisites said only "Docker (with the Compose plugin)". They did not say where to get it, that Compose v2 (`docker compose`) is needed rather than the old `docker-compose`, that the commands need root, `sudo` or the `docker` group, or that an SMTP relay is needed | Documentation | **Fixed** in "Installation (operator path)": a link to Docker's install instructions, Compose v2, root/`sudo`/`docker` group, the SMTP relay, and the tested Ubuntu, Engine and Compose versions |
| P8-08 | The README quick start chained `sh install.sh … && docker compose up -d`, which skipped configuring the instance altogether | Documentation | **Fixed.** Three lines, with the `.env` edit between them |

These worked as documented and needed no change:
- `install.sh`'s refusal messages;
- the generated secrets (`.env` mode 600);
- `SESSION_SECURE_COOKIE` for an `https://` address;
- the one-line Caddy configuration with the default `TRUSTED_PROXIES`;
- the start order and every healthcheck;
- the entrypoint's migrations and search settings;
- the legal-page placeholders;
- the version reporting.

### Observations, not changed

- **The unencrypted port with the default bind.** The default `APP_BIND=0.0.0.0` publishes port 8080 over plain
  HTTP on every interface. With a proxy on the same host, the unencrypted port stays reachable unless the operator
  sets `APP_BIND=127.0.0.1`. The docs say this in install step 1 and in the reverse-proxy section. Changing the
  default would break the "no proxy yet, try it on port 8080" path, so it stays a documented choice.
- **One stack per host.** `docker-compose.yaml` fixes the project name `nusszopf`. A second installation in another
  directory on the same host would take over the first one's containers and volumes. Nothing documents running two
  instances, and none was needed here.
- **Floating image tags.** `redis:alpine` and `postgres:16-alpine` follow upstream. `redis:alpine` can move to a new
  major version on a `docker compose pull`. This is for P-9 (upgrades) to judge.

## Who handles what

**What the operator must know from the documentation:**
- the host prerequisites: Docker Engine with Compose v2, `curl`, `openssl`, root or `sudo`;
- the public address, as `install.sh`'s argument;
- `MAIL_FROM_ADDRESS` (required) and the SMTP relay (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`),
  plus the DNS records that let the relay send for that address;
- `APP_BIND=127.0.0.1` and a TLS proxy in front of port 8080;
- optionally `NUSSZOPF_CONTACT_EMAIL`, `LOCATIONIQ_KEY`, and `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`;
- the three legal texts in `legal/`;
- to back up `.env`, which holds `APP_KEY`, and `legal/`;
- to wait for `healthy` before judging `nusszopf:health`.

**What `install.sh` does automatically:**
- downloads the release's `docker-compose.yaml` and `.env.production.example`;
- generates `APP_KEY`, `DB_PASSWORD`, `MEILISEARCH_KEY` and `HEALTH_TOKEN`;
- writes `APP_URL` and the release version into `.env`;
- turns `SESSION_SECURE_COOKIE` off for an `http://` address;
- creates `legal/` and sets `.env` to mode 600;
- refuses to overwrite an existing `.env`.

**What the containers do automatically:**
- wait for their dependencies, in health order;
- `php-fpm` refuses to start without `APP_KEY`, then runs the migrations (locked), warms the caches and applies the
  search index settings;
- the queue worker retries failed jobs;
- the scheduler writes the heartbeats and runs the newsletter purge;
- the healthchecks run for all seven services.

The operator needs no Artisan command to reach a working instance.

**Deferred to later phases:**

| Limitation | Owner |
|---|---|
| A real tag, the GitHub Release download (including the `latest` URL), the pull from GHCR, arm64 images, and the pull time | P-16 (P-15 for publishing the repository) |
| A fresh install by a second person who has not seen the code | P-16 ("an RC installed by a second person") |
| A real SMTP relay, deliverability, SPF and DKIM | P-13 |
| A public domain with an ACME certificate, instead of `tls internal` | P-16 (the RC on a real host) |
| Upgrading from this install to a newer release; floating third-party tags | P-9 |
| Backup and restore of this installation | P-10 |
| Losing and rebuilding the search index | P-11 |
| Queue and scheduler failure drills | P-12 |
| Checking the rest of `docs/` for stale content (only the pages above were read for P-8) | P-14 |

## Changed files

- `.env.production.example`: `MAIL_FROM_ADDRESS` is required and has no default; `APP_TIMEZONE` removed; repository
  URL in the header.
- `.env.example`: `APP_TIMEZONE` removed.
- `docker-compose.yaml`: requires `MAIL_FROM_ADDRESS`; repository URL in the header.
- `scripts/install.sh`: rewritten last lines (P8-02, P8-03, P8-04).
- `scripts/smoke-test.sh`: the P8-03 regression checks, and a test sender.
- `scripts/prod-e2e.sh`: a test sender.
- `README.md`: the quick start.
- `docs/deployment/README.md`: status line, required configuration, prerequisites, install steps.
- `docs/deployment/operations.md`: the 03:30 UTC schedule; the `restart` troubleshooting entry.

After the fixes, `sh scripts/smoke-test.sh` passed. Run 2 on a new clean host passed every check above.

## Status

**Done.** Awaiting the maintainer's review. The documented install path works from a bare host to a healthy instance,
signed in and searchable, in under three minutes of machine time. Every stumble is fixed in the documentation or the
operator files. The one installation defect, P8-03, is fixed with regression coverage.

Two parts cannot be done before a release exists, and both are deferred, not waived:
- the real GitHub and GHCR download path (P8-01, P-16);
- an install by a person who has not seen the code (P-16).
