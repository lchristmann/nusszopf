# P-15 FOSS repository hygiene (2026-09-26)

Exit evidence (`master-roadmap.md` §4): "Checklist against Waffle Dashboard's repository layout". The phase covers:
LICENSE, CONTRIBUTING, SECURITY.md, CODE_OF_CONDUCT, issue/PR templates, third-party license notices, asset provenance
(A-5), Dependabot/Renovate, the `.env.example` audit and "no secrets in history" (roadmap §7.5, item 21).

**Status: Done (closed by the maintainer 2026-09-26, on commit `8c62991` with the decisions and fixes of section 8).**
Every item of the roadmap row now exists and was checked; twelve findings were fixed or recorded (section 2); the whole
Git history (118 commits) has no secret; the dependencies have no known vulnerability and no incompatible license. The
maintainer's decisions on the open points were applied and recorded (section 8). What is left is two GitHub settings
that only the maintainer can change from their account, and the first run of the workflows on GitHub (section 5).

## 1. Method

1. Read the roadmap row and §7.5, the parity report, the P-4 secret-handling notes, decisions A-4, A-5, A-8 and A-10,
   `docs/references/waffle-dashboard.md`, `README.md`, `README-DEV.md`, `docs/development/*` and the release documents.
2. Took the three historical repositories and Waffle Dashboard from GitHub (public, at the revisions of
   `docs/rewrite/source-map.md`) into a scratch directory, because the `../historical` and `../foss-reference` checkouts
   the documents name are not on this machine. LCxHolz is not public and could not be read; nothing here needed it.
3. Inventoried what the repository has (`git ls-files`, the workflows, the ignore files) against the list above and
   against what the historical repositories and Waffle Dashboard have.
4. Audited licenses from the lock files, the production build output and the assets (section 4), and the environment
   templates against every `env()` call of the code.
5. Scanned the secrets (section 3) and the dependencies (section 4).
6. Wrote the missing files from the existing documents; decided nothing that a document leaves to the maintainer.

## 2. Findings and fixes

