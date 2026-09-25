# P-11 Search index recovery drill (2026-09-25)

Exit evidence (`master-roadmap.md` §4): wipe Meilisearch, run one documented command, and get identical results.

The maintainer set these conditions:
- start from a populated, working installation and record representative search evidence;
- destroy the Meilisearch index or data, leaving PostgreSQL and the application data intact;
- prove the index is really gone or broken;
- run exactly the documented recovery;
- compare the result with the reference: Project and Request search, visibility, category filters, ordering, and
  "Mehr laden";
- verify that private projects stay excluded;
- recover from a clean, empty Meilisearch, not only from a partly filled index;
- keep three things apart: rebuilding the derived index, applying its settings, and normal indexing of later changes.

The backup/restore drill (P-10), queue and scheduler failure behavior (P-12) and the release (P-16) are not part of
this phase.

## Environment

One host, its own Docker daemon (`docker:28-dind`, Docker 28.5.2, Compose v2.40.3), started empty on the workstation
(Linux 7.0.0-31, Docker 28.5.1), publishing `127.0.0.1:18111`. This working copy's release was installed as an operator
installs it (`install.sh`, the release's `docker-compose.yaml` and `.env`), with Mailpit in a `compose.override.yaml`.
The Nusszopf images were built locally and loaded into the host, standing in for the GHCR pull. Meilisearch is
`getmeili/meilisearch:v1.11`, as pinned. The real page size (50) was used, not the E2E value.

The drill is `sh scripts/search-recovery-test.sh`. It takes the recovery blocks out of `docs/deployment/operations.md`
by their markers and runs them exactly as printed (`docs/testing/README.md`, "Search index recovery drill").

## 1. Source dataset and pre-wipe evidence

**Dataset:** `tests/Upgrade/seed.php`, the P-9/P-10 dataset, written through the application's models. Every
project and request was therefore indexed by **normal live indexing** (the queue worker), not by a reindex.
- 41 users;
- 80 projects: 60 public and 20 private (every fourth). All Berlin projects happen to be private.
- 198 requests: companions 40, financials 39, materials 39, others 40, rooms 40. 60 of them belong to private
  projects.
- Public projects with and without requests, remote and located ones, flexible and fixed periods, with and without
  team and motto.

**What PostgreSQL says the index must hold:** 151 documents, one per public project without requests plus one per
request of a public project. None for anything private.

**Reference recorded on the working installation:**

| Evidence | Content |
|---|---|
| Index | `items`, primary key `id`, 151 documents, the same as expected from PostgreSQL |
| Settings | filterable `req_type`, `updated_at`; sortable `updated_at`; the searchable fields of `config/scout.php`; ranking `words, typo, proximity, attribute, sort, exactness, updated_at:desc, id:asc`; `maxTotalHits` 100000 |
| Answers (`tests/SearchRecovery/probe.php`) | **153 answers** from the search page's own query side (`ProjectSearch`: its limit, offset, filter expression, highlighting and PostgreSQL visibility re-check). 13 queries × 9 filters × every "Mehr laden" page. Each answer records the raw hit order, `estimatedTotalHits`, the cards in order with their request hits in order, "Mehr laden" offered or not, and the page's failure state |
| Queries | empty (the opening page), title (`Garten`), goal (`Nachbarschaft`), request text only (`Werkzeug`), place (`Zürich`), `Berlin` (only private projects: must find nothing), motto, author (`p9user05`), a typo (`Gemeinschaftgarten`), one project (`P9TOKEN010`), a private project's token (`P9TOKEN001`), one request title, nothing (`Zzqxwv`) |
| Filters | none checked, each of the five categories, "Keine Gesuche" alone, rooms + "Keine Gesuche", all checked |
| Paging | the unfiltered search: 50 / 100 / 150 / 151 documents, 21 / 40 / 60 / 60 cards, "Mehr laden" offered three times |
| Page in Chromium (`tests/SearchRecovery/browser.mjs`) | 8 deep links (`/search`, `?q=…`, `?f[0]=…`, a combined query and filter), clicking "Mehr laden" to the end, with every card's link, title and request hits in order. `/search` showed 21 → 40 → 60 cards after 3 clicks |
| Raw index | every document and the settings (`tests/Upgrade/snapshot.sh`) |
| Privacy | 20 private projects and 60 of their requests: none in the index, and none in any of the 153 answers |

## 2. How the index was destroyed

Four losses, one after the other. PostgreSQL, the storage volume, Redis and the application containers were never
touched; only Meilisearch and its data were.

| | Loss | How | Proof that search was broken |
|---|---|---|---|
| a | **Clean, empty Meilisearch** | `docker compose rm --stop --force meilisearch`, `docker volume rm nusszopf_meilisearch-data`, `docker compose up -d --wait meilisearch` | `GET /indexes`: `{"results": [], "total": 0}`. `items` does not exist; 151 of 151 documents missing. All 117 answers failed, so the page showed "Verzopft…". In Chromium, all 8 links showed no hits. `nusszopf:health`: `search FAILED — the index items does not exist — run php artisan search:reindex` (before the fix: `search ok`) |
| b | **Lost while the site is in use** | as a, then a user saves a public project before the operator notices | Live indexing recreated `items` by itself, with Meilisearch's **default settings**: filterable `[]`, no `updated_at`/`id` rule. 1 document of 152. Every category filter failed (`Attribute req_type is not filterable`, 91 answers). `nusszopf:health` named every setting that differed |
| c | **Damaged index** | settings reset to Meilisearch's defaults; 20 documents deleted; one document's title and date altered; a private project's document and a deleted project's document injected | 133 documents, 20 missing, 2 that do not belong. The private project was found (7 leaking hits). 149 of 153 answers differed from the reference |
| d | **Corrupt on disk** | Meilisearch stopped, the first 64 KiB of every `data.mdb` in its volume overwritten with random bytes, started | Meilisearch crash-loops: `Error: MDB_INVALID: File is not an LMDB file`, `Restarting (1)`. `nusszopf:health`: search FAILED. `search:reindex` alone fails and says so: ``scout:flush` failed (… Could not resolve host: meilisearch …); the search index is incomplete`` |

## 3. The recovery, exactly as documented

`docs/deployment/operations.md`, "Search index recovery". For a, b and c, the one command:

```bash
docker compose exec php-fpm php artisan search:reindex
```

For d, the block the page gives for "If Meilisearch itself does not start":

```bash
docker compose rm --stop --force meilisearch
docker volume rm nusszopf_meilisearch-data
docker compose up -d --wait
docker compose exec php-fpm php artisan search:reindex
```

Nothing else was run, and the database was never restored. The page explains the three different mechanisms:
- the reindex applies the settings and **waits until Meilisearch has taken them**, then drops every document and
  imports from PostgreSQL;
- the entrypoint applies only the settings;
- live indexing writes only what changes. After a loss, it recreates the index *without* its settings.

## 4. Recovered index and settings

In every case the command printed `Index settings applied.` and `Search index rebuilt`, then the queue worker wrote the
documents:

| | Documents after recovery | Time from the first command to the last document | `nusszopf:health` afterwards |
|---|---|---|---|
| a | 151, the same as expected from PostgreSQL | 2 s | `search ok — available, index configured` |
| b | 152: the 151 plus the project saved during the loss, which was found with the "Keine Gesuche" filter | 1 s | ok |
| c | 151 | 2 s | ok |
| d | 151 | 10 s, including recreating the Meilisearch container | ok |

After each one, the snapshot of the index was **byte for byte the reference's**:
- every document (`search-docs.jsonl`);
- every setting (`search-settings.json`): searchable, filterable and sortable attributes, ranking rules, pagination,
  primary key `id`.

## 5. Before/after comparison

After each recovery, the harness compared everything against the reference, requiring exact equality:
- the 153 recorded answers (`probe.php` output);
- the Chromium recording of the 8 links;
- the documents and the settings.

| | Answers (153) | Page, 8 links | Documents | Settings |
|---|---|---|---|---|
| a | identical | identical | identical | identical |
| b (after the project saved meanwhile was deleted again by a user, which live indexing removed) | identical | identical | identical | identical |
| c | identical | identical | identical | identical |
| d | identical | identical | identical | identical |
| after the live-indexing checks (section 6) | identical | identical | identical | identical |

"Identical" covers everything the search specification defines:
- Project search (title, goal, description, place, motto, author) and Request search (request text only, request
  title);
- typo tolerance;
- every category filter and combination, and "Keine Gesuche";
- the order of the hits and of the cards, and the order of the request hits inside each card;
- `estimatedTotalHits`, and "Mehr laden" offered exactly while more documents exist;
- the one-card-per-project grouping across pages;
- the no-hits state.

This held only after finding P11-01 was fixed (section 7). Before the fix, the documents and settings were
identical, but 66 of the 153 answers listed the same hits in another order.

**Normal indexing after recovery.** After the fourth recovery, each of these changes reached the index by itself,
with no command:
- a new public project, found with the "Keine Gesuche" filter;
- its first request, which replaced the project's document (found with the materials filter);
- a title edit;
- unpublishing, which removed the project and its request;
- publishing again, which brought them back;
- deleting, which removed them.

No job failed during the whole drill (`failed_jobs` = 0).

**Browsers, on the production images:** `sh scripts/prod-e2e.sh tests/E2E/specs/visitor/search.spec.ts` on Chromium,
Firefox and WebKit, including the spec's own recovery test (it wipes the documents and runs `search:reindex`).
With one worker: **30 passed**, the recovery test in all three engines. With four workers, 22 passed: Firefox's and
WebKit's "scrolls back to the top" found 0 cards and their remaining serial tests did not run. The log shows why:
Chromium's recovery test was wiping and rebuilding the shared index at that moment. That is a race between engines
in the spec itself, not in the application (section 8).

## 6. Privacy and visibility

- In the reference and after every recovery, none of the 20 private projects and 60 private requests was in the
  index, and none was in any of the 153 answers. The probe checks this against PostgreSQL, not against the index.
- `Berlin`, whose projects are all private, found nothing before and after, including with the category filters. The
  page showed "Verzopft…".
- `P9TOKEN001`, a private project's own token, found only public neighbours through typo tolerance, never the project.
- In c, a private project's document was injected into the index, and was then found by 7 answers. The reindex
  removed it: it drops every document before importing, and it never imports anything private.
- After recovery, live indexing kept the rules: unpublishing removed the project and its request, and publishing
  brought them back.
- Defense in depth is unchanged: the page re-reads every hit from PostgreSQL through `Project::visible(null)`
  (BUG-002). The probe goes through that same path.

## 7. Findings and fixes

None of these contradicts a Confirmed claim about historical behavior. P11-01 concerns search ordering and is recorded
through the bug protocol (BUG-047). P11-02 and P11-03 concern the rewrite's own operator tooling, like P9-01/P10-01.

| # | What happened | Kind | Resolution |
|---|---|---|---|
| P11-01 | **The recovered index answered with the same hits in another order.** Documents and settings were identical, yet 66 of 153 answers differed, and 346 card renderings showed their requests permuted. Hits the ranking rules rank equal come in Meilisearch's internal order, which is the order they were written in. The requests of one project always tie, because they carry the project's `updated_at`. The historical client shows Meilisearch's order unchanged (**Confirmed**, `search.service.js`), and it never rebuilt its index, so the order never changed there | Defect (recovery fidelity); **BUG-047, Fix** | **Fixed.** A last ranking rule, `id:asc`: ids are time-ordered UUIDs, so equal hits come oldest first, whatever order they were written in. Existing installations get it when `php-fpm` starts, with no reindex. Recorded in `bugs.md` (BUG-047) and `intentional-changes.md`, "Equally ranked search hits are ordered oldest first", **for the maintainer's review**: it is the one visible change of this phase, and it only affects hits the historical rules rank equal |
| P11-02 | **Neither health nor the recovery noticed a missing or unconfigured index.** `nusszopf:health` and `/health` reported `search ok` with the index gone (a) or recreated without its settings (b), because they only asked whether Meilisearch answered. The troubleshooting section sends the operator to exactly that check. In the same way, `search:reindex` would report "rebuilt" when Meilisearch rejected the settings: Scout's `scout:sync-index-settings` prints the error and exits 0, and Meilisearch applies settings asynchronously anyway | Defect (operator tooling) | **Fixed.** `App\Services\Search\IndexSettings` compares the live index with `config/scout.php`. The `search` health check fails with the reason and "run php artisan search:reindex" when the index is missing or a setting differs. `search:reindex` waits until Meilisearch has applied the settings (up to 30 s), and otherwise fails with "The index settings were not applied". The entrypoint's comment no longer claims a warning its command can never trigger |
| P11-03 | **No documented way out when Meilisearch itself does not start** (d). `search:reindex` then fails, correctly, but `operations.md` only said "wiping or replacing the meilisearch-data volume" without saying how, or when that is needed | Documentation | **Fixed.** "If Meilisearch itself does not start": how to recognize it (restarting, `MDB_INVALID`, or a Meilisearch version that cannot read old data), and the four-line block, drilled as printed. "Search index recovery" now also explains the three mechanisms, what the command prints, how to tell when indexing has finished, and what health does and does not notice |

**Regression coverage:**
- `tests/Feature/Search/ReindexSearchTest.php`:
  - "answers in the same order after a rebuild …" writes tied documents newest first and requires the id order,
    before and after `search:reindex`. It failed without `id:asc`, showing the reverse order.
  - "fails, and says so, when Meilisearch does not take the index settings": the old command reported success.
- `tests/Feature/Search/SearchHealthTest.php`: the health check passes with the configured index. It fails, naming
  `search:reindex`, when the index is missing (`/health` 503), and when its settings were reset.
- `tests/Feature/Search/SearchEngineTest.php`: the ranking rules end in `updated_at:desc, id:asc`.
- `scripts/smoke-test.sh`: a freshly started production stack has both rules.
- `scripts/search-recovery-test.sh`: the whole drill, running the documented blocks as printed.

## 8. Limitations and deferred checks

| Limitation | Why, and who owns it |
|---|---|
| Dataset size: 151 documents, recovery in 1–10 s | The import is chunked (500 per job) and grows linearly with the number of public projects and requests; there is no step whose cost is anything else. A large installation was not measured; no historical figure exists to compare against (P-6: no invented budgets) |
| The drill wrote the "user changes" through the application's models (tinker), not through the browser | Those models are the code the wizard and edit screen use; their browser journeys are covered by the suite (e.g. `project-wizard.spec.ts`, `my-projects.spec.ts`). Browser-level search, including its own wipe-and-reindex test, ran on the production images (section 5) |
| A Meilisearch major upgrade that cannot read old data was not reproduced | The pin (`v1.11`) prevents it until a release changes it; such a release must say so as "Migration required" (`operations.md`, "Upgrades"). The recovery is the drilled d block |
| One host, local images | GHCR pull and arm64 belong to P-16, as in P-8…P-10 |
| Queue failure while reindexing (the worker down, Meilisearch down mid-import) | P-12 (queue/scheduler). The drill only shows that a reindex against an unreachable Meilisearch fails visibly |
| `search.spec.ts`'s recovery test wipes the one shared index, so when engines run in parallel it can race another engine's search test (seen once with 4 workers, section 5) | Test isolation, not product behavior. The spec is `serial` only within an engine. CI runs one engine per job. Found here, not fixed in P-11: give the recovery test its own index prefix, or run it after the other engines. Recorded for P-14/P-16 |
| Health cannot notice a single missing or stale document | Deliberately: checking counts would mean a database query and a Meilisearch call on every monitor poll, and during normal queued indexing the counts differ for a few seconds. `operations.md` says to reindex whenever results look wrong |

## Changed files

- `config/scout.php`: `id:asc` (BUG-047).
- `app/Services/Search/IndexSettings.php` (new), `app/Health/HealthChecker.php`,
  `app/Console/Commands/ReindexSearch.php`, `config/search.php`: P11-02.
- `docker/php/entrypoint.sh`: comment only.
- Tests:
  - `tests/Feature/Search/ReindexSearchTest.php`, `SearchEngineTest.php` and `SearchHealthTest.php` (new);
  - `tests/SearchRecovery/probe.php` and `browser.mjs` (new);
  - `scripts/search-recovery-test.sh` (new), `scripts/smoke-test.sh`.
- Docs:
  - `docs/deployment/operations.md` ("Search index recovery", troubleshooting, the status banner),
    `docs/deployment/README.md`;
  - `docs/search/README.md`, `docs/rewrite/bugs.md`, `docs/rewrite/intentional-changes.md`;
  - `docs/testing/README.md`, `docs/development/README.md`, `README-DEV.md`, `docs/release/release-process.md`,
    `docs/release/parity/README.md`.

## Status

**Evidence complete; awaiting the maintainer's review.** Every exit condition is met:
- after each of the four losses, one documented block restored identical results;
- a started from a genuinely empty Meilisearch;
- privacy held throughout;
- live indexing works after recovery.

BUG-047 changes the order of equally ranked hits and is flagged for approval.
