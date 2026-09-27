# P-16 Release preparation and RC testing (started 2026-09-26)

Exit evidence (`master-roadmap.md` §4): "An RC installed by a second person; no open Blocker; the real-iPhone/real-Android
smoke pass deferred from P-5 executed and recorded."

**Status: In progress. P-16 cannot be closed yet:** the automated release checks are done, and the parts that only a
person can do are not (real devices, the mail clients, the second person's install). This page is the ledger. Every item
is listed with its state; nothing is marked done before it was observed, and nothing is waived without the maintainer
saying so here.

Maintainer decisions at the start of the phase (2026-09-26):

- The first tag is **`1.0.0-rc.1`** (`docs/release/versioning.md`).
- A second candidate, **`1.0.0-rc.2`**, is published so that `rc.1` is the N-1 of the first real upgrade test.

**The release candidate under test is `1.0.0-rc.2`** (commit `c3af7e3`, tag `1.0.0-rc.2`, GHCR images
`ghcr.io/lchristmann/nusszopf-php-fpm:1.0.0-rc.2` and `…/nusszopf-web:1.0.0-rc.2`, both `linux/amd64` and `linux/arm64`).
`1.0.0-rc.1` is published too but cannot be installed with its own installer (P16-04). Neither is `latest`.

## Checklist

State values: **Done**, **Open** (named with what it waits for), **Deferred** (only with the maintainer's word, dated).

| # | Item | Source of the obligation | State |
|---|---|---|---|
| 1 | First-release version and changelog: number decided, `CHANGELOG.md` consolidated, the notes extraction verified | P-14, `versioning.md`, `changelog.md` | **Done** (section 1) |
| 2 | The CI gate is green on the commit that is tagged; the gate's flakes found and fixed | `release.yml` runs the whole CI as its gate | **Done** (sections 2, 3: P16-01…03, 06, 07) |
| 3 | Build and publish the RC to GHCR by the release workflow; both packages pullable without login | `release-process.md` | **Done** (`rc.1`, `rc.2`; section 3) |
| 4 | The real download path: `install.sh` from `releases/download/<tag>/` without `NUSSZOPF_BASE_URL`, the GHCR pull and its time | P-8, P8-01 | **Done** on `rc.2` (section 5): pull 26 s, healthy 60 s later. Found and fixed P16-04 |
| 4b | `releases/latest/download/install.sh` and `--upgrade` without a version | P-8, P-9 | **Open, cannot be checked before a stable tag**: GitHub's "latest" never is a pre-release (P16-05). To do when `1.0.0` is tagged. Not waived |
| 5 | Production startup from the published images | P-7 | **Done** (section 5): `release-check.sh` and every drill, natively on amd64 and arm64 |
| 6 | arm64: the images run, the drills pass | P-8…P-12 | **Done except one drill step** (section 5): install, real upgrade, upgrade with the browser suite, restore and rollback, search recovery all pass natively on arm64; the queue/scheduler drill's step 5 does not (P16-11) |
| 7 | The true N-1 → N upgrade | P-9 | **Done** (section 5): `1.0.0-rc.1` → `1.0.0-rc.2`, both from GHCR, populated data, the whole browser suite after, amd64 (here and on GitHub) and arm64; `install.sh --upgrade` from the real URL |
| 8 | Rollback and restore | P-10 | **Done** for the rollback from `rc.1` and the restore onto an empty host (GitHub, amd64 and arm64). **Open**: the restore on a physically separate machine, which every drill so far replaced with a Docker-in-Docker host on one kernel; it belongs to the second person's real host (item 14) |
| 9 | Search-recovery drill on the RC | P-11 | **Done** (section 5), amd64 and arm64 |
| 10 | Queue/scheduler drill on the RC, and the Redis AOF caveat of an upgrade | P-12 | **Done on amd64** (all nine steps, on GitHub). **arm64: open** (P16-11). The AOF caveat is **closed**: the session and the queued jobs of `rc.1` survived the upgrade |
| 11 | Real iPhone (iOS Safari): the eight steps of `P-05-browsers-devices.md`, DEV-01 included | P-5 | **Open: needs a person and a device** (section 6) |
| 12 | Real Android (Chrome): the same eight steps | P-5 | **Open: needs a person and a device** (section 6) |
| 13 | The seven mails in Gmail, Outlook (desktop and web) and Apple Mail, the inline SVG logo and the self-hosted Barlow included | P-13, P-15 | **Open: needs the maintainer's mailboxes** (section 6) |
| 14 | The RC installed from the documentation by a second person, on a real host with an ACME certificate | P-8, roadmap P-16 | **Open: needs a second person and a host** (section 6) |
| 15 | `search.spec.ts`'s recovery test wipes the one shared index | P-11 | **Open, not a blocker**: the gate passed six times; kept as a known limitation (section 7) |
| 16 | No open Blocker | roadmap P-16 | Two blockers were found and fixed (P16-04, P16-07). **None is open**, unless P16-11 or the open items above turn out to be one |

## 1. Version and changelog (Done)

- The number was decided by the maintainer: `1.0.0-rc.1`. The register, `architecture-decisions.md`, `versioning.md`
  and `release-process.md` were amended so no document still says `0.x` or `0.1.0-rc.1` (the earlier phase pages keep
  their historical wording).
- `CHANGELOG.md` was empty; it now has an empty `Unreleased` section and a `1.0.0-rc.1` section consolidated from the
  approved entries of `docs/rewrite/intentional-changes.md` and the findings of P-4…P-15. Its "Known limitations" list
  names every open item of this page.
- `release.yml` extracts the notes with an `awk` over `## [<tag>]`. Run against the file: `1.0.0-rc.1` yields 76 lines,
  a tag without a section (`1.0.0-rc.2`) yields none, which is the case in which the workflow refuses to publish.
  Every further candidate therefore needs its own section before it is tagged.
- Local gate on the tagged commit's tree, on the dev stack: Pint (177 files), Larastan (85 files, no errors), Pest
  (625 tests, 2281 assertions), `composer audit` and `npm audit` (no advisories).

## 2. CI on GitHub (Done, after three findings)

**The gate had not run since 2026-09-23.** Every CI run from 2026-09-23 22:04 on, seven in a row, ended with "The job was
not started because recent account payments have failed or your spending limit needs to be increased": no job started, so
no test, no lint, no build. Commits of P-7…P-15 were therefore never checked by GitHub. Since the repository became
public, jobs start again (the push of this phase's first commit was the first to run). The gate runs before a tag
publishes anything, so this had to be settled before the tag.

Three things stood in the way of a green gate. Each was reproduced locally first, on an empty stack with CI's settings
(`CI=1`, one worker, `SEARCH_PAGE_SIZE=5`, the `E2E_*` variables), and each is a defect of the test suite or its baselines,
not of the product:

| ID | Finding | Kind | State |
|---|---|---|---|
| P16-01 | `zz-aria.spec.ts`, a developer's accessibility-tree dump, needs the visual reference dataset (the `demo` user and a fixed project id). On any other stack it timed out at the login. It is not run by `prod-e2e.sh`, but the three desktop engines of the CI job ran it. Introduced in `20d3eeb` on 2026-09-23 23:15, so it never ran in CI. It failed in chromium, firefox and webkit; the three phone/tablet projects skip it, which is why they were green | Test defect | Fixed: the spec skips itself unless `E2E_ARIA_DUMP=1` |
| P16-02 | `axe.spec.ts` ("visitor screens and states") creates a project and opens `/search` at once. The queue worker indexes it a moment later and the page does not refresh, so on an empty database (CI's) no card was found. `search.spec.ts` already waited for the index. It failed in chromium, firefox, webkit and in the production-image job | Test defect (race) | Fixed: the spec reloads until the card is there, as `search.spec.ts` does |
| P16-03 | Nine visual baselines (the three legal screens at three widths) still showed the original operators' legal texts. P-15 replaced them with the placeholders and the "Beispiel, nicht zur Veröffentlichung" banner, so the visual job failed. The diff was text reflow only: header, typography and layout are unchanged | Stale baseline, an approved change (P-15) | Fixed: the nine baselines were rewritten with `--update-snapshots=all`; exactly those nine files changed |

- Regression coverage: the fixed specs pass on an empty database with CI's settings (58 passed, 2 skipped: the aria dump
  and the reindex-command spec that needs the runner's Docker). One failed run in between was the documented shared
  newsletter budget (10 requests per 15 minutes per IP) exhausted by my own repeated local runs; it is not a finding.
- CI on `73dbd7e`: all 11 job groups green (Pint, Larastan, Pest, frontend build, production stack, the whole suite on
  the production images, three engines and three device projects on the dev stack, and the visual comparison). The
  Security workflow was green on its first run on GitHub as well.
- Observed but not blocking: the Actions runner warns that Node.js 20 actions are forced to Node 24
  (`actions/checkout@v4`, `setup-node@v4`, `upload-artifact@v4`, `setup-buildx-action@v3`), and that `ubuntu-latest`
  moves to Ubuntu 26 on 2026-10-19. Dependabot proposes the action bumps.
- Lesson recorded for the release process: a red or never-started CI is invisible from the terminal. Before every tag,
  check the badge or the run page of the exact commit (`docs/release/release-process.md`).

## 3. `1.0.0-rc.1` published, and what its real path revealed

The first attempt at the tag failed: the gate's Pest job ended with exit code 2 while the same commit's push run had
passed it, and the log needs a GitHub login. The step was made readable without one (`ci.yml` now writes each failing test
as an annotation), the tag was moved (nothing had been published from it), and the second gate passed all 13 jobs,
Pest included. The same test then failed once more on a push run, and this time the annotation named it: P16-06.

Release run `36270376826` on commit `138e63e`, 2026-09-26:

- The images `ghcr.io/lchristmann/nusszopf-php-fpm:1.0.0-rc.1` and `…/nusszopf-web:1.0.0-rc.1` exist for `linux/amd64`
  and `linux/arm64` (`docker manifest inspect`), carry `org.opencontainers.image.version=1.0.0-rc.1` and the
  repository as their source, and `latest` was not moved. The multi-arch build took about 40 minutes on GitHub's
  runners (arm64 under QEMU).
- Both packages could be pulled without logging in, from the first push (the repository is public). From this
  machine, `docker pull` of `nusszopf-php-fpm` (935 MB) took 38 s; `nusszopf-web` took 1.6 s, its layers being shared.
  The pulled image reports `NUSSZOPF_VERSION=1.0.0-rc.1` and Laravel 13.
- The GitHub Release is a pre-release with the changelog section as its notes and three files attached.

| ID | Finding | Kind | State |
|---|---|---|---|
| P16-04 | **Blocker.** The release attaches `.env.production.example`, and GitHub strips a leading dot from an asset's name: it is served as `default.env.production.example`. `install.sh` requested `.env.production.example`, which is a 404 (`releases/download/1.0.0-rc.1/.env.production.example`). So the documented install could not work from a real release: the path P-8 could not test (P8-01) and that every harness bypassed with `NUSSZOPF_BASE_URL=file://…` | Defect of the release mechanism | Fixed: the asset is `env.production.example`, `install.sh` downloads that name and still keeps `.env.production.example` on the host; the harnesses serve the real name (and, for an older tree's own installer, also the dotted one); `tests/Feature/Release/ReleaseAssetsTest.php` fails on rc.1's files (3 of 3) and passes on the fix; `scripts/smoke-test.sh` passed with the new installer |
| P16-06 | `ReindexSearchTest` ("answers in the same order after a rebuild", the regression test of BUG-047) failed on GitHub's runners on two of four runs and never locally. The four requests of the test were created one after the other and each kept the `project` it had loaded at its creation, so its indexed `updated_at` (in seconds) could be older than the others' when a second boundary fell between two creations. `updated_at:desc` ranks before `id:asc`, so that one document came last (`[B, C, D, A]`). Shown deterministically with a 2 s gap between the creations (stale: wrong order; fresh models: right order), and with Meilisearch throttled to 0.25–0.5 CPUs; with the fix 30 of 30 throttled runs pass. The product is not affected: a project's requests are written from one state of the project, and `search:reindex` reads them fresh | Test defect (fixture) | Fixed: the test reads fresh models; it also waits for Meilisearch's task queue to drain (`awaitIndexIdle()`) instead of trusting document counts |
| P16-07 | **The avatar dialog's "Speichern" was enabled before it could work.** `hasImage` turns true when the file has been read; the cropper is built later (its chunk is fetched, two animation frames, the image loads), and `save()` returns silently without one. A click in that window did nothing and showed no message. It failed the production-image job of the rc.2 gate (`[mobile-chrome] profile.spec.ts › uploads, crops and replaces an avatar`, which clicked at once in its second round), reproduced locally with the workflow's settings. On a slow phone the window is the network time of a 60 kB chunk, so a user can hit it too. A defect of Nusszopf 2, not historical behaviour (no `bugs.md` entry; like SEC-xx, PERF-xx) | Product defect (small) | Fixed: the dialog tracks `ready` (cropperjs' `ready` callback); Speichern and the rotate/zoom buttons wait for it. New Playwright test holds the cropper chunk back and asserts the buttons are disabled, then enabled and working: it fails on the old code ("Received: enabled") and passes now; the older test waits for the button in both rounds |
| P16-05 | `releases/latest/download/…` is a 404 while only pre-releases exist, because GitHub's "latest" never is a pre-release. The README and the deployment guide told operators to use it | Documentation, GitHub's rule | Fixed in the docs: a candidate is installed by naming it (`releases/download/<tag>/install.sh`, `sh install.sh <url> <tag>`, `--upgrade <tag>`). The `latest` URLs themselves can be checked only once a stable tag exists: **deferred to the `1.0.0` tag, not waived** (checklist item 4b) |

`1.0.0-rc.1` therefore stays published as a pre-release that cannot be installed with its own `install.sh`; its tag is not
moved, since image tags are immutable (`docker-images.md`). It is the N-1 of the upgrade test, and its manual install is
described there. `1.0.0-rc.2` carries the fix.

## 4. How the release-only checks run

Everything the previous phases could only do on locally built images now has a way to run on the published ones:

- `scripts/release-check.sh <tag>` installs a published release as an operator does: `install.sh` downloaded from
  `releases/download/<tag>/`, no `NUSSZOPF_BASE_URL`, the pull from GHCR (timed), start, `nusszopf:health`, the public
  pages, the CSP header and the scheduler. It uses nothing built or faked.
- `RELEASE_TAG=<tag>` on `search-recovery-test.sh`, `queue-scheduler-test.sh`, `upgrade-test.sh` and `restore-test.sh`
  pulls the published images of that tag (and, for the upgrade and the rollback, of the previous tag) instead of
  building them. The working copy must be a checkout of that tag, because its compose file, installer and template are
  the release's.
- `.github/workflows/release-verify.yml` runs these on GitHub's own amd64 and arm64 runners (arm64 natively, no
  emulation). It starts when a tag `verify/<tag>` pointing at the release's commit is pushed, or by hand. It publishes
  nothing. A failing drill writes the end of its output as an annotation, since job logs need a GitHub login.
- The arm64 evidence therefore comes from a native runner, not from this machine, which has only amd64 and no QEMU.
  `docker run --platform linux/arm64` here fails with `exec format error`; installing emulation would change the host
  and is not needed.
