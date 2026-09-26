# P-14 Documentation completion (2026-09-26)

Exit evidence (`master-roadmap.md` §4): "Doc review checklist, all 'Proposal' banners resolved."

**Status: Done (automated part), awaiting the maintainer's review.** Every documentation page was checked against the
implementation and the evidence of P-1…P-13; twelve substantive problems were found and fixed (section 2); no page is
labelled Proposal any more (section 3); the internal links and the referenced paths resolve. Items that need a release
or a decision, or that belong to P-15/P-16, are listed in section 5 and were not guessed. The phase is not closed until
the maintainer says so.

## 1. Method

1. Read the roadmap entry, the parity report (`README.md`, `00-doc-debts.md`, every carry-forward that names P-14) and
   the pages of P-8…P-13.
2. Read the operator and contributor documents in full: `README.md`, `README-DEV.md`, `docs/deployment/*`,
   `docs/development/*`, `docs/testing/README.md`, `docs/architecture/*`, `docs/release/*`, `docs/email/README.md`, the
   decision register and the architecture decisions. Searched the rest for stale markers (`Proposal`, `needs approval`,
   `not yet`, `once … exists`, slice-era limitations).
3. Compared claims with the code and configuration: routes, `app/`, `routes/console.php`, `composer.json`,
   `package.json`, `phpstan.neon`, `.github/workflows/*`, both Compose files, `.env.example`, `.env.production.example`,
   `scripts/*`, `tests/`.
4. Ran the mechanical checks in section 4.
5. Reproduced the two claims that changed the dev onboarding (P14-01) instead of trusting them.

## 2. Findings and fixes

