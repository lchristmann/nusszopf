# P-16 Release preparation and RC testing (started 2026-09-26)

Exit evidence (`master-roadmap.md` §4): "An RC installed by a second person; no open Blocker; the real-iPhone/real-Android
smoke pass deferred from P-5 executed and recorded."

**Status: In progress.** This page is the ledger. Every item is listed with its state; nothing is marked done before it
was observed, and nothing is waived without the maintainer saying so here.

Maintainer decisions at the start of the phase (2026-09-26):

- The first tag is **`1.0.0-rc.1`** (`docs/release/versioning.md`).
- A second candidate, **`1.0.0-rc.2`**, is published so that `rc.1` is the N-1 of the first real upgrade test.

## Checklist

State values: **Done**, **Blocked** (waiting for something outside the repository, named), **Open**, **Deferred by the
maintainer** (only with the maintainer's word, dated).

| # | Item | Source of the obligation | State |
|---|---|---|---|
| 1 | First-release version and changelog: number decided, `CHANGELOG.md` consolidated, the release workflow's notes extraction verified | P-14, `versioning.md`, `changelog.md` | Done (section 1) |
| 2 | The CI gate is green on the commit that is tagged | `release.yml` runs the whole CI as its gate | Open (section 2) |
| 3 | Build and publish `1.0.0-rc.1` to GHCR by the release workflow; both packages public | `release-process.md` | Open |
| 4 | `releases/latest/download/install.sh`, `install.sh` without `NUSSZOPF_BASE_URL`, and the pull from GHCR (with its time) | P-8, P8-01 | Open |
| 5 | Production startup from the published images (`smoke-test.sh`-equivalent checks against the pulled images) | P-7 | Open |
| 6 | arm64: the images run, the drills pass | P-8…P-11 | Open |
| 7 | True N-1 → N upgrade: `upgrade-test.sh 1.0.0-rc.1 --suite` to `rc.2`, `install.sh --upgrade` from `releases/download/<version>/`, `--upgrade` without a version | P-9 | Open |
| 8 | Rollback and restore: `restore-test.sh --rollback-from 1.0.0-rc.1`; restore on a physically separate machine | P-10 | Open |
| 9 | Search-recovery drill on the RC, images from GHCR, and on arm64 | P-11 | Open |
| 10 | Queue/scheduler drill on the RC, images from GHCR, and on arm64; the Redis AOF caveat of the upgrade | P-12 | Open |
| 11 | Real iPhone (iOS Safari) checklist, including DEV-01 on a real iPhone | P-5 | Open |
| 12 | Real Android (Chrome) checklist | P-5 | Open |
| 13 | The seven mails in Gmail, Outlook (desktop and web) and Apple Mail, including whether the inline SVG logo shows and how the self-hosted Barlow renders | P-13, P-15 | Open |
| 14 | The RC installed from the documentation by a second person, on a real host with an ACME certificate | P-8, roadmap P-16 | Open |
| 15 | `search.spec.ts`'s recovery test wipes the shared index (race between parallel engines) | P-11 | Open |
| 16 | No open Blocker | roadmap P-16 | Open |

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
