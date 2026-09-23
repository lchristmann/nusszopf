# Fourth Vertical Slice — Search completion

Status: **implemented and verified** (2026-09-21). This slice completed the search screen begun in the
first and third slices: grouped project cards with their matching requests, the category filter,
"Mehr laden", the skeleton and no-hits states, the scroll-to-top button, the highlighted matches, and a
documented command that rebuilds the index. It is the specification of what was built and why; scope
and sequencing come from `docs/rewrite/master-roadmap.md`, "Slice 4".

**Out of scope (unchanged)**: the server-sent contact e-mail and dialog (slice 6 — the contact-from-result
journey ends at the detail page's `mailto:` scaffold until then), My Projects (slice 5), geo search
(historically never implemented, `docs/search/README.md`).

## Historical mechanics (Confirmed against `web-nusszopf`, read in full)

| Item | Evidence | Behavior |
|---|---|---|
| Page size | `search.service.js`, `const OFFSET = 50` | 50 **documents** (a request or a request-less project each), not 50 projects; "Mehr laden" asks `offset + 50` and merges by `itemsId` |
| "More" button | `pages/search.js` | shown while `nbHits > loaded hits` |
| Grouping | `search.service.js` `groupBy(groupId)`, `Object.entries` | projects in first-appearance (relevance) order; a project's later documents nest into its card |
| Filter | `search.service.js` `_mapFilterQuery` | none or all six options checked → no filter; else `req_type = x OR …`; `none` is a real value ("Keine Gesuche") |
| Filter application | `SearchInput.js` | checkboxes edit a *pending* copy; the search icon shows "refresh" while it differs from the applied filter; the filter is applied by the next submit |
| Submit | `SearchInput.js` | Enter (after blur) or the icon; throttled 500 ms; `maxLength=30`; a clear button that only empties the field |
| Highlight | `search.service.js` `attributesToHighlight` | title, goal, description, team, motto, place, author, request title and description |
| Card | `HitCard.js`, `HitRequestCard.js` | title, goal, then one summary line (description, place, team, motto, author, non-empty, `' \| '`-joined, cut at 90), then one colored card per matching request (title, and description cut at 90 when present) |
| States | `pages/search.js`, `SkeletonHits.js`, `NoHitsSection.js` | skeleton until the first (empty-query) result; no hits → the "Verzopft" section with "Projekt starten"; a failed query looks like no hits; a failed load-more toasts "Sorry! Das hat gerade nicht geklappt." |
| Masonry | `pages/search.js` | 3 columns, 2 below 1024 px, 1 below 640 px, gap 20 px |
| Scroll to top | `pages/search.js` | fixed bottom-right circle button, smooth scroll |

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | Page size 50 documents | **Preserve** (verified) | `config/search.php`; overridable only for tests |
| 2 | Highlighted text is escaped (BUG-029) | **Fix** (implemented) | `intentional-changes.md`; `App\Support\SearchHighlight` |
| 3 | Search shows the guest's view and re-checks every document | **Replace/hardening** | `intentional-changes.md`; owner of a private project does not see it in search |
| 4 | `search:reindex`, uncapped paging (`maxTotalHits`) | **Fix** (BUG-008) | historically no recovery; Meilisearch 1.x caps hits at 1000 by default |
| 5 | A failed query shows the no-hits state | **Preserve** | the exception is now reported to the log |
| 6 | The 500 ms throttle is not reproduced | **Replace** | Livewire sends one request at a time per component; the icon spins while it runs |
| 7 | Masonry is CSS columns: cards read top-to-bottom per column | **Deviation (visual)** | `react-masonry-css` deals cards out left-to-right; achieving that server-side needs a column count at render time. Breakpoints, gap and card look are identical. Flagged for the parity audit (P-1/P-2) |
| 8 | The query stays deep-linkable (`?q=`), and the filter is too (`?f[]=`) | **Addition** (slice 3 for `q`) | historically neither was in the URL; the screen spec left the shape Unknown |
| 9 | The page opens on the skeleton and then loads (`wire:init`) | **Replace** | historically a client-side fetch; nothing indexable is lost, the search screen was never server-rendered |
| 10 | Filter popover, closed by Escape/outside click, scales in over 100 ms | **Preserve** | same as reakit's `Popover` (`animated: 100`) |
| 11 | The highlight tag is `<em>` | **Preserve** | Meilisearch's default, used historically |

## Implementation

- **Query** `App\Services\Search\ProjectSearch`: one Meilisearch query over `items` (`limit = pageSize × pages`, `offset 0`,
  so "Mehr laden" re-fetches from the start and can never duplicate), the filter expression (`filterExpression()` ignores
  anything that is not one of the six options), highlight markers, grouping and the database re-check. Results are
  `SearchResults` / `ProjectHit` / `RequestHit` values.
- **Text** `App\Support\SearchHighlight`: escape, markers → `<em>`, lodash-style truncation that ignores markers.
- **Page** `App\Livewire\Search\Search` and `search.blade.php`: `?q=`, `?f[]=`, `pages`, `ready`; the popover, the pending
  filter and the refresh icon are Alpine state that compares the picked options with `$wire.filter`; `request-card` gained
  the `hit` variant; `input` gained `displayRing`; icons `loader`, `refresh-cw`, `arrow-down-circle`, `chevron-up`.
- **Index** `config/scout.php`: `req_type` filterable, `pagination.maxTotalHits`; `php artisan search:reindex`
  (`App\Console\Commands\ReindexSearch`): settings → flush → import projects → import requests; a failing step is named and
  the command exits non-zero.
- **Operations**: `docs/deployment/operations.md`, "Search index recovery".

## Test map

| Layer | File | Covers |
|---|---|---|
| Feature | `tests/Feature/Search/SearchHighlightTest.php` | escaping, markers, truncation |
| Feature | `tests/Feature/Search/ProjectSearchTest.php` | filter expression per combination, grouping and order, stale/private/foreign documents, the load-more boundary, escaping, summary line |
| Livewire | `tests/Feature/Search/SearchPageTest.php` | skeleton → results, no hits, submit applies query and filter, 30-character limit, deep links, load-more and its error toast, popover options, nested request cards |
| Real Meilisearch | `tests/Feature/Search/SearchEngineTest.php` | settings applied, filter semantics per category combination, nesting only the matching request, highlighting, paging through 130 projects, stale document |
| Real Meilisearch | `tests/Feature/Search/ReindexSearchTest.php` | empty index → identical to live-synced, idempotent, stale documents removed, missing index, engine down |
| Feature | `tests/Feature/Search/SearchSyncFailureTest.php` | a failed request sync lands in `failed_jobs` (BUG-009) |
| E2E | `tests/E2E/specs/visitor/search.spec.ts` | query, submit-only, 30 characters, filter and refresh icon, popover, skeleton, project from result (contact entry point), scroll to top, load more, index recovery |

## Remaining gaps

- Masonry ordering (decision 7), to be judged in the parity audit.
- ~~The contact-from-result journey ends at the `mailto:` scaffold until slice 6.~~ Closed in slice 6.
- The recovery E2E needs the reindex command and Meilisearch's URL in the environment (CI sets them; locally it is skipped
  unless `E2E_REINDEX_COMMAND`/`E2E_MEILISEARCH_URL` are given, `docs/testing/README.md`).