| ID | Finding | Result |
|---|---|---|
| P14-01 | **The dev first-run sequence in `README-DEV.md` and `docs/development/README.md` was wrong in four ways.** (a) It ran `key:generate` after `docker compose up`, but Compose hands `.env` to every container as its environment, and an empty `APP_KEY` there wins over the key written into the file later. Reproduced: with `APP_KEY=` in the environment and a key in `.env`, `config('app.key')` had length 0; without the override, 51. `docker run --env-file` with an `APP_KEY=` line sets the variable, empty. The containers therefore ran without a key until recreated. (b) `MEILISEARCH_KEY` ships empty, so the application sends no key to a Meilisearch that runs with the fallback master key (P-12; the CI comment explains it). (c) A new `vendor`/`node_modules` volume is owned by root: reproduced, a user 1000 container cannot write to a fresh one, so `composer install` fails (P-12). (d) `storage:link` was missing from `README-DEV.md`, so avatars 404 in development | **Fixed.** Key generated and `MEILISEARCH_KEY` set before the first `up`, one `chown` line, `storage:link` added, `search:reindex` replacing `scout:sync-index-settings` (as CI does), and a troubleshooting table of problems actually hit. The sequence was checked piece by piece; a complete run on a fresh clone was not done (it would collide with the running development stack's project name) |
| P14-02 | `docs/architecture/README.md` still said "Status: Proposal … Nothing here has been implemented" and described modules, naming, the newsletter list sync, background work ("Scheduled: none") and the mail provider as open | **Rewritten as built**: the module map with namespaces, `ProjectRequest`, no list sync (A-6), the queued mails, the purge task, the mail transport |
| P14-03 | The P-13 mail decision was recorded nowhere except the P-13 page. The register's B5 said "SMTP as the universal path"; deployment, operations, email, `install.sh`, `.env.production.example` and the README named an SMTP relay as the way to send | **Fixed.** Recorded in `decisions-register.md` ("Mail provider"), `architecture-decisions.md` and `architecture/mapping.md`: Resend is recommended, Nusszopf uses Laravel's mail abstraction and has no provider abstraction of its own, SMTP stays supported (untested against a real TLS relay). Operator pages, the install output and both env templates say the same. A new "Mail" section in `operations.md` covers running it and the failure modes P-13 observed |
| P14-04 | `docs/deployment/README.md`: the scheduler "only" had the two heartbeats (there is a third task, the newsletter purge); the file table omitted five scripts; no page listed every setting; the status banner stopped at P-10; "Open questions" listed decided items | **Fixed.** Corrected, a configuration reference table of every setting of `.env.production.example` added, decisions and the truly open items separated |
| P14-05 | `operations.md`: the SMTP row said the mail path was unverified beyond Mailpit, no mail troubleshooting, banner stopped at P-12, "No log aggregation is proposed" | **Fixed** (see P14-03); the row now cites P-7 and P-13 |
| P14-06 | `docs/release/*` still labelled decided and implemented items "Inferred, needs approval" (versioning, changelog, release process, images, upgrades, breaking changes, the Waffle reference). `versioning.md` said the compatibility policy was "to be defined" | **Fixed.** Each now says what was decided, where, and what is untested until a real tag (P-16). Found on the way: the number of the first tag is inconsistent (section 5, D2) |
| P14-07 | `docs/testing/README.md`: "target shape" tree did not match `tests/` (`Unit/` is empty), the Playwright conventions claimed a `global-setup` and seeded `storageState` that do not exist (specs register their own accounts), the CI section described LCxHolz's pipeline | **Fixed** to the real layout and the real CI jobs; the P-11 spec race documented as a known limitation |
| P14-08 | `docs/development/quality.md`: the gates table lacked the visual, production-stack and production-E2E jobs and named a Docker-build command that no longer is a gate; it said coverage "is measured and reported" (there is no coverage job) and the database strategy was an "open architecture decision" (CI runs PostgreSQL service containers) | **Fixed** |
| P14-09 | `docs/development/README.md`: Laravel Boost described as if configured (it is not installed; there is no `.mcp.json` or `boost.json`), queue commands duplicated the Compose services, "Troubleshooting: to be filled in" | **Fixed** |
| P14-10 | Stale cross-references and slice-era limitations: `bugs.md`, `intentional-changes.md` and `golden-master-review.md` named `ProjectFormTest.php`/`ProjectForm.php`, which the second slice split (the regression test is `ProjectEditTest.php`, "404s a non-owner…"); closed limitations still open in the fifth, sixth, seventh and eighth slice pages; `screen-specs.md` "once Scout sync exists"; `journeys/README.md` "slice 5 adds that preview" (it did, and the spec asserts it); `golden-master.md` "not yet approved for implementation"; `laravel-docker-examples.md` "Nusszopf (proposed)" columns and "Proposal — requires approval" | **Fixed**, in the doc-debts convention: struck through with the phase that closed it, or updated |
| P14-11 | Indexes: `docs/README.md` and `docs/rewrite/README.md` listed four slices and called architecture "proposed" and the parity phases "in progress"; the root `README.md` said the public shell "is the last one still to come" and gave a quick start that does not work before the first release without saying so | **Fixed** |
| P14-12 | The parity report linked to `P-14`, `P-15` and `P-16` pages that did not exist; its carry-forwards for P-14 were open | **Fixed**: this page exists, the P-15/P-16 rows are plain text until their phases create the pages, the P-14 carry-forwards are closed and the rest assigned |

No finding is a historical defect, so `docs/rewrite/bugs.md` and `intentional-changes.md` gained no entry. Code changed only
where operator-visible text was wrong: the closing message of `scripts/install.sh` and two comments in the env templates.

## 3. Proposal, TODO and TBD banners

| Where | State |
|---|---|
| `docs/deployment/operations.md` | Promoted before P-14 (O-1/O-2); its status line now covers P-9…P-13 and says what only a release can prove |
| `docs/architecture/README.md` | "Proposal" resolved (P14-02) |
| `docs/references/laravel-docker-examples.md` | "Proposal" cells and heading resolved (P14-10) |
| `docs/release/*` | "Inferred, needs approval" resolved or re-labelled as reasoning (P14-06) |
| `docs/development/README.md` | "To be filled in" resolved (P14-09) |
| Remaining hits of `proposal`, `proposed`, `TODO` | Historical evidence (`open-questions.md`, `bugs.md` and `intentional-changes.md` status vocabulary, the `todo` comment quoted from the historical Apple button) or quotes of decisions; none marks a Nusszopf 2 page as provisional |

## 4. Validation

| Check | Result |
|---|---|
| Internal Markdown links and anchors across `docs/`, `README*.md`, `CLAUDE.md`, `.claude/rules/` (a script written for this phase) | 0 broken after the fixes; it had found the three missing parity pages (P14-12) |
| Paths named in backticks (`app/…`, `tests/…`, `scripts/…`, `config/…`…) exist | All Nusszopf paths do. What remains are paths inside the reference repositories, runtime URLs (`public/storage`, `robots.txt`) and the historical `docs/auth0`, `docs/meilisearch` |
| Every artisan command the docs name exists (`php artisan list`) | Yes: `search:reindex`, `nusszopf:health`, `newsletter:export`, `newsletter:purge-unconfirmed`, `scout:sync-index-settings`, `queue:failed/retry/flush/restart`, `about` |
| Every script named in the docs exists; `composer.json` and `package.json` scripts named in the docs exist | Yes |
| `docker compose config -q` for `docker-compose.yaml` (with the filled template), with `compose.prod.yaml`, and for `compose.dev.yaml` | All valid |
| `sh -n scripts/install.sh`, then `sh scripts/smoke-test.sh` (builds both images, runs `install.sh`, starts the stack, checks it end to end) | Syntax ok; **SMOKE TEST PASSED** |
| `git diff --check`; code fences balanced in every changed page | Clean |
| Every environment variable of the template exists in the code, and every custom variable of the code is documented or is a test knob | Yes; the ones not in the template are framework defaults, `MAIL_SCHEME`, `LOCATIONIQ_URL` (both mentioned) and test settings |
| P14-01 claims reproduced | See the finding |

Not run: the Pest and Playwright suites and Pint/Larastan, because no PHP, TypeScript, Blade or test file changed. The
smoke test covers the one script that did.

## 5. Remaining and deferred

| # | Item | Owner |
|---|---|---|
| D1 | `CHANGELOG.md` is an empty file, and `release.yml` refuses a tag without its section. The first content is the consolidation of the phase pages and `intentional-changes.md`; writing it now would invent a version | P-16 |
| D2 | **The first tag's number is inconsistent across documents:** the register says `0.x` until parity, `release-process.md` suggests `0.1.0-rc.1`, roadmap P-16 names `1.0.0-rc.N`. Recorded in `versioning.md`, not resolved: it is the maintainer's decision | P-16, maintainer |
| D3 | Gmail, Outlook and Apple Mail rendering; the real-iPhone/Android pass; the GitHub download, GHCR pull, arm64, an N-1 upgrade from a real tag; a generic SMTP relay with real TLS | P-16 (carried forward, unchanged) |
| D4 | The P-11 spec race (`search.spec.ts` recovery test) is documented as a known limitation, not fixed: it is test code | P-16 |
| D5 | CONTRIBUTING, SECURITY, CODE_OF_CONDUCT, issue/PR templates, README badges, third-party notices, the `.env.example` audit; a docs link check in CI would fit there | P-15 |
| D6 | The historical specification pages (`domain/`, `design/`, `authentication/`, `search/`, `security/`, the historical parts of `email/` and `journeys/`) keep their `Unknown`/`Inferred` markers. They describe the historical system, and each Nusszopf 2 decision is recorded in the register, `open-questions.md` (all resolved, closed or deferred) or the slice pages. They were not rewritten | none |
| D7 | Who writes changelog entries (register C1) and the breached-password check (C2) stay deferred by decision | later |
| D8 | Section 7 checklist and the sign-off of the parity report | P-17 |

## 6. Can P-14 be closed?

Yes, on the automated evidence: the checklist above is complete and nothing found was left unfixed or guessed. Two
things are the maintainer's: reading the rewritten operator pages (P14-03, P14-04) and taking the decision in D2 whenever
it suits, which does not block this phase.