| ID | Finding | Result |
|---|---|---|
| P15-01 | No `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, issue templates or pull-request template; `.github` held only the two workflows | **Fixed.** `CONTRIBUTING.md` restates the existing rules for people (read the register and `bugs.md` first, the bug-classification workflow, the checks in the order of `docs/development/README.md`, docs in the same change, no SaaS concepts); the pull-request template is the same checklist. `SECURITY.md`: private reporting through GitHub, latest release supported, scope, no promised response time (a single maintainer). Issue forms: bug report (asks whether the historical Nusszopf did the same), suggestion (product changes need approval, as `CLAUDE.md` says), `config.yml` with blank issues off and links to the private form and the operator's handbook |
| P15-02 | The historical repositories carry a Contributor Covenant **1.4** whose enforcement contact was never filled in (it reads "at undefined") | **Fixed** in the new file: Contributor Covenant **2.1**, unmodified except the contact, which is GitHub's private form, since the maintainer decided on no separate address (section 8). The historical `bug_report.md` template is a GitHub default; the new forms replace it |
| P15-03 | The production build carried **no license notices**: MIT and OFL require them to accompany what is shipped, and the bundle had no legal comments and the Barlow fonts no OFL text | **Fixed.** `vite.config.js`: `build.license` writes `public/build/THIRD-PARTY-LICENSES.txt` (the 37 bundled JavaScript packages) and a small plugin emits `public/build/LICENSE-barlow.txt`. Both are in the image (the frontend stage copies `public/build`) and served under `/build/` as `text/plain`, checked in the development stack. `NOTICE` names them |
| P15-04 | The environment templates had drifted from the code: `.env.example` lacked `RESEND_API_KEY`, `HEALTH_TOKEN`, `NUSSZOPF_REGISTER_LIMIT` and `LOCATIONIQ_URL`, and carried `VITE_APP_NAME`, which nothing reads | **Fixed** in `.env.example` (commented optional entries; the unused line removed). `.env.production.example` was already complete (P-14); every key of both templates is read by the code or the Compose files, and the keys of the code that they lack are framework defaults, the development stub URL and test knobs. No non-empty secret in either: only the documented development defaults (`DB_PASSWORD=nusszopf`) |
| P15-05 | The README's badge comment still waited for the stack and CI targets, both settled | **Fixed:** CI, Security and license badges, and the technology badges of `README-DEV.md`. There is no release badge until the first release (P-16). The CI and Security badges show "no status" until the workflows have run on GitHub (D7) |
| P15-06 | No dependency-update automation and no advisory check in CI (P-4's recommendation) | **Fixed.** `.github/dependabot.yml`: weekly, grouped pull requests for Composer, npm, GitHub Actions, the Dockerfile and the Compose files. It ignores what must not move alone: majors of PostgreSQL and Redis and any major or minor of Meilisearch (stored data: dump/import or reindex and the upgrade drills), the Playwright image and `@playwright/test` (they must match), Node majors; PHP takes its version from a build argument and is raised by hand. `.github/workflows/security.yml` runs `composer audit --locked`, `npm audit --omit=dev` and the secret scan on push, pull request and weekly. It is separate from `ci.yml` on purpose, so that an advisory published tomorrow cannot fail a release gate for a commit that changed nothing |
| P15-07 | Asset provenance was undocumented (A-5 decided the rights, not where each file comes from) | **Fixed:** `docs/legal/provenance.md` lists every shipped asset and dependency set with origin and terms. Confirmed by byte comparison with the historical repository: all five sponsor/partner logos, the big Nusszopf logo, all favicons, the pinned-tab SVG and `og-image.png` are identical to `web-nusszopf`; Feather (MIT, license text present), Barlow (OFL) and Lucide (ISC) are named; the one test image is a synthetic gradient |
| P15-08 | `docs/deployment/legal-examples/legal-notice.md` published two named people's street address and telephone number, again, under a new owner (the same address is in `legal-policy.md` and `privacy.md`, together with the names) | **Fixed on the maintainer's decision (register, "Repository hygiene decisions"):** names, street address and telephone number are bracketed placeholders in all three examples, whose first line says so; `legal-examples/README.md` explains it. `mail@nusszopf.org`, the historical operator's public contact, stays. Nothing else in the tracked files names a private person (searched: names, street, telephone number) |
| P15-09 | The mail layout loaded Barlow from `fonts.googleapis.com`; the register and roadmap say the email fonts are self-hosted | **Fixed on the maintainer's decision.** `vite.config.js` emits the Barlow Latin 500 and 700 files as `public/build/fonts/barlow-latin-{500,700}.woff2` (fixed names: the hashed names of `public/build/assets/` change with every build, and a mail sent last year must keep finding its font); the layout declares them with `@font-face` (absolute URLs of the instance) and keeps `'Barlow', Arial, sans-serif`, so clients that ignore web fonts or block remote content show Arial. Same weights, subset (Latin: German copy), family and sizes as the historical mail; no request goes to Google. Regression test in `tests/Feature/Mail/ContactMailTest.php`. Not verified in real Gmail/Outlook/Apple Mail (P-16's real-client pass) |
| P15-10 | The local, untracked `.env` holds a real Resend key (P-13) | **Not a finding for the repository:** the file is ignored (`.gitignore`), excluded from the image (`.dockerignore`), and its value is in no revision of the history (searched for the value itself, not for a pattern) |
| P15-11 | The repository is public (the maintainer's decision); `SECURITY.md`, the issue-form link and the Code of Conduct name GitHub's private vulnerability form as the only private channel | **Recorded.** No separate security or conduct mailbox, no `FUNDING.yml`: register, "Repository hygiene decisions". Private vulnerability reporting and Dependabot alerts/security updates could not be switched on from this environment (section 5, D1) |
| P15-12 | `docs/deployment/README.md` still said the repository would go public in P-15 | **Fixed** (it is public) |

## 3. Secrets

| Check | Result |
|---|---|
| gitleaks v8.30.1 over all revisions (`git /repo --log-opts="--all"`): 117 commits scanned | 3 findings, none a secret (below) |
| The root commit (the scanner reports 117 commits of the 118 revisions, so the root commit was scanned separately, as a directory) | No finding |
| The final working tree (tracked files plus new files, without ignored ones) | No finding |
| Every file name ever added to the history matching `.env`, key, pem, secret, credential, `auth.json`, dump, sqlite | Only `.env.example`, `.env.production.example`, `tests/Visual/historical-harness/hist.env`, `docker/postgres/init-testing-db.sql` (one `CREATE DATABASE` statement) |
| The values of the real local `.env` (Resend key, `APP_KEY`, the Meilisearch key) searched for in every revision | Not present. The Meilisearch key is the documented development default |
| Personal data: e-mail addresses and telephone numbers in the tracked files | Only `example.*` and `*.test` addresses, the historical operator's public `mail@nusszopf.org`, and P15-08 |
| Negative control: a file with a fake AWS key and a fake GitHub token | Both found, so the scanner and `.gitleaks.toml` are live |

The three findings, all set aside in `.gitleaks.toml` by exact line (a real secret anywhere else still fails the scan):

1. `tests/Visual/historical-harness/hist.env`, `MEILI_API_KEY`/`MEILI_PK`: the public key that Meilisearch v0.19 derives from
   the harness's own master key `meilipassword`. Verified by starting that image and calling `GET /keys`: the value is
   exactly the returned `public` key. Throwaway, and unused outside a local container.
2. `docs/security/README.md`: the local-development Hasura admin secret that the public `be-nusszopf` repository itself
   commits, quoted as archaeology (P-4 had already classified it).
3. `docs/rewrite/seventh-slice.md`: the prose "token single-use, ..." that matched the generic rule (a false positive).

No history rewrite was needed or done.

## 4. Dependencies and licenses

| Check | Result |
|---|---|
| `composer audit --locked` (Composer 2.10.3, PHP 8.5.11) | No security vulnerability advisories |
| `npm audit` (all) and `npm audit --omit=dev` | 0 vulnerabilities |
| Declared licenses in `composer.lock` (141 packages) and `package-lock.json` (187) | MIT, BSD-3-Clause, ISC, Apache-2.0, 0BSD, SIL OFL 1.1 and (build tooling only) MPL-2.0; the two `nette/*` packages offer BSD-3-Clause, used under it. All compatible with GPL-3.0-or-later; none missing or unknown. Table in `docs/legal/provenance.md` |
| Which of them ship | The image installs Composer with `--no-dev`; the browser bundle holds 37 MIT packages and the Barlow files; see P15-03 for their notices |

## 5. Remaining and deferred

| # | Item | Why | Owner |
|---|---|---|---|
| D1 | **Enable private vulnerability reporting** (repository Settings, Code security, "Private vulnerability reporting") and **Dependabot alerts and Dependabot security updates** (same page). Then check that the CI and Security badges resolve, and set the description and topics. Suggested description: "Revival and faithful reimplementation of Nusszopf, a free, self-hostable application"; topics: `laravel`, `livewire`, `self-hosted`, `foss`, `postgresql`, `meilisearch`, `docker` | **Not done, cannot be done from this environment:** there is no `gh` CLI and no GitHub token here (the remote is an SSH URL), so the settings were neither changed nor read back. They are the maintainer's actions, not complete. Until private reporting is enabled, the "Report a vulnerability" links in `SECURITY.md`, `CODE_OF_CONDUCT.md` and the issue form lead to a page that does not accept reports. (The repository itself is public: an anonymous `git ls-remote` and the API answer without credentials.) | Maintainer, before P-16 |
| D6 | The Home sponsor row naming Vercel, Auth0 and Sanity (A-5 asked for a re-read "before the release candidate") | The maintainer's explicit fidelity choice; not a hygiene defect | P-16 |
| D7 | The first Dependabot pull requests, and the `Security` workflow's first run on GitHub, cannot be seen until the maintainer pushes the commits and enables D1; the workflow's three commands were run locally as written (section 6) | Needs the pushed repository | Maintainer / P-16 |
| D8 | A docs link check in CI (P-14 D5 suggested it here) | **A non-blocking future improvement, by decision** (section 8). The links of the pages written and changed here were checked by hand | later, unscheduled |
| D9 | `CHANGELOG.md` content, the first tag's number, GHCR/arm64 checks, real-device and mail-client passes (this includes how the self-hosted Barlow renders in real clients) | Unchanged, P-16 | P-16 |

Closed by the maintainer's decisions of section 8: the conduct/security address (D2: none), `FUNDING.yml` (D3: none), the
named people in the legal example (D4) and the mail layout's Google Fonts link (D5).

## 6. Validation

| Check | Result |
|---|---|
| `composer lint:check` (Pint), `composer larastan` (level 7), `composer test` (Pest) in the development stack | Pass: 177 files, no errors, 624 tests and 2276 assertions |
| `npm run build` in the development stack, then `curl` of both notice files | Built; `THIRD-PARTY-LICENSES.txt` and `LICENSE-barlow.txt` answer 200 `text/plain` under `/build/` |
| The same build in a clean directory from `package-lock.json` (`npm ci`) | Same output. The Dockerfile's frontend stage runs the same command with `node_modules` present, where the plugin reads the font's license |
| YAML syntax of `dependabot.yml`, `security.yml` and the three issue files | Parsed |
| The workflow's commands, run locally | `composer audit --locked`, `npm audit --omit=dev` and the pinned gitleaks image command: all clean |
| Links and repository paths in `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, `NOTICE`, `docs/legal/provenance.md` and the changed docs | All resolve |

After the decisions of section 8 (2026-09-26):

| Check | Result |
|---|---|
| `composer lint:check`, `composer larastan`, `composer test` in the development stack | Pass: 177 files, no errors, 625 tests and 2281 assertions (one test added: the rendered mail names no font provider and points at the two local font files) |
| `npm run build`, then `curl` of `/build/fonts/barlow-latin-500.woff2` | Both files emitted (22.0 and 22.8 kB); answers 200 `font/woff2` |
| gitleaks v8.30.1 over all 118 commits, the workflow's command | No leaks. A scan of the working directory (not what CI does) reports only the ignored local `.env` (P15-10) |
| Search of the tracked files for the names, the street and the telephone number, and for `fonts.googleapis.com` | Only this report and `open-questions.md` mention the font host, as history |
| The legal examples on `/legalNotice`, `/legalPolicy` and `/privacy` | Covered by the existing `LegalPagesTest` (banner, labelled example): pass |

Not run: the Playwright and production-image suites and the visual suite (its `reseed.sh` empties the development
database, and the legal pages are already listed as an accepted difference in `docs/testing/visual-regression.md`; no
other screen changed), and the workflows themselves on GitHub (D7).

## 7. Checklist against Waffle Dashboard's repository layout

Waffle Dashboard's root (checked out from GitHub at `0f57a0b`) has `LICENSE`, `README.md`, `DEVELOPER-DOCS.md`,
`.env.example`, `docker-compose.yaml`, `compose.dev.yaml`, `compose.prod.yaml`, `.dockerignore`, `.editorconfig`,
`.gitattributes`, `.gitignore`, `docs/` (an installation guide and a contribution guide), and no `.github` directory.

| Waffle Dashboard | Nusszopf |
|---|---|
| `LICENSE`, `README.md` | Yes, and `NOTICE` |
| `DEVELOPER-DOCS.md` | `README-DEV.md` and `docs/development/` |
| Installation guide, contribution guide in `docs/` | `docs/deployment/`, and `CONTRIBUTING.md` |
| `.env.example`, `docker-compose.yaml`, `compose.dev.yaml`, `compose.prod.yaml` | Yes, plus `.env.production.example` and `install.sh` |
| `.dockerignore`, `.editorconfig`, `.gitattributes`, `.gitignore` | Yes |
| (nothing) | `SECURITY.md`, `CODE_OF_CONDUCT.md`, issue forms, pull-request template, `dependabot.yml`, `CHANGELOG.md`, three workflows |

The historical repositories have `LICENSE`, `CODE_OF_CONDUCT.md`, `.github/FUNDING.yml` (all three, funding aside) and
one bug-report template; the roadmap's "historical repos have one" is now **Confirmed** (Contributor Covenant 1.4).

## 8. Sign-off and decisions

Approved by the maintainer on 2026-09-26, with these decisions and fixes, all applied in the closing change and
recorded in `docs/rewrite/decisions-register.md` ("Repository hygiene decisions"; former C3-C6 are resolved) and, for
the fonts, `docs/rewrite/open-questions.md`:

1. The repository is public.
2. The named people's names, street address and telephone number are replaced by placeholders in the legal examples
   (P15-08).
3. GitHub private vulnerability reporting is the security contact; no separate security mailbox.
4. No `FUNDING.yml`.
5. The mail layout no longer depends on Google Fonts; the fonts are self-hosted (P15-09).
6. The docs link check stays a non-blocking future improvement (D8).
7. Enabling private vulnerability reporting and Dependabot alerts/security updates: a maintainer action (D1), listed
   above as not done.

Not started: P-16 and later. The first tag's number stays deferred.
